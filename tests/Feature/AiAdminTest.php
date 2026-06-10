<?php

use App\Models\AiAgent;
use App\Models\AiProvider;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;

function aiOwner(string $plan = 'business'): User
{
    $ws = Workspace::create(['name' => 'Admin WS', 'plan' => $plan]);

    return User::create([
        'workspace_id' => $ws->id, 'name' => 'Owner', 'email' => 'owner-'.uniqid().'@ai.test',
        'password' => bcrypt('x'), 'workspace_role' => 'owner',
    ]);
}

it('renders the AI settings page for a business owner', function () {
    $this->actingAs(aiOwner())->get('/settings/ai-agents')->assertOk();
});

it('blocks a premium workspace (plan gate)', function () {
    $this->actingAs(aiOwner('premium'))->get('/settings/ai-agents')->assertForbidden();
});

it('blocks an agent role (manage-bots gate)', function () {
    $ws = Workspace::create(['name' => 'WS', 'plan' => 'business']);
    $agent = User::create(['workspace_id' => $ws->id, 'name' => 'A', 'email' => 'a@ai.test', 'password' => bcrypt('x'), 'workspace_role' => 'agent']);

    $this->actingAs($agent)->get('/settings/ai-agents')->assertForbidden();
});

it('never exposes provider keys in the page payload', function () {
    $user = aiOwner();
    Tenancy::set($user->currentWorkspace);
    AiProvider::create([
        'workspace_id' => $user->workspace_id, 'type' => 'openai', 'name' => 'OpenAI',
        'credentials' => ['api_key' => 'sk-supersecret', 'model' => 'gpt-4.1-mini'],
        'status' => 'connected', 'is_default' => true, 'fallback_order' => 0,
    ]);
    Tenancy::clear();

    $this->actingAs($user)->get('/settings/ai-agents')->assertOk()->assertDontSee('sk-supersecret');
});

it('connects a provider after verifying the key with a live call', function () {
    Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]], 200)]);
    $user = aiOwner();

    $this->actingAs($user)->post('/settings/ai-agents/providers', [
        'preset' => 'openai', 'api_key' => 'sk-good',
    ])->assertRedirect();

    Tenancy::set($user->currentWorkspace);
    $p = AiProvider::first();
    expect($p)->not->toBeNull();
    expect($p->is_default)->toBeTrue();
    expect($p->credentials['model'])->toBe('gpt-4.1-mini');
    Tenancy::clear();
});

it('rejects a provider whose key fails verification', function () {
    Http::fake(['api.openai.com/*' => Http::response(['error' => 'bad key'], 401)]);
    $user = aiOwner();

    $this->actingAs($user)
        ->post('/settings/ai-agents/providers', ['preset' => 'openai', 'api_key' => 'sk-bad'])
        ->assertSessionHasErrors('api_key');

    Tenancy::set($user->currentWorkspace);
    expect(AiProvider::count())->toBe(0);
    Tenancy::clear();
});

it('requires the enterprise plan for custom/self-hosted presets', function () {
    $user = aiOwner('business');

    $this->actingAs($user)
        ->post('/settings/ai-agents/providers', ['preset' => 'groq', 'api_key' => 'k'])
        ->assertSessionHasErrors('preset');

    Tenancy::set($user->currentWorkspace);
    expect(AiProvider::count())->toBe(0);
    Tenancy::clear();
});

it('blocks a non-enterprise base_url override on a non-custom preset (SSRF gate)', function () {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]], 200)]);
    $user = aiOwner('business');

    $this->actingAs($user)
        ->post('/settings/ai-agents/providers', [
            'preset' => 'openai', 'api_key' => 'sk', 'base_url' => 'http://169.254.169.254/v1',
        ])
        ->assertSessionHasErrors('base_url');

    Tenancy::set($user->currentWorkspace);
    expect(AiProvider::count())->toBe(0);
    Http::assertNothingSent(); // never probed the attacker URL
    Tenancy::clear();
});

it('rejects the cloud-metadata endpoint even for enterprise', function () {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]], 200)]);
    $user = aiOwner('enterprise');

    $this->actingAs($user)
        ->post('/settings/ai-agents/providers', [
            'preset' => 'ollama', 'api_key' => 'sk', 'base_url' => 'http://169.254.169.254/v1',
        ])
        ->assertSessionHasErrors('base_url');

    Http::assertNothingSent();
});

it('lets enterprise connect a self-hosted endpoint', function () {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]], 200)]);
    $user = aiOwner('enterprise');

    $this->actingAs($user)
        ->post('/settings/ai-agents/providers', [
            'preset' => 'ollama', 'api_key' => 'sk', 'base_url' => 'http://10.0.0.5:11434/v1',
        ])
        ->assertRedirect()->assertSessionHasNoErrors();

    Tenancy::set($user->currentWorkspace);
    expect(AiProvider::first()->credentials['base_url'])->toBe('http://10.0.0.5:11434/v1');
    Tenancy::clear();
});

