<?php

use App\Jobs\SendBroadcast;
use App\Models\Broadcast;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\Workspace;
use App\Services\WalletService;
use App\Support\InsufficientFundsException;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::clear());

function ownerOf(Workspace $ws): User
{
    return User::create([
        'workspace_id' => $ws->id, 'name' => 'O', 'email' => 'o'.$ws->id.'@t.test',
        'password' => bcrypt('x'), 'workspace_role' => 'owner',
    ]);
}

it('blocks a broadcast that costs more than the wallet (C-03)', function () {
    $ws = Workspace::create(['name' => 'Poor WS', 'wallet_balance' => 10]);
    $user = ownerOf($ws);

    $this->actingAs($user)
        ->from('/broadcasts/create')
        ->post('/broadcasts', [
            'name' => 'Big blast',
            'recipients' => 1000,
            'credit_cost' => 50.0,
        ])
        ->assertRedirect('/broadcasts/create')
        ->assertSessionHasErrors('credit_cost');

    expect(Broadcast::withoutGlobalScopes()->count())->toBe(0);
    expect((float) $ws->fresh()->wallet_balance)->toBe(10.0);
});

it('debits the wallet and records a ledger entry on send', function () {
    $ws = Workspace::create(['name' => 'Rich WS', 'wallet_balance' => 100]);
    Tenancy::set($ws);

    $broadcast = Broadcast::create(['name' => 'OK blast', 'recipients' => 800, 'credit_cost' => 40, 'status' => 'sending']);

    (new SendBroadcast($broadcast->id))->handle(app(WalletService::class));

    expect($broadcast->fresh()->status)->toBe('sent');
    expect((float) $ws->fresh()->wallet_balance)->toBe(60.0);
    expect(WalletTransaction::withoutGlobalScopes()->where('type', 'debit')->where('workspace_id', $ws->id)->count())->toBe(1);
});

it('pauses a broadcast when the wallet drains rather than half-sending (C-03)', function () {
    $ws = Workspace::create(['name' => 'Drain WS', 'wallet_balance' => 5]);
    Tenancy::set($ws);

    $broadcast = Broadcast::create(['name' => 'Drain blast', 'recipients' => 800, 'credit_cost' => 40, 'status' => 'sending']);

    (new SendBroadcast($broadcast->id))->handle(app(WalletService::class));

    expect($broadcast->fresh()->status)->toBe('paused');
    expect((float) $ws->fresh()->wallet_balance)->toBe(5.0);
    expect(WalletTransaction::withoutGlobalScopes()->where('workspace_id', $ws->id)->count())->toBe(0);
});

it('refuses to debit below zero', function () {
    $ws = Workspace::create(['name' => 'WS', 'wallet_balance' => 3]);

    expect(fn () => app(WalletService::class)->debit($ws, 10, 'x'))
        ->toThrow(InsufficientFundsException::class);
});
