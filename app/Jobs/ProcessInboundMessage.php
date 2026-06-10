<?php

namespace App\Jobs;

use App\Events\MessageCreated;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\CsatRating;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\RoutingService;
use App\Support\Tenancy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Persists a normalized inbound message: resolve/create the contact, find or open
 * a conversation, append the message, reopen if resolved (M1/M3). Queued — never
 * processed inline in the request (§3, SiteGround CPU limits).
 *
 * @phpstan-type Normalized array{external_id?: string, from: string, type?: string, body: string, sent_at?: string}
 */
class ProcessInboundMessage implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Normalized  $message
     */
    public function __construct(
        public int $workspaceId,
        public string $channel,
        public array $message,
    ) {}

    public function handle(): void
    {
        $workspace = Workspace::find($this->workspaceId);
        if (! $workspace) {
            return;
        }

        Tenancy::set($workspace);

        $contact = Contact::firstOrCreate(
            ['phone' => $this->message['from'], 'channel' => $this->channel],
            ['name' => $this->message['from']],
        );

        $conversation = Conversation::firstOrCreate(
            ['contact_id' => $contact->id, 'channel' => $this->channel],
            ['status' => 'open', 'window_open' => true],
        );

        // Auto-route brand-new conversations to an available agent (M5).
        if ($conversation->wasRecentlyCreated) {
            app(RoutingService::class)->assign($conversation);
        }

        $body = $this->message['body'];

        $created = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'in',
            'author' => 'customer',
            'body' => $body,
            'sent_at' => $this->message['sent_at'] ?? now(),
        ]);

        // Stream the new message to the workspace inbox in real time (A10.7).
        MessageCreated::dispatch($created);

        // A numeric reply to a pending CSAT survey records a rating and does NOT
        // reopen the resolved conversation (B10.4).
        if ($conversation->awaiting_csat_at !== null && preg_match('/^\s*([1-5])\s*$/', $body, $m)) {
            CsatRating::create([
                'conversation_id' => $conversation->id,
                'agent_id' => $conversation->assignee_id,
                'channel' => $conversation->channel,
                'rating' => (int) $m[1],
                'rated_at' => now(),
            ]);

            $conversation->update([
                'awaiting_csat_at' => null,
                'unread' => $conversation->unread + 1,
                'last_message' => $body,
                'last_message_at' => now(),
            ]);

            Tenancy::clear();

            return;
        }

        // Inbound reopens a resolved ticket and (re)opens the 24h service window.
        $conversation->update([
            'status' => $conversation->status === 'resolved' ? 'open' : $conversation->status,
            'window_open' => true,
            'unread' => $conversation->unread + 1,
            'last_message' => $body,
            'last_message_at' => now(),
        ]);

        // Let the AI agent consider a reply (debounced for burst messages, M13).
        GenerateAiReply::dispatch($this->workspaceId, $conversation->id, $created->id)
            ->delay(now()->addSeconds(8));

        Tenancy::clear();
    }
}
