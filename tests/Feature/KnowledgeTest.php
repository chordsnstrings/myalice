<?php

use App\Ai\Prompts;
use App\Jobs\FetchKnowledgeSource;
use App\Models\AiAgent;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\KnowledgeSnippet;
use App\Models\KnowledgeSource;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

afterEach(fn () => Tenancy::clear());

function kOwner(): User
{
    $ws = Workspace::create(['name' => 'K WS', 'plan' => 'business']);

    return User::create(['workspace_id' => $ws->id, 'name' => 'O', 'email' => 'o-'.uniqid().'@k.test', 'password' => bcrypt('x'), 'workspace_role' => 'owner']);
}

function kAgent(int $wsId): AiAgent
{
    return AiAgent::create([
        'workspace_id' => $wsId, 'name' => 'A', 'enabled' => true, 'mode' => 'auto', 'goal' => 'sale',
        'channel_scope' => 'all', 'tone' => 'friendly', 'methodology' => 'consultative_spin',
    ]);
}

it('fetches a website source into chunked snippets', function () {
    $ws = Workspace::create(['name' => 'KW']);
    Tenancy::set($ws);
    $source = KnowledgeSource::create(['type' => 'website', 'url' => 'https://acme.test/faq', 'title' => 'FAQ', 'status' => 'pending']);
    Tenancy::clear();

    Http::fake(['acme.test/*' => Http::response('<html><body><h1>Shipping</h1><p>We ship worldwide in 3 days.</p><script>x()</script></body></html>', 200)]);

    (new FetchKnowledgeSource($ws->id, $source->id))->handle();

    Tenancy::set($ws);
    $source->refresh();
    expect($source->status)->toBe('fetched');
    expect($source->snippets()->count())->toBeGreaterThan(0);
    expect($source->snippets()->first()->content)->toContain('ship worldwide');
    expect($source->snippets()->first()->content)->not->toContain('x()'); // script stripped
    Tenancy::clear();
});

it('marks a website source errored when the fetch fails', function () {
    $ws = Workspace::create(['name' => 'KE']);
    Tenancy::set($ws);
    $source = KnowledgeSource::create(['type' => 'website', 'url' => 'https://bad.test/x', 'title' => 'Bad', 'status' => 'pending']);
    Tenancy::clear();

    Http::fake(['bad.test/*' => Http::response('nope', 500)]);
    (new FetchKnowledgeSource($ws->id, $source->id))->handle();

    Tenancy::set($ws);
    expect($source->fresh()->status)->toBe('error');
    Tenancy::clear();
});

it('injects matching knowledge into the system prompt, ranked by the last message', function () {
    $ws = Workspace::create(['name' => 'KI']);
    Tenancy::set($ws);
    $agent = kAgent($ws->id);
    $source = KnowledgeSource::create(['ai_agent_id' => null, 'type' => 'manual', 'title' => 'Policies', 'status' => 'fetched']);
    KnowledgeSnippet::create(['knowledge_source_id' => $source->id, 'content' => 'Our refund policy allows returns within 30 days.', 'char_count' => 47]);
    KnowledgeSnippet::create(['knowledge_source_id' => $source->id, 'content' => 'We sponsor a local football team each season.', 'char_count' => 45]);

    $contact = Contact::create(['name' => 'C', 'phone' => '+1', 'channel' => 'web']);
    $conv = Conversation::create(['contact_id' => $contact->id, 'channel' => 'web', 'status' => 'open']);
    Message::create(['conversation_id' => $conv->id, 'direction' => 'in', 'author' => 'customer', 'body' => 'what is your refund policy?', 'sent_at' => now()]);

    $prompt = Prompts::system($agent, $ws, $contact, $conv);

    expect($prompt)->toContain('KNOWLEDGE');
    expect($prompt)->toContain('refund policy allows returns');
    Tenancy::clear();
});

it('adds and removes a manual knowledge source over HTTP', function () {
    $user = kOwner();

    $this->actingAs($user)->post('/settings/ai-agents/knowledge', [
        'scope' => 'all', 'type' => 'manual', 'title' => 'Notes', 'text' => 'We are open 9 to 5, Sunday to Thursday.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    Tenancy::set($user->currentWorkspace);
    $source = KnowledgeSource::first();
    expect($source->status)->toBe('fetched');
    expect($source->snippets()->count())->toBeGreaterThan(0);
    Tenancy::clear();

    $this->actingAs($user)->delete("/settings/ai-agents/knowledge/{$source->id}")->assertRedirect();
    Tenancy::set($user->currentWorkspace);
    expect(KnowledgeSource::count())->toBe(0);
    Tenancy::clear();
});

it('queues a fetch when adding a website source', function () {
    Queue::fake();
    $user = kOwner();

    $this->actingAs($user)->post('/settings/ai-agents/knowledge', [
        'scope' => 'all', 'type' => 'website', 'title' => 'Site', 'url' => 'https://acme.test',
    ])->assertRedirect()->assertSessionHasNoErrors();

    Queue::assertPushed(FetchKnowledgeSource::class);
    Tenancy::set($user->currentWorkspace);
    expect(KnowledgeSource::where('type', 'website')->where('status', 'pending')->exists())->toBeTrue();
    Tenancy::clear();
});

it('gates knowledge management to managers', function () {
    $ws = Workspace::create(['name' => 'KG', 'plan' => 'business']);
    $agent = User::create(['workspace_id' => $ws->id, 'name' => 'Ag', 'email' => 'ag@kg.test', 'password' => bcrypt('x'), 'workspace_role' => 'agent']);

    $this->actingAs($agent)->post('/settings/ai-agents/knowledge', ['scope' => 'all', 'type' => 'manual', 'title' => 'x', 'text' => 'y'])->assertForbidden();
});
