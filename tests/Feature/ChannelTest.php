<?php

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WebhookEvent;
use App\Models\Workspace;

function waPayload(string $messageId, string $phoneId, string $from = '15551230000', string $body = 'Hello there'): array
{
    return [
        'entry' => [[
            'id' => 'ENTRY1',
            'changes' => [[
                'value' => [
                    'metadata' => ['phone_number_id' => $phoneId],
                    'messages' => [[
                        'id' => $messageId,
                        'from' => $from,
                        'type' => 'text',
                        'text' => ['body' => $body],
                    ]],
                ],
            ]],
        ]],
    ];
}

it('verifies the WhatsApp webhook handshake', function () {
    config()->set('services.whatsapp.verify_token', 'secret-verify');

    $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=secret-verify&hub_challenge=12345')
        ->assertOk()
        ->assertSee('12345');

    $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=12345')
        ->assertForbidden();
});

it('ingests an inbound WhatsApp message into a normalized conversation', function () {
    $ws = Workspace::create(['name' => 'Inbound WS']);
    Channel::create(['workspace_id' => $ws->id, 'type' => 'whatsapp', 'name' => 'WA', 'external_id' => 'PHONE123']);

    $this->postJson('/api/webhooks/whatsapp', waPayload('wamid.1', 'PHONE123'))
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    expect(Contact::withoutGlobalScopes()->where('workspace_id', $ws->id)->count())->toBe(1);
    expect(Conversation::withoutGlobalScopes()->where('workspace_id', $ws->id)->count())->toBe(1);
    expect(Message::withoutGlobalScopes()->where('workspace_id', $ws->id)->where('body', 'Hello there')->exists())->toBeTrue();
});

it('is idempotent against provider retries (M1-FR-06)', function () {
    $ws = Workspace::create(['name' => 'Idem WS']);
    Channel::create(['workspace_id' => $ws->id, 'type' => 'whatsapp', 'name' => 'WA', 'external_id' => 'PHONE9']);

    $payload = waPayload('wamid.dup', 'PHONE9');

    $this->postJson('/api/webhooks/whatsapp', $payload)->assertJson(['status' => 'ok']);
    $this->postJson('/api/webhooks/whatsapp', $payload)->assertJson(['status' => 'duplicate']);

    expect(WebhookEvent::where('event_id', 'wamid.dup')->count())->toBe(1);
    expect(Message::withoutGlobalScopes()->where('workspace_id', $ws->id)->count())->toBe(1);
});