it('updates the agent profile', function () {
    $user = aiOwner();

    $this->actingAs($user)->put('/settings/ai-agents/agent', [
        'name' => 'Ava', 'enabled' => true, 'mode' => 'autopilot', 'goal' => 'lead',
        'tone' => 'professional', 'methodology' => 'direct_closer',
        'business_profile' => 'We sell mugs.', 'guardrails' => ['max_messages_per_conversation' => 8],
    ])->assertRedirect();

    Tenancy::set($user->currentWorkspace);
    $agent = AiAgent::resolveFor('all');
    expect($agent->name)->toBe('Ava');
    expect($agent->mode)->toBe('autopilot');
    expect($agent->goal)->toBe('lead');
    Tenancy::clear();
});

it('saves discount layers, closure techniques and re-engagement config', function () {
    $user = aiOwner();

    $this->actingAs($user)->put('/settings/ai-agents/agent', [
        'name' => 'Ava', 'enabled' => true, 'mode' => 'autopilot', 'goal' => 'sale',
        'tone' => 'friendly', 'methodology' => 'direct_closer',
        'guardrails' => [
            'closure_techniques' => ['fomo', 'authority'],
            'discount' => [
                'enabled' => true,
                'layers' => [['type' => 'free_shipping'], ['type' => 'cart_percent', 'value' => 10]],
                'max_percent' => 20,
            ],
            'reengage' => ['enabled' => true, 'min_customer_messages' => 2],
        ],
    ])->assertRedirect()->assertSessionHasNoErrors();

    Tenancy::set($user->currentWorkspace);
    $cfg = AiAgent::resolveFor('all')->guardConfig();
    expect($cfg['closure_techniques'])->toContain('fomo', 'authority');
    expect($cfg['discount']['enabled'])->toBeTrue();
    expect($cfg['discount']['layers'])->toHaveCount(2);
    expect($cfg['reengage']['enabled'])->toBeTrue();
    Tenancy::clear();
});

it('rejects an unknown closure technique or discount type', function () {
    $user = aiOwner();

    $this->actingAs($user)->put('/settings/ai-agents/agent', [
        'name' => 'Ava', 'enabled' => true, 'mode' => 'auto', 'goal' => 'sale',
        'tone' => 'friendly', 'methodology' => 'direct_closer',
        'guardrails' => ['closure_techniques' => ['mind_control']],
    ])->assertSessionHasErrors('guardrails.closure_techniques.0');
});

it('lets an admin set a catalog item as a service', function () {
    $user = aiOwner();
    Tenancy::set($user->currentWorkspace);
    $p = Product::create(['workspace_id' => $user->workspace_id, 'title' => 'Setup', 'price' => 50, 'currency' => 'USD', 'stock' => 1]);
    Tenancy::clear();

    $this->actingAs($user)->patch("/products/{$p->id}/type", ['type' => 'service'])->assertRedirect();

    Tenancy::set($user->currentWorkspace);
    expect($p->fresh()->type)->toBe('service');
    Tenancy::clear();
});

it('runs the playground without persisting or sending anything', function () {
    Http::fake(['api.openai.com/*' => Http::response([
        'choices' => [['message' => ['content' => 'Hi! How can I help?'], 'finish_reason' => 'stop']],
    ], 200)]);
    $user = aiOwner();
    Tenancy::set($user->currentWorkspace);
    AiProvider::create(['workspace_id' => $user->workspace_id, 'type' => 'openai', 'name' => 'OpenAI', 'credentials' => ['api_key' => 'sk', 'model' => 'gpt-4.1-mini'], 'status' => 'connected', 'is_default' => true, 'fallback_order' => 0]);
    Tenancy::clear();

    $this->actingAs($user)->postJson('/settings/ai-agents/playground', [
        'messages' => [['role' => 'user', 'content' => 'hello']],
    ])->assertOk()->assertJson(['text' => 'Hi! How can I help?']);

    Tenancy::set($user->currentWorkspace);
    expect(Message::count())->toBe(0);
    expect(Conversation::count())->toBe(0);
    Tenancy::clear();
});

it('sends and dismisses AI drafts', function () {
    $user = aiOwner();
    Tenancy::set($user->currentWorkspace);
    $contact = Contact::create(['name' => 'C', 'phone' => '+1', 'channel' => 'web']);
    $conv = Conversation::create(['contact_id' => $contact->id, 'channel' => 'web', 'status' => 'open']);
    $draft = Message::create(['conversation_id' => $conv->id, 'direction' => 'out', 'author' => 'bot', 'body' => 'Draft', 'status' => 'draft', 'sent_at' => now()]);
    $other = Message::create(['conversation_id' => $conv->id, 'direction' => 'out', 'author' => 'bot', 'body' => 'Draft2', 'status' => 'draft', 'sent_at' => now()]);
    Tenancy::clear();

    $this->actingAs($user)->post("/inbox/ai-drafts/{$draft->id}/send")->assertRedirect();
    expect($draft->fresh()->status)->toBe('sent');

    $this->actingAs($user)->delete("/inbox/ai-drafts/{$other->id}")->assertRedirect();
    expect(Message::find($other->id))->toBeNull();
});
