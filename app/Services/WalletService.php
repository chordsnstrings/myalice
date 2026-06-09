<?php

namespace App\Services;

use App\Models\WalletTransaction;
use App\Models\Workspace;
use App\Support\InsufficientFundsException;
use Illuminate\Support\Facades\DB;

/**
 * Prepaid messaging wallet (M18). All movements go through here so the balance
 * and the auditable ledger never diverge. Debits are atomic and refuse to go
 * negative — broadcasts are blocked, not half-sent (C-03).
 */
class WalletService
{
    public function debit(Workspace $workspace, float $amount, string $description): WalletTransaction
    {
        return DB::transaction(function () use ($workspace, $amount, $description) {
            $fresh = Workspace::lockForUpdate()->findOrFail($workspace->id);
            $balance = (float) $fresh->wallet_balance;

            if ($amount > $balance) {
                throw new InsufficientFundsException(round($amount - $balance, 2));
            }

            $after = round($balance - $amount, 2);
            $fresh->update(['wallet_balance' => $after]);

            return WalletTransaction::create([
                'workspace_id' => $workspace->id,
                'type' => 'debit',
                'amount' => $amount,
                'balance_after' => $after,
                'description' => $description,
            ]);
        });
    }

    public function credit(Workspace $workspace, float $amount, string $description): WalletTransaction
    {
        return DB::transaction(function () use ($workspace, $amount, $description) {
            $fresh = Workspace::lockForUpdate()->findOrFail($workspace->id);
            $after = round((float) $fresh->wallet_balance + $amount, 2);
            $fresh->update(['wallet_balance' => $after]);

            return WalletTransaction::create([
                'workspace_id' => $workspace->id,
                'type' => 'credit',
                'amount' => $amount,
                'balance_after' => $after,
                'description' => $description,
            ]);
        });
    }
}
