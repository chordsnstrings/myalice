<?php

namespace App\Ai;

use App\Channels\ChannelManager;
use App\Events\MessageCreated;
use App\Models\AiAction;
use App\Models\AiAgent;
use App\Models\AiProvider;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\Tenancy;
use Illuminate\Support\Str;

/**
 * The autonomous sales agent: assembles context, runs the LLM tool loop, and
 * produces output according to the agent's autonomy mode.
 */
class SalesAgent
{
    public function __construct(
        private LlmManager $llm,
        private ToolExecutor $tools,
    ) {}

    public function run(AiAgent $agent, Conversation $conversation): void
    {
        $workspace = Tenancy::currentOrFail();
        $contact = $conversation->contact;

        $messages = [['role' => 'system', 'content' => Prompts::system($agent, $workspace, $contact, $conversation)]];
        foreach ($this->history($conversation) as $m) {
            $messages[] = $m;
        }

        $tools = $agent->mode === 'suggest' ? [] : $this->tools->definitions($agent);

        try {
            [$text, $usage, $provider, $model, $handedOff] = $this->loop($agent, $conversation, $messages, $tools);
        } catch (\Throwable $e) {
            // All providers failed: log it and stay silent — the conversation is
            // already unread so a human will pick it up. Never a broken message.
            $this->log($agent, $conversation, 'error', ['message' => $e->getMessage()], 'failed');

            return;
        }

        if (trim($text) === '') {
            return; // nothing to say (e.g. it only handed off)
        }

        $agent->mode === 'suggest'
            ? $this->emitDraft($agent, $conversation, $text)
            : $this->emitReply($agent, $conversation, $contact, $text, $usage, $provider, $model, $handedOff);
    }

    /**
     * ~23h re-engagement: craft ONE chat-specific follow-up that references the
     * customer's original question and nudges toward the close before the 24h
     * window shuts. Always sends (never a draft) — eligibility is gated upstream.
     */
    public function reengage(AiAgent $agent, Conversation $conversation): void
    {
        $workspace = Tenancy::currentOrFail();
        $contact = $conversation->contact;

        $firstInbound = $conversation->messages()
            ->where('direction', 'in')->where('author', 'customer')->orderBy('sent_at')->first();
        $topic = $firstInbound ? trim($firstInbound->body) : 'their earlier question';

        $messages = [['role' => 'system', 'content' => Prompts::system($agent, $workspace, $contact, $conversation)]];
        foreach ($this->history($conversation) as $m) {
            $messages[] = $m;
        }
        $messages[] = ['role' => 'user', 'content' => $this->reengageDirective($topic, $agent)];

        try {
            [$text, $usage, $provider, $model, $handedOff] = $this->loop($agent, $conversation, $messages, $this->tools->definitions($agent));
        } catch (\Throwable $e) {
            $this->log($agent, $conversation, 'error', ['message' => $e->getMessage(), 'context' => 'reengage'], 'failed');

            return;
        }

        if (trim($text) === '') {
            return;
        }

        $this->emitReply($agent, $conversation, $contact, $text, $usage, $provider, $model, $handedOff);
        $this->log($agent, $conversation, 'reengage', ['provider' => $provider, 'topic' => Str::limit($topic, 120)]);
    }

    private function reengageDirective(string $topic, AiAgent $agent): string
    {
        $directive = '[INTERNAL RE-ENGAGEMENT INSTRUCTION — this is not from the customer] '
            .'This customer reached out about: "'.Str::limit($topic, 200).'" and then went quiet about a day ago. '
            .'The 24-hour messaging window is about to close. Send ONE short, warm, specific follow-up that references '
            .'their actual question, adds a little value, and moves toward the close with genuine, truthful urgency about '
            .'the closing window or real stock. Never say or imply this message is automated.';

        if ($agent->guardConfig()['discount']['enabled'] ?? false) {
            $directive .= ' If their hesitation seemed to be about price, you may call offer_discount for the first layer as the hook.';
        }

        return $directive;
    }

