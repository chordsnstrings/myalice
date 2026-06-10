<?php

namespace App\Jobs;

use App\Channels\ChannelConnector;
use App\Channels\ChannelManager;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\ContactChannel;
use App\Models\MessageTemplate;
use App\Models\Workspace;
use App\Services\WalletService;
use App\Support\Tenancy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Sends one batch of broadcast recipients. tries=1 (a retry could double-send).
 * Idempotent + resumable: only ever advances a `queued` recipient, re-checks
 * eligibility per recipient, and finalizes the broadcast exactly once when the
 * last queued recipient is processed.
 */
class SendBroadcastChunk implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * @param  array<int, int>  $recipientIds
     */
    public function __construct(
        public int $workspaceId,
        public int $broadcastId,
        public array $recipientIds,
    ) {}

    public function handle(ChannelManager $channels): void
    {
        $workspace = Workspace::find($this->workspaceId);
        if (! $workspace) {
            return;
        }

        Tenancy::set($workspace);

        try {
            $broadcast = Broadcast::with('template')->find($this->broadcastId);
            if (! $broadcast || in_array($broadcast->status, ['paused', 'canceled'], true)) {
                return;
            }

            $connector = $channels->for($broadcast->channel);
            $template = $broadcast->template;
            $throttle = (int) config('broadcasts.throttle_us', 0);

            $recipients = BroadcastRecipient::where('broadcast_id', $broadcast->id)
                ->whereIn('id', $this->recipientIds)
                ->where('status', 'queued')
                ->with('contact')
                ->get();

            foreach ($recipients as $recipient) {
                $this->sendOne($connector, $broadcast, $template, $recipient);

                if ($throttle > 0) {
                    usleep($throttle);
                }
            }

            $this->finalizeIfDone($broadcast);
        } finally {
            Tenancy::clear();
        }
    }

    private function sendOne(ChannelConnector $connector, Broadcast $broadcast, ?MessageTemplate $template, BroadcastRecipient $recipient): void
    {
        // Re-check consent at send time (a STOP may have arrived after launch).
        $identity = ContactChannel::where('channel', $recipient->channel)->where('external_id', $recipient->external_id)->first();
        if ($identity && $identity->opted_out_at !== null) {
            $recipient->update(['status' => 'skipped', 'skip_reason' => 'opted_out']);

            return;
        }

        if ($broadcast->channel === 'whatsapp' && ($template === null || ! $template->isSendable())) {
            $recipient->update(['status' => 'skipped', 'skip_reason' => 'template_unavailable']);

            return;
        }

        try {
            $payload = $this->payload($broadcast, $template, $recipient);
            $providerId = $connector->send($recipient->external_id, $payload);
            $recipient->update(['status' => 'sent', 'provider_message_id' => $providerId, 'sent_at' => now()]);
        } catch (Throwable $e) {
            $recipient->update(['status' => 'failed', 'error_code' => mb_substr($e->getMessage(), 0, 190)]);
        }
    }

    /**
     * Build the channel payload for this recipient. WhatsApp = HSM template with
     * per-recipient variables; session channels = plain text body.
     *
     * @return array<string, mixed>
     */
    private function payload(Broadcast $broadcast, ?MessageTemplate $template, BroadcastRecipient $recipient): array
    {
        if ($broadcast->channel !== 'whatsapp' || $template === null) {
            $body = $template ? $template->render($this->params($broadcast, $recipient)) : '';

            return ['type' => 'text', 'text' => ['body' => $body]];
        }

        $components = [];

        if (in_array($template->header_format, ['image', 'video', 'document'], true) && $template->header_media_url) {
            $components[] = ['type' => 'header', 'parameters' => [[
                'type' => $template->header_format,
                $template->header_format => ['link' => $template->header_media_url],
            ]]];
        }

        $params = $this->params($broadcast, $recipient);
        if ($params !== []) {
            $components[] = ['type' => 'body', 'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => $p], $params)];
        }

        $tpl = ['name' => $template->name, 'language' => ['code' => $template->language]];
        if ($components !== []) {
            $tpl['components'] = $components;
        }

        return ['type' => 'template', 'template' => $tpl];
    }

    /**
     * Resolve {{1}},{{2}}… from the broadcast's variable_map against the contact.
     *
     * @return array<int, string>
     */
    private function params(Broadcast $broadcast, BroadcastRecipient $recipient): array
    {
        $map = $broadcast->variable_map ?? [];
        if ($map === []) {
            return [];
        }

        $contact = $recipient->contact;
        $params = [];
        foreach ($map as $index => $field) {
            $params[(int) $index - 1] = (string) (data_get($contact, $field) ?? '');
        }
        ksort($params);

        return array_values($params);
    }

    /** Finalize once the last queued recipient is done: refund unused, set counters. */
    private function finalizeIfDone(Broadcast $broadcast): void
    {
        if (BroadcastRecipient::where('broadcast_id', $broadcast->id)->where('status', 'queued')->exists()) {
            return;
        }

        // Single-winner transition so concurrent chunks can't double-refund.
        $won = Broadcast::where('id', $broadcast->id)->where('status', 'sending')
            ->update(['status' => 'completed', 'completed_at' => now()]);
        if (! $won) {
            return;
        }

        $rows = BroadcastRecipient::where('broadcast_id', $broadcast->id)->get(['status', 'cost']);
        $billable = $rows->whereIn('status', ['sent', 'delivered', 'read', 'replied']);
        $spent = round((float) $billable->sum('cost'), 2);
        $refund = round((float) $broadcast->reserved_cost - $spent, 2);

        if ($refund > 0) {
            app(WalletService::class)->credit(Tenancy::currentOrFail(), $refund, "Broadcast refund: {$broadcast->name}");
        }

        $broadcast->update([
            'spent_cost' => $spent,
            'failed' => $rows->where('status', 'failed')->count(),
            'recipients' => $rows->count(),
        ]);
    }
}
