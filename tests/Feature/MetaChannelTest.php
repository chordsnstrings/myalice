<?php

use App\Channels\MessengerConnector;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;

function messagingPayload(string $pageId, string $senderId = 'PSID123', string $mid = 'mid.1', string $text = 'Hi via Messenger'): array
{
    return [
        'object' => 'page',
        'entry' => [[
            'id' => $pageId,
            'messaging' => [[
                'sender' => ['id' => $senderId],
                'recipient' => ['id' => $pageId],
                'message' => ['mid' => $mid, 'text' => $text],
            ]],
        ]],
    ];
}

it('ingests an inbound Messenger message', function () {
    $ws = Workspace::create(['name' => 'FB WS']);
    Channel::create(['workspace_id' => $ws->id, 'type' => 'messenger', 'name' => 'Page', 'external_id' => 'PAGE1']);

    $this->postJson('/api/webhooks/messenger', messagingPayload('PAGE1'))
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    expect(Contact::withoutGlobalScopes()->where('workspace_id', $ws->id)->count())->toBe(1);
    expect(Message::withoutGlobalScopes()->where('workspace_id', $ws->id)->where('body', 'Hi via Messenger')->exists())->toBeTrue();
});

it('ingests an inbound Instagram DM and is idempotent', function () {
    $ws = Workspace::create(['name' => 'IG WS']);
    Channel::create(['workspace_id' => $ws->id, 'type' => 'instagram', 'name' => 'IG', 'external_id' => 'IG1']);

    $payload = messagingPayload('IG1', 'IGSID', 'mid.ig.dup', 'Hello on IG');

    $this->postJson('/api/webhooks/instagram', $payload)->assertJson(['status' => 'ok']);
    $this->postJson('/api/webhooks/instagram', $payload)->assertJson(['status' => 'duplicate']);

    expect(Message::withoutGlobalScopes()->where('workspace_id', $ws->id)->count())->toBe(1);
    expect(Conversation::withoutGlobalScopes()->where('workspace_id', $ws->id)->count())->toBe(1);
});

it('verifies the Messenger handshake', function () {
    config()->set('services.messenger.verify_token', 'fb-verify');

    $this->get('/api/webhooks/messenger?hub_mode=subscribe&hub_verify_token=fb-verify&hub_challenge=CH')
        ->assertOk()->assertSee('CH');
});

it('rejects a payload with an invalid signature when the app secret is set', function () {
    config()->set('services.messenger.app_secret', 'topsecret');
    $ws = Workspace::create(['name' => 'Sig WS']);
    Channel::create(['workspace_id' => $ws->id, 'type' => 'messenger', 'name' => 'Page', 'external_id' => 'PAGE2']);

    $this->postJson('/api/webhooks/messenger', messagingPayload('PAGE2'), ['X-Hub-Signature-256' => 'sha256=deadbeef'])
        ->assertForbidden();

    expect(Message::withoutGlobalScopes()->count())->toBe(0);
});

it('accepts a payload with a valid signature', function () {
    config()->set('services.messenger.app_secret', 'topsecret');
    $ws = Workspace::create(['name' => 'Sig OK WS']);
    Channel::create(['workspace_id' => $ws->id, 'type' => 'messenger', 'name' => 'Page', 'external_id' => 'PAGE3']);

    $payload = messagingPayload('PAGE3', 'PSID', 'mid.sig', 'Signed message');
    $body = json_encode($payload);
    $sig = 'sha256='.hash_hmac('sha256', $body, 'topsecret');

    $this->call('POST', '/api/webhooks/messenger', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $sig,
    ], $body)->assertOk()->assertJson(['status' => 'ok']);

    expect(Message::withoutGlobalScopes()->where('workspace_id', $ws->id)->where('body', 'Signed message')->exists())->toBeTrue();
});

it('sends an outbound Messenger message via the Graph API', function () {
    config()->set('services.messenger.page_token', 'PAGE_TOKEN');
    Http::fake([
        'graph.facebook.com/*' => Http::response(['message_id' => 'm_abc'], 200),
    ]);

    $id = app(MessengerConnector::class)->send('PSID123', ['type' => 'text', 'text' => ['body' => 'Hello!']]);

    expect($id)->toBe('m_abc');
    Http::assertSent(fn ($request) => str_contains($request->url(), '/me/messages')
        && $request['recipient']['id'] === 'PSID123'
        && $request['message']['text'] === 'Hello!');
});
