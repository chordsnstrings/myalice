<?php

use App\Models\AutomationRule;
use App\Models\AutomationSend;
use App\Models\Contact;
use App\Models\Workspace;
use App\Services\AutomationDispatcher;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;

afterEach(fn () => Tenancy::clear());

function rule(Workspace $ws, array $attrs = []): AutomationRule
{
    return AutomationRule::create(array_merge([
        'workspace_id' => $ws->id,
        'name' => 'Abandoned cart',
        'trigger_type' => 'abandoned_cart',
        'status' => 'active',
    ], $attrs));
}

it('sends when all guards pass and debits the wallet', function () {
    $ws = Workspace::create(['name' => 'Auto WS', 'wallet_balance' => 5]);
    Tenancy::set($ws);
    $contact = Contact::create(['name' => 'Cart Abandoner', 'phone' => '+1555000']);

    $outcome = app(AutomationDispatcher::class)->dispatch(rule($ws), $contact, Carbon::parse('2026-06-09 12:00'));

    expect($outcome)->toBe('sent');
    expect(AutomationSend::count())->toBe(1);
    expect((float) $ws->fresh()->wallet_balance)->toBe(4.99);
});

it('skips, logs and does not send when the wallet is empty (G9.2)', function () {
    $ws = Workspace::create(['name' => 'Broke WS', 'wallet_balance' => 0]);
    Tenancy::set($ws);
    $contact = Contact::create(['name' => 'X', 'phone' => '+1555001']);

    $outcome = app(AutomationDispatcher::class)->dispatch(rule($ws), $contact, Carbon::parse('2026-06-09 12:00'));

    expect($outcome)->toBe('skipped_wallet');
    expect(AutomationSend::count())->toBe(0);
});

it('respects the frequency cap so a contact is not message-stormed (C-22)', function () {
    $ws = Workspace::create(['name' => 'Cap WS', 'wallet_balance' => 10]);
    Tenancy::set($ws);
    $contact = Contact::create(['name' => 'Y', 'phone' => '+1555002']);
    $rule = rule($ws);

    $first = app(AutomationDispatcher::class)->dispatch($rule, $contact, Carbon::parse('2026-06-09 12:00'));
    $second = app(AutomationDispatcher::class)->dispatch($rule, $contact, Carbon::parse('2026-06-09 13:00'));

    expect($first)->toBe('sent');
    expect($second)->toBe('skipped_frequency');
    expect(AutomationSend::count())->toBe(1);
});

it('holds during quiet hours', function () {
    $ws = Workspace::create(['name' => 'Quiet WS', 'wallet_balance' => 10]);
    Tenancy::set($ws);
    $contact = Contact::create(['name' => 'Z', 'phone' => '+1555003']);
    $rule = rule($ws, ['timing' => ['quiet_hours' => ['22:00', '08:00']]]);

    $outcome = app(AutomationDispatcher::class)->dispatch($rule, $contact, Carbon::parse('2026-06-09 23:30'));

    expect($outcome)->toBe('skipped_quiet_hours');
    expect(AutomationSend::count())->toBe(0);
});
