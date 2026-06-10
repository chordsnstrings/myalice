<?php

use App\Channels\ChannelManager;
use App\Jobs\SendBroadcastChunk;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\ContactChannel;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Services\BroadcastLauncher;
use App\Services\WalletService;
use App\Support\InsufficientFundsException;
use App\Support\Tenancy;

beforeEach(fn () => config(['broadcasts.pricing.default.marketing' => 1.0])); // clean whole-cent rate
afterEach(fn () => Tenancy::clear());

function ownerOf(Workspace $ws): User
{
    return User::create([
        'workspace_id' => $ws->id, 'name' => 'O', 'email' => 'o'.$ws->id.'@t.test',
        'password' => bcrypt('x'), 'workspace_role' => 'owner',
    ]);
}

function approvedTemplate(int $wsId): MessageTemplate
{
    return MessageTemplate::create(['workspace_id' => $wsId, 'name' => 'promo', 'category' => 'marketing', 'language' => 'en', 'body' => 'Hi!', 'approval_status' => 'approved']);
}

function subscriber(int $wsId, string $phone): Contact
{
    $c = Contact::create(['name' => 'C'.$phone, 'phone' => $phone, 'channel' => 'whatsapp', 'lifecycle_stage' => 'lead']);
    ContactChannel::create(['workspace_id' => $wsId, 'contact_id' => $c->id, 'channel' => 'whatsapp', 'external_id' => $phone, 'opted_in_at' => now()]);

    return $c;
}

it('blocks a broadcast that costs more than the wallet (C-03)', function () {
    $ws = Workspace::create(['name' => 'Poor WS', 'plan' => 'premium', 'wallet_balance' => 0.01]);
    Tenancy::set($ws);
    $tpl = approvedTemplate($ws->id);
    for ($i = 0; $i < 5; $i++) {
        subscriber($ws->id, '+1000'.$i);
    }
    Tenancy::clear();

    $this->actingAs(ownerOf($ws))
        ->from('/broadcasts/create')
        ->post('/broadcasts', ['name' => 'Big', 'channel' => 'whatsapp', 'message_template_id' => $tpl->id])
        ->assertRedirect('/broadcasts/create')
        ->assertSessionHasErrors('credit_cost');

    expect(Broadcast::withoutGlobalScopes()->count())->toBe(0);
    expect((float) $ws->fresh()->wallet_balance)->toBe(0.01);
});

it('refuses to debit the wallet below zero', function () {
    $ws = Workspace::create(['name' => 'WS', 'wallet_balance' => 3]);

    expect(fn () => app(WalletService::class)->debit($ws, 10, 'x'))
        ->toThrow(InsufficientFundsException::class);
});

it('launches: materializes only subscribed recipients, reserves, sends, completes', function () {
    $ws = Workspace::create(['name' => 'Rich WS', 'plan' => 'premium', 'wallet_balance' => 100]);
    Tenancy::set($ws);
    Channel::create(['workspace_id' => $ws->id, 'type' => 'whatsapp', 'name' => 'WA', 'external_id' => 'PN', 'status' => 'connected']);
    $tpl = approvedTemplate($ws->id);

    subscriber($ws->id, '+15551');
    subscriber($ws->id, '+15552');
    // opted-out — must be excluded
    $out = subscriber($ws->id, '+15553');
    ContactChannel::where('contact_id', $out->id)->update(['opted_out_at' => now()]);
    // no opt-in at all — must be excluded
    $cold = Contact::create(['name' => 'Cold', 'phone' => '+15554', 'channel' => 'whatsapp']);
    ContactChannel::create(['workspace_id' => $ws->id, 'contact_id' => $cold->id, 'channel' => 'whatsapp', 'external_id' => '+15554']);

    $b = Broadcast::create(['name' => 'Blast', 'channel' => 'whatsapp', 'message_template_id' => $tpl->id, 'status' => 'launching']);

    $this->actingAs(ownerOf($ws)); // not required, but harmless
    app(BroadcastLauncher::class)->launch($b);

    $b->refresh();
    expect($b->recipients)->toBe(2);                 // only the two subscribed
    expect($b->status)->toBe('completed');
    expect($b->recipientRows()->where('status', 'sent')->count())->toBe(2);
    expect($b->recipientRows()->pluck('provider_message_id')->filter()->count())->toBe(2);
    // rate 1.0 * 2 = 2.0 reserved and spent (all sent → no refund)
    expect((float) $ws->fresh()->wallet_balance)->toBe(98.0);
    Tenancy::clear();
});

