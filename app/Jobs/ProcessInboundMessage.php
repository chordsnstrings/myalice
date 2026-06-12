<?php

namespace App\Jobs;

use App\Events\MessageCreated;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Contact;
use App\Models\ContactChannel;
use App\Models\Conversation;
use App\Models\CsatRating;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\RoutingService;
use App\Support\Consent;
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
        public ?int $channelId = null,
    ) {}

    public function handle(): void
    {
        $workspace = Workspace::find($this->workspaceId);
        if (! $workspace) {
            return;
        }

        Tenancy::set($workspace);

        $from = $this->message['from'];

        $contact = Contact::firstOrCreate(
            ['phone' => $from, 'channel' => $this->channel],
            ['name' => $from],
        );

        // Per-channel identity + 24h session window (broadcast Phase 0). Inbound
        // opens the session window but does NOT imply marketing opt-in.
        $channelIdentity = ContactChannel::firstOrNew(['channel' => $this->channel, 'external_id' => $from]);
        if (! $channelIdentity->exists) {
            $channelIdentity->contact_id = $contact->id;
        }
        $channelIdentity->last_inbound_at = now();
        $channelIdentity->window_expires_at = now()->addHours(24);
        $channelIdentity->save();

        $conversation = Conversation::firstOrCreate(
            ['contact_id' => $contact->id, 'channel' => $this->channel],
            ['status' => 'open', 'window_open' => true, 'channel_id' => $this->channelId],
        );

        // Keep the originating page/number current (per-page agent resolution).
        if ($this->channelId && $conversation->channel_id !== $this->channelId) {
            $conversation->channel_id = $this->channelId;
        }

        // Auto-route brand-new conversations to an available agent (M5).
        if ($conversation->wasRecentlyCreated) {
            app(RoutingService::class)->assign($conversation);
        }

        $body = $this->message['body'];

        // Honour STOP/unsubscribe immediately (compliance).
        $optedOut = Consent::looksLikeOptOut($body);
        if ($optedOut && $channelIdentity->opted_out_at === null) {
            Consent::recordOptOut($channelIdentity, 'inbound_keyword', $body);
        }

        $created = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'in',
            'author' => 'customer',
            'body' => $body,
            'external_id' => $this->message['external_id'] ?? null,
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

        // Attribute a reply to the most recent broadcast that reached this contact.
        $recipient = BroadcastRecipient::where('contact_id', $contact->id)
            ->whereIn('status', ['sent', 'delivered', 'read'])
            ->whereNull('replied_at')
            ->latest('sent_at')->first();
        if ($recipient) {
            $recipient->update(['status' => 'replied', 'replied_at' => now()]);
            Broadcast::where('id', $recipient->broadcast_id)->increment('replied');
        }

        // Auto-tag the conversation's topic (cheap LLM pass) while it's untagged.
        if ($conversation->tags()->doesntExist()) {
            ClassifyConversationTopic::dispatch($this->workspaceId, $conversation->id);
        }

        // An opt-out takes the conversation off the AI; otherwise let the agent
        // consider a reply (debounced for burst messages, M13).
        if ($optedOut) {
            $conversation->update(['ai_status' => 'suppressed']);
        } else {
            GenerateAiReply::dispatch($this->workspaceId, $conversation->id, $created->id)
                ->delay(now()->addSeconds(8));
        }

        Tenancy::clear();
    }
}
