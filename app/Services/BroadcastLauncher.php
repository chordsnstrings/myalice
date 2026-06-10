<?php

namespace App\Services;

use App\Jobs\SendBroadcastChunk;
use App\Models\Audience;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\MessageTemplate;
use App\Support\InsufficientFundsException;
use App\Support\Tenancy;

/**
 * Turns a broadcast into a running send: materialize recipients (filtered +
 * deduped + exactly-once), reserve the wallet cost, and enqueue paced chunks.
 */
class BroadcastLauncher
{
    public function __construct(
        private AudienceBuilder $audiences,
        private BroadcastPricing $pricing,
        private WalletService $wallet,
    ) {}

    /**
     * Audience size + estimated cost preview.
     *
     * @return array{recipients: int, rate: float, cost: float}
     */
    public function estimate(string $channel, ?Audience $audience, string $category): array
    {
        $count = $this->audiences->count($channel, $audience);
        $rate = $this->pricing->rate($channel, $category);

        return ['recipients' => $count, 'rate' => $rate, 'cost' => round($count * $rate, 2)];
    }

    /**
     * Launch the broadcast. Throws InsufficientFundsException if the reserve fails.
     */
    public function launch(Broadcast $broadcast): void
    {
        $workspace = Tenancy::currentOrFail();
        $category = (string) (MessageTemplate::whereKey($broadcast->message_template_id)->value('category') ?? 'marketing');
        $rate = $this->pricing->rate($broadcast->channel, $category);

        // Materialize recipients (insertOrIgnore keeps it exactly-once + resumable).
        $this->audiences->query($broadcast->channel, $broadcast->audience)
            ->with('contact')
            ->chunkById(500, function ($rows) use ($broadcast, $rate) {
                $insert = [];
                foreach ($rows as $cc) {
                    $insert[] = [
                        'workspace_id' => $broadcast->workspace_id,
                        'broadcast_id' => $broadcast->id,
                        'contact_id' => $cc->contact_id,
                        'channel' => $cc->channel,
                        'external_id' => $cc->external_id,
                        'status' => 'queued',
                        'cost' => $rate,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if ($insert !== []) {
                    BroadcastRecipient::insertOrIgnore($insert);
                }
            });

        $count = BroadcastRecipient::where('broadcast_id', $broadcast->id)->count();
        $reserved = round($count * $rate, 2);

        if ($count === 0) {
            $broadcast->update(['recipients' => 0, 'status' => 'completed', 'completed_at' => now()]);

            return;
        }

        // Session channels (Messenger/Instagram) are free — no reserve needed.
        if ($reserved > 0) {
            try {
                $this->wallet->debit($workspace, $reserved, "Broadcast reserve: {$broadcast->name}");
            } catch (InsufficientFundsException $e) {
                $broadcast->update(['status' => 'failed', 'recipients' => $count]);
                throw $e;
            }
        }

        $broadcast->update([
            'recipients' => $count,
            'reserved_cost' => $reserved,
            'credit_cost' => $reserved,
            'status' => 'sending',
            'started_at' => now(),
        ]);

        $this->dispatchQueued($broadcast);
    }

    /** (Re)dispatch chunk jobs for any still-queued recipients (launch + resume). */
    public function dispatchQueued(Broadcast $broadcast): void
    {
        $size = (int) config('broadcasts.chunk_size', 50);

        BroadcastRecipient::where('broadcast_id', $broadcast->id)
            ->where('status', 'queued')
            ->pluck('id')
            ->chunk($size)
            ->each(fn ($ids) => SendBroadcastChunk::dispatch($broadcast->workspace_id, $broadcast->id, $ids->all()));
    }
}
