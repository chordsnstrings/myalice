<?php

use App\Models\AiAgent;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::clear());

function pageAgent(int $wsId, string $scope, string $name): AiAgent
{
    return AiAgent::create([
        'workspace_id' => $wsId, 'name' => $name, 'enabled' => true, 'mode' => 'auto',
        'goal' => 'sale', 'channel_scope' => $scope, 'tone' => 'friendly', 'methodology' => 'consultative_spin',
    ]);
}

it('resolves a page-specific agent before the channel-type and default', function () {
    $ws = Workspace::create(['name' => 'P', 'plan' => 'business']);
    Tenancy::set($ws);
    $ch = Channel::create(['workspace_id' => $ws->id, 'type' => 'whatsapp', 'name' => 'WA', 'external_id' => 'PN']);
    pageAgent($ws->id, 'all', 'Default');
    pageAgent($ws->id, 'channel:'.$ch->id, 'PageBot');

    expect(AiAgent::resolveFor('whatsapp', $ch->id)->name)->toBe('PageBot');  // page wins
    expect(AiAgent::resolveFor('whatsapp', null)->name)->toBe('Default');     // no page → default
    expect(AiAgent::resolveFor('whatsapp', 9999)->name)->toBe('Default');     // unknown page → default
});

it('stamps the originating channel_id on an inbound conversation', function () {
    $ws = Workspace::create(['name' => 'P2']);
    $ch = Channel::create(['workspace_id' => $ws->id, 'type' => 'whatsapp', 'name' => 'WA', 'external_id' => 'PNX']);

    $payload = ['object' => 'whatsapp_business_account', 'entry' => [[
        'id' => 'WABA', 'changes' => [['field' => 'messages', 'value' => [
            'metadata' => ['phone_number_id' => 'PNX'],
            'messages' => [['id' => 'wamid.p', 'from' => '+15550000', 'type' => 'text', 'text' => ['body' => 'hi']]],
        ]]],
    ]]];
    $this->postJson('/api/webhooks/whatsapp', $payload)->assertOk();

    Tenancy::set($ws);
    expect(Conversation::first()->channel_id)->toBe($ch->id);
});

it('lets an admin configure a per-channel agent by scope', function () {
    $ws = Workspace::create(['name' => 'P3', 'plan' => 'business']);
    $owner = User::create(['workspace_id' => $ws->id, 'name' => 'O', 'email' => 'o@p3.test', 'password' => bcrypt('x'), 'workspace_role' => 'owner']);
    $ch = Channel::create(['workspace_id' => $ws->id, 'type' => 'messenger', 'name' => 'Page', 'external_id' => 'PG']);
    $scope = 'channel:'.$ch->id;

    $this->actingAs($owner)->get('/settings/ai-agents?scope='.$scope)->assertOk()
        ->assertInertia(fn ($p) => $p->where('scope', $scope)->has('scopes'));

    $this->actingAs($owner)->put('/settings/ai-agents/agent', [
        'scope' => $scope, 'name' => 'PageBot', 'enabled' => true, 'mode' => 'auto', 'goal' => 'lead',
        'tone' => 'playful', 'methodology' => 'lead_capture',
    ])->assertRedirect()->assertSessionHasNoErrors();

    Tenancy::set($ws);
    expect(AiAgent::where('channel_scope', $scope)->value('name'))->toBe('PageBot');
});

it('rejects an unknown agent scope', function () {
    $ws = Workspace::create(['name' => 'P4', 'plan' => 'business']);
    $owner = User::create(['workspace_id' => $ws->id, 'name' => 'O', 'email' => 'o@p4.test', 'password' => bcrypt('x'), 'workspace_role' => 'owner']);

    $this->actingAs($owner)->put('/settings/ai-agents/agent', [
        'scope' => 'channel:99999', 'name' => 'X', 'enabled' => true, 'mode' => 'auto', 'goal' => 'sale',
        'tone' => 'friendly', 'methodology' => 'consultative_spin',
    ])->assertSessionHasErrors('scope');
});