    /**
     * Playground: run the same pipeline against ad-hoc messages without
     * persisting or sending anything.
     *
     * @param  list<array{role:string, content:string}>  $playground
     * @return array{text:string, tool_calls:list<array{name:string, arguments:array<string,mixed>}>}
     */
    public function dryRun(AiAgent $agent, array $playground): array
    {
        $workspace = Tenancy::currentOrFail();
        $conversation = new Conversation(['channel' => 'web', 'status' => 'open']);
        $contact = new Contact(['name' => 'Test Customer', 'lifecycle_stage' => 'lead']);

        $messages = [['role' => 'system', 'content' => Prompts::system($agent, $workspace, $contact, $conversation)]];
        foreach ($playground as $m) {
            $messages[] = ['role' => $m['role'] === 'assistant' ? 'assistant' : 'user', 'content' => $m['content']];
        }

        $response = $this->llm->chat($messages, $this->tools->definitions($agent), [], $agent->ai_provider_id ? AiProvider::find($agent->ai_provider_id) : null);

        return [
            'text' => $response->text,
            'tool_calls' => array_map(fn (ToolCall $c) => ['name' => $c->name, 'arguments' => $c->arguments], $response->toolCalls),
        ];
    }

    /**
     * Tool-calling loop, bounded by iterations and a wall-clock budget.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @return array{0:string,1:array{in:int,out:int},2:string,3:string,4:bool}
     */
    private function loop(AiAgent $agent, Conversation $conversation, array $messages, array $tools): array
    {
        $maxIterations = (int) config('ai.max_tool_iterations', 3);
        $budget = (int) config('ai.run_budget', 35);
        $startedAt = microtime(true);
        $preferred = $agent->ai_provider_id ? AiProvider::find($agent->ai_provider_id) : null;

        $text = '';
        $usage = ['in' => 0, 'out' => 0];
        $provider = $model = '';
        $handedOff = false;

        for ($i = 0; $i < $maxIterations; $i++) {
            if (microtime(true) - $startedAt > $budget) {
                break;
            }

            $response = $this->llm->chat($messages, $tools, [], $preferred);
            $text = $response->text;
            $usage['in'] += $response->usage['in'];
            $usage['out'] += $response->usage['out'];
            $provider = $response->provider;
            $model = $response->model;

            if (! $response->hasToolCalls()) {
                break;
            }

            $messages[] = ['role' => 'assistant', 'content' => $response->text, 'tool_calls' => $response->toolCalls];
            foreach ($response->toolCalls as $call) {
                if ($call->name === 'handoff_to_human') {
                    $handedOff = true;
                }
                $result = $this->tools->execute($call, $agent, $conversation);
                $messages[] = ['role' => 'tool', 'tool_call_id' => $call->id, 'name' => $call->name, 'content' => json_encode($result)];
            }
        }

        return [$text, $usage, $provider, $model, $handedOff];
    }

    private function emitDraft(AiAgent $agent, Conversation $conversation, string $text): void
    {
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'author' => 'bot',
            'body' => $text,
            'status' => 'draft',
            'sent_at' => now(),
        ]);
        MessageCreated::dispatch($message);
        $this->log($agent, $conversation, 'draft', []);
    }

    /** @param  array{in:int,out:int}  $usage */
    private function emitReply(AiAgent $agent, Conversation $conversation, Contact $contact, string $text, array $usage, string $provider, string $model, bool $handedOff): void
    {
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
        $to = $contact->phone ?? $contact->email;
        if ($to && $channels->supports($conversation->channel)) {
            $channels->for($conversation->channel)->send($to, ['type' => 'text', 'text' => ['body' => $text]]);
        }

        $conversation->update([
            'last_message' => $text,
            'last_message_at' => now(),
            'ai_status' => $handedOff ? 'handed_off' : 'active',
        ]);

        AiAction::create([
            'conversation_id' => $conversation->id,
            'ai_agent_id' => $agent->id,
            'type' => 'reply',
            'payload' => ['provider' => $provider, 'model' => $model],
            'status' => 'ok',
            'tokens_in' => $usage['in'],
            'tokens_out' => $usage['out'],
            'created_at' => now(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function history(Conversation $conversation): array
    {
        $limit = (int) config('ai.history_limit', 30);

        return $conversation->messages()
            ->whereIn('author', ['customer', 'agent', 'bot'])
            ->where(fn ($q) => $q->whereNull('status')->orWhereIn('status', ['sent', 'delivered', 'read']))
            ->orderBy('sent_at')
            ->limit($limit)
            ->get()
            ->map(fn (Message $m) => [
                'role' => $m->author === 'customer' ? 'user' : 'assistant',
                'content' => $m->body,
            ])->all();
    }

    /** @param  array<string, mixed>  $payload */
    private function log(AiAgent $agent, Conversation $conversation, string $type, array $payload, string $status = 'ok'): void
    {
        AiAction::create([
            'conversation_id' => $conversation->id,
            'ai_agent_id' => $agent->id,
            'type' => $type,
            'payload' => $payload,
            'status' => $status,
            'created_at' => now(),
        ]);
    }
}
