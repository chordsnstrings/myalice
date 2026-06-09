<?php

namespace App\Jobs;

use App\Channels\ChannelManager;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Support\Tenancy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends a post-resolution CSAT survey (B10.4). Queued from ConversationObserver
 * when a conversation is resolved. Marks the conversation as awaiting a rating;
 * the inbound pipeline (ProcessInboundMessage) captures a 1–5 reply.
 */
class SendCsatSurvey implements ShouldQueue
{
    use Queueable;

    private const PROMPT = 'Thanks for chatting with us! How did we do? Reply with a number from 1 (poor) to 5 (great).';

    public function __construct(public int $conversationId) {}

    public function handle(ChannelManager $channels): void
    {
        $conversation = Conversation::withoutGlobalScopes()->with('contact')->find($this->conversationId);
        if (! $conversation) {
            return;
        }

        $workspace = Workspace::find($conversation->workspace_id);
        if (! $workspace || ! $workspace->csat_enabled) {
            return;
        }

        Tenancy::set($workspace);

        // Don't re-survey a contact already awaiting a rating.
        if ($conversation->awaiting_csat_at !== null) {
            Tenancy::clear();

            return;
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'author' => 'bot',
            'body' => self::PROMPT,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        // Deliver via the channel connector (stub-logs when unconfigured); only
        // channels with a connector can send — others record the prompt only.
        $to = $conversation->contact->phone ?? $conversation->contact->email;
        if ($to && $channels->supports($conversation->channel)) {
            $channels->for($conversation->channel)->send($to, [
                'type' => 'text',
                'text' => ['body' => self::PROMPT],
            ]);
            $message->update(['status' => 'delivered']);
        }

        $conversation->update(['awaiting_csat_at' => now()]);

        Tenancy::clear();
    }
}
