<?php

namespace App\Jobs;

use App\Ai\SalesAgent;
use App\Ai\ToolCall;
use App\Ai\ToolExecutor;
use App\Channels\ChannelManager;
use App\Events\MessageCreated;
use App\Models\AiAction;
use App\Models\AiAgent;
use App\Models\Chatbot;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Support\Plans;
use App\Support\Tenancy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Decides whether the AI should engage an inbound message and, if so, runs the
 * SalesAgent. tries=1 — a retried run could double-message a customer.
 */
class GenerateAiReply implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public int $workspaceId,
        public int $conversationId,
        public int $messageId,
    ) {}

    public function handle(SalesAgent $agent): void
    {
        $workspace = Workspace::find($this->workspaceId);
        if (! $workspace) {
            return;
        }

        Tenancy::set($workspace);

        try {
            $this->engage($workspace, $agent);
        } finally {
            Tenancy::clear();
        }
    }

    private function engage(Workspace $workspace, SalesAgent $agent): void
    {
        if (! Plans::includes($workspace->plan, 'ai_agents')) {
            return;
        }

        $conversation = Conversation::with('contact')->find($this->conversationId);
        if (! $conversation) {
            return;
        }

        $config = AiAgent::resolveFor($conversation->channel);
        if (! $config || ! $config->enabled || $config->mode === 'off') {
            return;
        }
        if (! ($config->guardConfig()['engage_new_conversations'] ?? true)) {
            return;
        }

        // Debounce: only the job for the latest inbound message proceeds.
        $latest = $conversation->messages()->where('direction', 'in')->latest('sent_at')->latest('id')->first();
        if (! $latest || $latest->id !== $this->messageId) {
            return;
        }

        if (in_array($conversation->ai_status, ['handed_off', 'suppressed'], true)) {
            return;
        }

        // Human back-off: a real agent reply suppresses the AI. If a human has
        // handed the chat back ("Resume AI"), only agent messages sent AFTER the
        // resume count — earlier ones are forgiven.
        $humanReplied = $conversation->messages()->where('author', 'agent')
            ->when($conversation->ai_resumed_at, fn ($q) => $q->where('sent_at', '>', $conversation->ai_resumed_at))
            ->exists();
        if ($humanReplied) {
            $conversation->update(['ai_status' => 'suppressed']);

            return;
        }

        // A published chatbot flow owns this channel — don't double-bot.
        $flowExists = Chatbot::where('status', 'live')
            ->where(fn ($q) => $q->where('channel_scope', 'all')->orWhere('channel_scope', $conversation->channel))
            ->exists();
        if ($flowExists) {
            return;
        }

        // Explicit human request → deterministic handoff (no LLM).
        $keywords = (array) ($config->guardConfig()['handoff_keywords'] ?? []);
        $body = mb_strtolower($latest->body);
        foreach ($keywords as $kw) {
            if ($kw !== '' && str_contains($body, mb_strtolower((string) $kw))) {
                $this->handoff($config, $conversation, 'customer asked for a human');

                return;
            }
        }

        // Message-count guardrail → hand off rather than loop forever.
        $sent = AiAction::where('conversation_id', $conversation->id)->whereIn('type', ['reply', 'draft'])->count();
        if ($sent >= (int) ($config->guardConfig()['max_messages_per_conversation'] ?? 12)) {
            $this->handoff($config, $conversation, 'conversation length limit reached');

            return;
        }

        // Never auto-send into a closed 24h window — downgrade to a draft.
        if (! $conversation->window_open && $config->mode !== 'suggest') {
            $config->mode = 'suggest';
        }

        $agent->run($config, $conversation);
    }

    private function handoff(AiAgent $config, Conversation $conversation, string $reason): void
    {
        app(ToolExecutor::class)->execute(new ToolCall('kw', 'handoff_to_human', ['reason' => $reason]), $config, $conversation);

        $text = "Of course — I'm connecting you with a teammate now.";
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'author' => 'bot',
            'body' => $text,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        MessageCreated::dispatch($message);

        $channels = app(ChannelManager::class);
        $to = $conversation->contact->phone ?? $conversation->contact->email;
        if ($to && $channels->supports($conversation->channel)) {
            $channels->for($conversation->channel)->send($to, ['type' => 'text', 'text' => ['body' => $text]]);
        }

        $conversation->update(['last_message' => $text, 'last_message_at' => now()]);
    }
}
