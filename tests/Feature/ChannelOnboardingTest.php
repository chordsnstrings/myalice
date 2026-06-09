<?php

use App\Models\Channel;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;

function owner(): User
{
    $ws = Workspace::create(['name' => 'Onboard WS']);

    return User::create([
        'workspace_id' => $ws->id, 'name' => 'O', 'email' => 'owner@ob.test',
        'password' => bcrypt('x'), 'workspace_role' => 'owner',
    ]);
}

it('connects WhatsApp manually after verifying with the Graph API', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['verified_name' => 'Acme', 'display_phone_number' => '+15551230000'], 200),
    ]);

    $this->actingAs(owner())
        ->post('/settings/channels/whatsapp/connect', [
            'access_token' => 'EAAB-token',
            'phone_number_id' => 'PHONE777',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $channel = Channel::withoutGlobalScopes()->where('type', 'whatsapp')->first();
    expect($channel)->not->toBeNull();
    expect($channel->status)->toBe('connected');
    expect($channel->external_id)->toBe('PHONE777');
    expect($channel->credentials['access_token'])->toBe('EAAB-token'); // decrypted via cast
});

it('rejects manual connect when Meta cannot verify the credentials', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => 'bad token'], 401)]);

    $this->actingAs(owner())
        ->from('/settings/channels')
        ->post('/settings/channels/whatsapp/connect', ['access_token' => 'bad', 'phone_number_id' => 'X'])
        ->assertRedirect('/settings/channels')
        ->assertSessionHasErrors('connection');

    expect(Channel::withoutGlobalScopes()->where('type', 'whatsapp')->exists())->toBeFalse();
});

it('connects Messenger manually via the page token', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'PAGE55', 'name' => 'Acme Page'], 200)]);

    $this->actingAs(owner())
        ->post('/settings/channels/messenger/connect', ['page_token' => 'page-token'])
        ->assertSessionHas('success');

    $channel = Channel::withoutGlobalScopes()->where('type', 'messenger')->first();
    expect($channel->external_id)->toBe('PAGE55');
    expect($channel->credentials['page_token'])->toBe('page-token');
});

it('blocks Embedded Signup when no Meta app is configured', function () {
    config()->set('services.meta.app_id', null);

    $this->actingAs(owner())
        ->from('/settings/channels')
        ->post('/settings/channels/whatsapp/embedded', ['code' => 'abc', 'phone_number_id' => 'P1'])
        ->assertSessionHasErrors('embedded');
});

it('connects via Embedded Signup by exchanging the code for a token', function () {
    config()->set('services.meta.app_id', 'APP123');
    config()->set('services.meta.app_secret', 'SECRET');
    Http::fake([
        'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'long-lived'], 200),
    ]);

    $this->actingAs(owner())
        ->post('/settings/channels/whatsapp/embedded', ['code' => 'SHORT', 'phone_number_id' => 'P999'])
        ->assertSessionHas('success');

    $channel = Channel::withoutGlobalScopes()->where('type', 'whatsapp')->first();
    expect($channel->external_id)->toBe('P999');
    expect($channel->credentials['access_token'])->toBe('long-lived');
});

it('disconnects a channel', function () {
    $user = owner();
    Channel::create(['workspace_id' => $user->workspace_id, 'type' => 'messenger', 'name' => 'P', 'external_id' => 'X', 'status' => 'connected']);

    $this->actingAs($user)
        ->delete('/settings/channels/messenger')
        ->assertSessionHas('success');

    expect(Channel::withoutGlobalScopes()->where('type', 'messenger')->exists())->toBeFalse();
});

it('forbids agents from connecting channels (§4.3)', function () {
    $ws = Workspace::create(['name' => 'WS']);
    $agent = User::create(['workspace_id' => $ws->id, 'name' => 'A', 'email' => 'a@ob.test', 'password' => bcrypt('x'), 'workspace_role' => 'agent']);

    $this->actingAs($agent)
        ->post('/settings/channels/whatsapp/connect', ['access_token' => 't', 'phone_number_id' => 'p'])
        ->assertForbidden();
});
