<?php

use App\Models\AiAgent;
use App\Models\AiProvider;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
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
