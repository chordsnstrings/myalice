<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
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

        Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'in',
            'author' => 'customer',
            'body' => $this->message['body'],
            'sent_at' => $this->message['sent_at'] ?? now(),
        ]);

        // Inbound reopens a resolved ticket and (re)opens the 24h service window.
        $conversation->update([
            'status' => $conversation->status === 'resolved' ? 'open' : $conversation->status,
            'window_open' => true,
            'unread' => $conversation->unread + 1,
            'last_message' => $this->message['body'],
            'last_message_at' => now(),
        ]);

        Tenancy::clear();
    }
}
