<?php

use App\Channels\ChannelManager;
use App\Jobs\SendOutboundMessage;
use App\Models\Channel;
use App\Models\ConsentEvent;
use App\Models\Contact;
use App\Models\ContactChannel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::clear());

function waInbound(string $phoneId, string $from = '+15551234567', string $wamid = 'wamid.A', string $text = 'hello'): array
{
    return ['object' => 'whatsapp_business_account', 'entry' => [[
        'id' => 'WABA', 'changes' => [[
            'field' => 'messages',
            'value' => [
                'metadata' => ['phone_number_id' => $phoneId],
                'messages' => [['id' => $wamid, 'from' => $from, 'type' => 'text', 'text' => ['body' => $text]]],
            ],
        ]],
    ]]];
}

function waStatus(string $phoneId, string $wamid, string $status): array
{
    return ['object' => 'whatsapp_business_account', 'entry' => [[
        'id' => 'WABA', 'changes' => [[
            'field' => 'messages',
            'value' => [
                'metadata' => ['phone_number_id' => $phoneId],
                'statuses' => [['id' => $wamid, 'status' => $status, 'recipient_id' => '15551234567']],
            ],
        ]],
    ]]];
}

it('creates a per-channel identity with a 24h window on inbound (no marketing opt-in)', function () {
    $ws = Workspace::create(['name' => 'WA WS']);
    Channel::create(['workspace_id' => $ws->id, 'type' => 'whatsapp', 'name' => 'WA', 'external_id' => 'PN1']);

    $this->postJson('/api/webhooks/whatsapp', waInbound('PN1', '+15551112222'))->assertOk();

    Tenancy::set($ws);
    $cc = ContactChannel::where('channel', 'whatsapp')->where('external_id', '+15551112222')->first();
    expect($cc)->not->toBeNull();
    expect($cc->window_expires_at->isFuture())->toBeTrue();
    expect($cc->opted_in_at)->toBeNull();       // inbound is a session, not consent
    expect($cc->inWindow())->toBeTrue();
    expect($cc->isSubscribed())->toBeFalse();
});

it('reuses the same channel identity on a second inbound', function () {
    $ws = Workspace::create(['name' => 'WA WS2']);
    Channel::create(['workspace_id' => $ws->id, 'type' => 'whatsapp', 'name' => 'WA', 'external_id' => 'PN2']);

    $this->postJson('/api/webhooks/whatsapp', waInbound('PN2', '+15553334444', 'wamid.1'))->assertOk();
    $this->postJson('/api/webhooks/whatsapp', waInbound('PN2', '+15553334444', 'wamid.2'))->assertOk();

    Tenancy::set($ws);
    expect(ContactChannel::where('external_id', '+15553334444')->count())->toBe(1);
    expect(Contact::count())->toBe(1);
});

it('records an opt-out on a STOP message and suppresses the AI', function () {
    $ws = Workspace::create(['name' => 'WA WS3']);
    Channel::create(['workspace_id' => $ws->id, 'type' => 'whatsapp', 'name' => 'WA', 'external_id' => 'PN3']);

    $this->postJson('/api/webhooks/whatsapp', waInbound('PN3', '+15555556666', 'wamid.optout', 'STOP'))->assertOk();

    Tenancy::set($ws);
    $cc = ContactChannel::where('external_id', '+15555556666')->first();
    expect($cc->opted_out_at)->not->toBeNull();
    expect($cc->isSubscribed())->toBeFalse();
    expect(ConsentEvent::where('type', 'opt_out')->exists())->toBeTrue();
    expect(Conversation::where('contact_id', $cc->contact_id)->value('ai_status'))->toBe('suppressed');
});

it('stores the inbound provider message id', function () {
    $ws = Workspace::create(['name' => 'WA WS4']);
    Channel::create(['workspace_id' => $ws->id, 'type' => 'whatsapp', 'name' => 'WA', 'external_id' => 'PN4']);

    $this->postJson('/api/webhooks/whatsapp', waInbound('PN4', '+15557778888', 'wamid.in'))->assertOk();

    Tenancy::set($ws);
    expect(Message::where('external_id', 'wamid.in')->exists())->toBeTrue();
});

it('reconciles a delivery then read receipt onto the outbound message', function () {
    $ws = Workspace::create(['name' => 'WA WS5']);
    Channel::create(['workspace_id' => $ws->id, 'type' => 'whatsapp', 'name' => 'WA', 'external_id' => 'PN5']);
    Tenancy::set($ws);
    $contact = Contact::create(['name' => 'C', 'phone' => '+15559990000', 'channel' => 'whatsapp']);
    $conv = Conversation::create(['contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'open']);
    $msg = Message::create(['conversation_id' => $conv->id, 'direction' => 'out', 'author' => 'agent', 'body' => 'hi', 'status' => 'sent', 'external_id' => 'wamid.out', 'sent_at' => now()]);
    Tenancy::clear();

    $this->postJson('/api/webhooks/whatsapp', waStatus('PN5', 'wamid.out', 'delivered'))->assertOk();
    expect($msg->fresh()->status)->toBe('delivered');

    $this->postJson('/api/webhooks/whatsapp', waStatus('PN5', 'wamid.out', 'read'))->assertOk();
    expect($msg->fresh()->status)->toBe('read');

    // A late 'delivered' must not regress a 'read' message.
    $this->postJson('/api/webhooks/whatsapp', waStatus('PN5', 'wamid.out', 'delivered'))->assertJson(['status' => 'duplicate']);
    expect($msg->fresh()->status)->toBe('read');
});

it('marks a failed receipt as failed', function () {
    $ws = Workspace::create(['name' => 'WA WS6']);
    Channel::create(['workspace_id' => $ws->id, 'type' => 'whatsapp', 'name' => 'WA', 'external_id' => 'PN6']);
    Tenancy::set($ws);
    $contact = Contact::create(['name' => 'C', 'phone' => '+1', 'channel' => 'whatsapp']);
    $conv = Conversation::create(['contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'open']);
    $msg = Message::create(['conversation_id' => $conv->id, 'direction' => 'out', 'author' => 'agent', 'body' => 'hi', 'status' => 'sent', 'external_id' => 'wamid.fail', 'sent_at' => now()]);
    Tenancy::clear();

    $this->postJson('/api/webhooks/whatsapp', waStatus('PN6', 'wamid.fail', 'failed'))->assertOk();
    expect($msg->fresh()->status)->toBe('failed');
});

it('stores the provider id when sending an outbound message', function () {
    $ws = Workspace::create(['name' => 'WA WS7']);
    Tenancy::set($ws);
    $contact = Contact::create(['name' => 'C', 'phone' => '+15550001111', 'channel' => 'whatsapp']);
    $conv = Conversation::create(['contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'open']);
    $msg = Message::create(['conversation_id' => $conv->id, 'direction' => 'out', 'author' => 'agent', 'body' => 'hi', 'status' => 'queued', 'sent_at' => now()]);

    (new SendOutboundMessage($msg->id, 'whatsapp', '+15550001111'))->handle(app(ChannelManager::class));

    expect($msg->fresh()->external_id)->not->toBeNull();
    expect($msg->fresh()->status)->toBe('sent');
});