it('refunds the reserve for recipients skipped at send time', function () {
    $ws = Workspace::create(['name' => 'Refund WS', 'plan' => 'premium', 'wallet_balance' => 100]);
    Tenancy::set($ws);
    Channel::create(['workspace_id' => $ws->id, 'type' => 'whatsapp', 'name' => 'WA', 'external_id' => 'PN', 'status' => 'connected']);
    $tpl = approvedTemplate($ws->id);
    $a = subscriber($ws->id, '+16001');
    $b2 = subscriber($ws->id, '+16002');

    // b2 opts out *after* the broadcast is materialized but before sending.
    $broadcast = Broadcast::create(['name' => 'B', 'channel' => 'whatsapp', 'message_template_id' => $tpl->id, 'status' => 'launching']);
    BroadcastRecipient::insert([
        ['workspace_id' => $ws->id, 'broadcast_id' => $broadcast->id, 'contact_id' => $a->id, 'channel' => 'whatsapp', 'external_id' => '+16001', 'status' => 'queued', 'cost' => 1.0, 'created_at' => now(), 'updated_at' => now()],
        ['workspace_id' => $ws->id, 'broadcast_id' => $broadcast->id, 'contact_id' => $b2->id, 'channel' => 'whatsapp', 'external_id' => '+16002', 'status' => 'queued', 'cost' => 1.0, 'created_at' => now(), 'updated_at' => now()],
    ]);
    $broadcast->update(['recipients' => 2, 'reserved_cost' => 2.0, 'status' => 'sending']);
    app(WalletService::class)->debit($ws, 2.0, 'reserve');
    ContactChannel::where('contact_id', $b2->id)->update(['opted_out_at' => now()]);

    $ids = $broadcast->recipientRows()->pluck('id')->all();
    (new SendBroadcastChunk($ws->id, $broadcast->id, $ids))->handle(app(ChannelManager::class));

    $broadcast->refresh();
    expect($broadcast->status)->toBe('completed');
    expect($broadcast->recipientRows()->where('status', 'sent')->count())->toBe(1);
    expect($broadcast->recipientRows()->where('status', 'skipped')->count())->toBe(1);
    expect((float) $broadcast->spent_cost)->toBe(1.0);          // 1 sent * 1.0
    expect((float) $ws->fresh()->wallet_balance)->toBe(99.0);   // reserve 2 - spent 1 -> refund 1
    Tenancy::clear();
});

it('is idempotent — a re-run of the chunk does not resend', function () {
    $ws = Workspace::create(['name' => 'Idem WS', 'plan' => 'premium', 'wallet_balance' => 100]);
    Tenancy::set($ws);
    Channel::create(['workspace_id' => $ws->id, 'type' => 'whatsapp', 'name' => 'WA', 'external_id' => 'PN', 'status' => 'connected']);
    $tpl = approvedTemplate($ws->id);
    subscriber($ws->id, '+17001');
    $b = Broadcast::create(['name' => 'B', 'channel' => 'whatsapp', 'message_template_id' => $tpl->id, 'status' => 'launching']);
    app(BroadcastLauncher::class)->launch($b);

    $sentId = $b->recipientRows()->first()->provider_message_id;
    $ids = $b->recipientRows()->pluck('id')->all();
    (new SendBroadcastChunk($ws->id, $b->id, $ids))->handle(app(ChannelManager::class));

    expect($b->recipientRows()->first()->provider_message_id)->toBe($sentId); // unchanged
    Tenancy::clear();
});

it('creates and sends a broadcast over HTTP', function () {
    $ws = Workspace::create(['name' => 'HTTP WS', 'plan' => 'premium', 'wallet_balance' => 100]);
    Tenancy::set($ws);
    Channel::create(['workspace_id' => $ws->id, 'type' => 'whatsapp', 'name' => 'WA', 'external_id' => 'PN', 'status' => 'connected']);
    $tpl = approvedTemplate($ws->id);
    subscriber($ws->id, '+19001');
    subscriber($ws->id, '+19002');
    Tenancy::clear();

    $this->actingAs(ownerOf($ws))->post('/broadcasts', [
        'name' => 'HTTP blast', 'channel' => 'whatsapp', 'message_template_id' => $tpl->id,
    ])->assertRedirect('/broadcasts');

    Tenancy::set($ws);
    $b = Broadcast::first();
    expect($b->status)->toBe('completed');
    expect($b->recipients)->toBe(2);
    Tenancy::clear();
});

it('previews recipients and cost', function () {
    $ws = Workspace::create(['name' => 'Prev WS', 'plan' => 'premium', 'wallet_balance' => 100]);
    Tenancy::set($ws);
    $tpl = approvedTemplate($ws->id);
    subscriber($ws->id, '+19101');
    subscriber($ws->id, '+19102');
    subscriber($ws->id, '+19103');
    Tenancy::clear();

    $this->actingAs(ownerOf($ws))
        ->postJson('/broadcasts/preview', ['channel' => 'whatsapp', 'message_template_id' => $tpl->id])
        ->assertOk()
        ->assertJson(['recipients' => 3, 'cost' => 3.0]);
});

it('forbids an agent from creating a broadcast', function () {
    $ws = Workspace::create(['name' => 'Gate WS', 'plan' => 'premium', 'wallet_balance' => 100]);
    $agent = User::create(['workspace_id' => $ws->id, 'name' => 'A', 'email' => 'a@bgate.test', 'password' => bcrypt('x'), 'workspace_role' => 'agent']);

    $this->actingAs($agent)->post('/broadcasts', ['name' => 'x', 'channel' => 'whatsapp'])->assertForbidden();
});

it('cancels a running broadcast and refunds the queued reserve', function () {
    $ws = Workspace::create(['name' => 'Cancel WS', 'plan' => 'premium', 'wallet_balance' => 100]);
    Tenancy::set($ws);
    $tpl = approvedTemplate($ws->id);
    $c = subscriber($ws->id, '+18001');
    $b = Broadcast::create(['name' => 'B', 'channel' => 'whatsapp', 'message_template_id' => $tpl->id, 'status' => 'sending', 'reserved_cost' => 1.0]);
    BroadcastRecipient::create(['workspace_id' => $ws->id, 'broadcast_id' => $b->id, 'contact_id' => $c->id, 'channel' => 'whatsapp', 'external_id' => '+18001', 'status' => 'queued', 'cost' => 1.0]);
    app(WalletService::class)->debit($ws, 1.0, 'reserve');
    Tenancy::clear();

    $this->actingAs(ownerOf($ws))->delete("/broadcasts/{$b->id}")->assertRedirect();

    expect($b->fresh()->status)->toBe('canceled');
    expect((float) $ws->fresh()->wallet_balance)->toBe(100.0); // fully refunded
});
