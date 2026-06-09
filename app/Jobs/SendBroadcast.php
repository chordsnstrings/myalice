<?php

namespace App\Jobs;

use App\Models\Broadcast;
use App\Models\Workspace;
use App\Services\WalletService;
use App\Support\InsufficientFundsException;
use App\Support\Tenancy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends a broadcast (M14). Debits the wallet up front; if funds run out the
 * broadcast pauses rather than half-sending (C-03). Queued + throttled to Meta
 * limits in production; here it debits and marks the result.
 */
class SendBroadcast implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $broadcastId) {}

    public function handle(WalletService $wallet): void
    {
        $broadcast = Broadcast::withoutGlobalScopes()->find($this->broadcastId);
        if (! $broadcast || $broadcast->status === 'sent') {
            return;
        }

        $workspace = Workspace::find($broadcast->workspace_id);
        if (! $workspace) {
            return;
        }

        Tenancy::set($workspace);
        $broadcast->update(['status' => 'sending']);

        try {
            $wallet->debit($workspace, (float) $broadcast->credit_cost, "Broadcast: {$broadcast->name}");
        } catch (InsufficientFundsException) {
            // Pause at the boundary; preserve state for resume after top-up (C-03).
            $broadcast->update(['status' => 'paused']);
            Tenancy::clear();

            return;
        }

        $broadcast->update([
            'status' => 'sent',
            'delivered' => $broadcast->recipients,
        ]);

        Tenancy::clear();
    }
}
