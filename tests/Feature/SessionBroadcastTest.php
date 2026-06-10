<?php

use App\Models\Broadcast;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\ContactChannel;
use App\Models\MessageTemplate;
use App\Models\Workspace;
use App\Services\AudienceBuilder;
use App\Services\BroadcastLauncher;
use App\Services\BroadcastPricing;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::clear());

/** A contact reachable on a session channel, optionally inside the 24h window. */
function sessionContact(int $wsId, string $channel, string $psid, bool $inWindow): Contact
{
    $c = Contact::create(['name' => 'C'.$psid, 'phone' => $psid, 'channel' => $channel, 'lifecycle_stage' => 'lead']);
    ContactChannel::create([
        'workspace_id' => $wsId, 'contact_id' => $c->id, 'channel' => $channel, 'external_id' => $psid,
        'last_inbound_at' => $inWindow ? now()->subHour() : now()->subDays(2),
        'window_expires_at' => $inWindow ? now()->addHours(23) : now()->subDay(),
    ]);

    return $c;
}

it('targets only in-window contacts for a Messenger broadcast and is free', function () {
    $ws = Workspace::create(['name' => 'MSG WS', 'plan' => 'premium', 'wallet_balance' => 50]);
    Tenancy::set($ws);
    Channel::create(['workspace_id' => $ws->id, 'type' => 'messenger', 'name' => 'Page', 'external_id' => 'PAGE', 'status' => 'connected']);
    $tpl = MessageTemplate::create(['workspace_id' => $ws->id, 'name' => 'hello', 'category' => 'utility', 'language' => 'en', 'body' => 'Hi {{1}}!', 'approval_status' => 'draft']);

    $in = sessionContact($ws->id, 'messenger', 'PSID_IN', true);
    sessionContact($ws->id, 'messenger', 'PSID_OUT', false); // outside 24h — excluded

    $b = Broadcast::create(['name' => 'MSG blast', 'channel' => 'messenger', 'message_template_id' => $tpl->id, 'variable_map' => ['1' => 'name'], 'status' => 'launching']);
    app(BroadcastLauncher::class)->launch($b);

    $b->refresh();
    expect($b->recipients)->toBe(1);
    expect($b->recipientRows()->first()->contact_id)->toBe($in->id);
    expect($b->recipientRows()->first()->status)->toBe('sent');
    expect((float) $b->spent_cost)->toBe(0.0);
    expect((float) $ws->fresh()->wallet_balance)->toBe(50.0); // session sends are free
    Tenancy::clear();
});

it('counts Instagram session eligibility by the 24h window', function () {
    $ws = Workspace::create(['name' => 'IG WS', 'plan' => 'premium']);
    Tenancy::set($ws);
    sessionContact($ws->id, 'instagram', 'IG_IN1', true);
    sessionContact($ws->id, 'instagram', 'IG_IN2', true);
    sessionContact($ws->id, 'instagram', 'IG_OUT', false);

    expect(app(AudienceBuilder::class)->count('instagram', null))->toBe(2);
    Tenancy::clear();
});

it('prices session channels at zero and WhatsApp by category', function () {
    $pricing = app(BroadcastPricing::class);
    expect($pricing->rate('messenger', 'marketing'))->toBe(0.0);
    expect($pricing->rate('instagram', 'marketing'))->toBe(0.0);
    expect($pricing->rate('whatsapp', 'marketing'))->toBeGreaterThan(0.0);
});
