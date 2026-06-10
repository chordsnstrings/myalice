<?php

use App\Models\Channel;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WhatsAppTemplateService;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;

afterEach(fn () => Tenancy::clear());

function tmplOwner(string $role = 'owner'): User
{
    $ws = Workspace::create(['name' => 'TPL WS', 'plan' => 'business']);

    return User::create(['workspace_id' => $ws->id, 'name' => 'U', 'email' => 'u-'.uniqid().'@t.test', 'password' => bcrypt('x'), 'workspace_role' => $role]);
}

it('creates a draft template with computed variables and components', function () {
    $user = tmplOwner();

    $this->actingAs($user)->post('/templates', [
        'name' => 'order_update',
        'category' => 'utility',
        'language' => 'en',
        'body' => 'Hi {{1}}, your order {{2}} has shipped.',
        'footer' => 'Reply STOP to opt out',
        'variable_samples' => ['Sam', 'AI-123'],
        'buttons' => [['type' => 'url', 'text' => 'Track', 'value' => 'https://x.test']],
        'submit' => false,
    ])->assertRedirect();

    Tenancy::set($user->currentWorkspace);
    $t = MessageTemplate::first();
    expect($t->approval_status)->toBe('draft');
    expect($t->variable_count)->toBe(2);
    expect(collect($t->components)->pluck('type'))->toContain('BODY', 'FOOTER', 'BUTTONS');
});

it('submits to Meta (stub) and marks pending with a meta id', function () {
    $user = tmplOwner();

    $this->actingAs($user)->post('/templates', [
        'name' => 'promo_one', 'category' => 'marketing', 'language' => 'en',
        'body' => 'Sale on now!', 'submit' => true,
    ])->assertRedirect();

    Tenancy::set($user->currentWorkspace);
    $t = MessageTemplate::first();
    expect($t->approval_status)->toBe('pending');
    expect($t->meta_template_id)->toStartWith('stub_');
});

it('syncs statuses from Meta', function () {
    $user = tmplOwner();
    Tenancy::set($user->currentWorkspace);
    Channel::create(['workspace_id' => $user->workspace_id, 'type' => 'whatsapp', 'name' => 'WA', 'external_id' => 'PN', 'credentials' => ['access_token' => 'tok', 'waba_id' => 'WABA1']]);
    MessageTemplate::create(['workspace_id' => $user->workspace_id, 'name' => 'promo_two', 'category' => 'marketing', 'language' => 'en', 'body' => 'x', 'approval_status' => 'pending']);

    Http::fake(['graph.facebook.com/*' => Http::response(['data' => [
        ['name' => 'promo_two', 'language' => 'en', 'status' => 'APPROVED', 'id' => '999'],
    ]], 200)]);

    $count = app(WhatsAppTemplateService::class)->sync();

    expect($count)->toBe(1);
    expect(MessageTemplate::first()->approval_status)->toBe('approved');
    Tenancy::clear();
});

it('applies an async template status webhook from Meta', function () {
    $user = tmplOwner();
    Tenancy::set($user->currentWorkspace);
    Channel::create(['workspace_id' => $user->workspace_id, 'type' => 'whatsapp', 'name' => 'WA', 'external_id' => 'PN9']);
    $t = MessageTemplate::create(['workspace_id' => $user->workspace_id, 'name' => 'promo', 'category' => 'marketing', 'language' => 'en', 'body' => 'x', 'approval_status' => 'pending', 'meta_template_id' => 'mt_1']);
    Tenancy::clear();

    $payload = ['object' => 'whatsapp_business_account', 'entry' => [[
        'id' => 'WABA', 'changes' => [[
            'field' => 'message_template_status_update',
            'value' => ['message_template_id' => 'mt_1', 'message_template_name' => 'promo', 'message_template_language' => 'en', 'event' => 'REJECTED', 'reason' => 'INVALID_FORMAT'],
        ]],
    ]]];

    $this->postJson('/api/webhooks/whatsapp', $payload)->assertOk();

    expect($t->fresh()->approval_status)->toBe('rejected');
    expect($t->fresh()->rejection_reason)->toBe('INVALID_FORMAT');
});

it('blocks editing an approved template', function () {
    $user = tmplOwner();
    Tenancy::set($user->currentWorkspace);
    $t = MessageTemplate::create(['workspace_id' => $user->workspace_id, 'name' => 'locked', 'category' => 'marketing', 'language' => 'en', 'body' => 'x', 'approval_status' => 'approved']);
    Tenancy::clear();

    $this->actingAs($user)->put("/templates/{$t->id}", ['name' => 'locked', 'category' => 'marketing', 'language' => 'en', 'body' => 'changed'])->assertStatus(422);
});

it('gates template management to managers/owners', function () {
    $ws = Workspace::create(['name' => 'Gate WS', 'plan' => 'business']);
    $agent = User::create(['workspace_id' => $ws->id, 'name' => 'A', 'email' => 'a@gate.test', 'password' => bcrypt('x'), 'workspace_role' => 'agent']);

    $this->actingAs($agent)->post('/templates', ['name' => 'x', 'category' => 'marketing', 'language' => 'en', 'body' => 'y'])->assertForbidden();
    $this->actingAs($agent)->post('/templates/sync')->assertForbidden();
});

it('computes variables and renders a body', function () {
    expect(MessageTemplate::countVariables('Hi {{1}} and {{2}} and {{1}}'))->toBe(2);
    $t = new MessageTemplate(['body' => 'Hi {{1}}, code {{2}}']);
    expect($t->render(['Sam', '9999']))->toBe('Hi Sam, code 9999');
});
