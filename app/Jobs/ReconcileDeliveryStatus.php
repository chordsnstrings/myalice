<?php

namespace App\Jobs;

use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Message;
use App\Models\Workspace;
use App\Support\Tenancy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

/**
 * Reconciles provider delivery/read/failed receipts back onto our messages (and,
 * once they exist, broadcast recipients) by matching the provider message id.
 * Queued — webhooks only enqueue (§3).
 *
 * @phpstan-type Status array{external_id: string, status: string, error_code?: int|string|null, at?: string}
 */
class ReconcileDeliveryStatus implements ShouldQueue
{
    use Queueable;

    /** Status rank so a receipt never regresses (sent < delivered < read). */
    private const RANK = ['queued' => 0, 'sending' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3];

    /**
     * @param  array<int, Status>  $statuses
     */
    public function __construct(
        public int $workspaceId,
        public string $channel,
        public array $statuses,
    ) {}

    public function handle(): void
    {
        $workspace = Workspace::find($this->workspaceId);
        if (! $workspace) {
            return;
        }

        Tenancy::set($workspace);

        try {
            foreach ($this->statuses as $status) {
                $code = $status['status'];

                if ($code === 'read' && $status['external_id'] === '') {
                    $this->applyReadWatermark($status['at'] ?? null);

                    continue;
                }

                // Broadcast recipients are keyed by the provider message id.
                $recipient = BroadcastRecipient::where('provider_message_id', $status['external_id'])->first();
                if ($recipient) {
                    $this->applyToRecipient($recipient, $code);
                }

                $message = Message::where('external_id', $status['external_id'])->first();
                if (! $message) {
                    continue;
                }

                if ($code === 'failed') {
                    $message->update(['status' => 'failed']);

                    continue;
                }

                // Never regress a more-advanced status.
                if ((self::RANK[$code] ?? -1) > (self::RANK[$message->status ?? ''] ?? -1)) {
                    $message->update(['status' => $code]);
                }
            }
        } finally {
            Tenancy::clear();
        }
    }

    /** Advance a broadcast recipient's status (+ broadcast counters) without regressing. */
    private function applyToRecipient(BroadcastRecipient $recipient, string $code): void
    {
        if ($code === 'failed') {
            if ($recipient->status !== 'failed') {
                $recipient->update(['status' => 'failed']);
                Broadcast::where('id', $recipient->broadcast_id)->increment('failed');
            }

            return;
        }

        if (! isset(self::RANK[$code]) || (self::RANK[$code] <= (self::RANK[$recipient->status] ?? -1))) {
            return;
        }

        $recipient->update(['status' => $code, $code.'_at' => now()]);

        if ($code === 'delivered') {
            Broadcast::where('id', $recipient->broadcast_id)->increment('delivered');
        } elseif ($code === 'read') {
            Broadcast::where('id', $recipient->broadcast_id)->increment('read');
        }
    }

    /** Messenger/IG "read" is a watermark: mark earlier sent messages as read. */
    private function applyReadWatermark(?string $watermark): void
    {
        if ($watermark === null || $watermark === '') {
            return;
        }

        // Watermark is epoch millis; mark outbound messages sent before it as read.
        $cutoff = Carbon::createFromTimestampMs((int) $watermark);

        Message::where('direction', 'out')
            ->whereIn('status', ['sent', 'delivered'])
            ->where('sent_at', '<=', $cutoff)
            ->update(['status' => 'read']);
    }
}
