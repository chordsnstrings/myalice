<?php

use App\Ai\TopicClassifier;
use App\Jobs\ClassifyConversationTopic;
use App\Jobs\ProcessInboundMessage;
use App\Models\AiAction;
use App\Models\AiProvider;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tag;
use App\Models\Workspace;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

afterEach(fn () => Tenancy::clear());

function autoTagWs(): Workspace
{
    return Workspace::create(['name' => 'AT-'.uniqid(), 'plan' => 'business']);
}

function topicProvider(int $wsId): void
{
    AiProvider::create(['workspace_id' => $wsId, 'type' => 'openai', 'name' => 'OpenAI', 'credentials' => ['api_key' => 'sk', 'model' => 'gpt-4.1-mini'], 'status' => 'connected', 'is_default' => true, 'fallback_order' => 0]);
}

it('auto-tags a conversation from its customer messages', function () {
    $ws = autoTagWs();
    Tenancy::set($ws);
    topicProvider($ws->id);
    Tag::create(['name' => 'Shipping', 'kind' => 'topic', 'color' => 'info']);
    Tag::create(['name' => 'Returns', 'kind' => 'topic', 'color' => 'warning']);
    $contact = Contact::create(['name' => 'C', 'phone' => '+1', 'channel' => 'whatsapp']);
    $conv = Conversation::create(['contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'open']);
    Message::create(['conversation_id' => $conv->id, 'direction' => 'in', 'author' => 'customer', 'body' => 'Where is my package? It still has not shipped.', 'sent_at' => now()]);

    Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => '["Shipping"]'], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 2]], 200)]);

    app(TopicClassifier::class)->classify($conv);

    expect($conv->fresh()->tags()->pluck('name')->all())->toContain('Shipping');
    expect($conv->fresh()->tags()->pluck('name')->all())->not->toContain('Returns');
    expect(AiAction::where('type', 'tag')->where('status', 'ok')->count())->toBe(1);
    Tenancy::clear();
});

it('skips classification when no LLM provider is connected', function () {
    $ws = autoTagWs();
    Tenancy::set($ws);
    Tag::create(['name' => 'Shipping', 'kind' => 'topic', 'color' => 'info']);
    $contact = Contact::create(['name' => 'C', 'phone' => '+1', 'channel' => 'whatsapp']);
    $conv = Conversation::create(['contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'open']);
    Message::create(['conversation_id' => $conv->id, 'direction' => 'in', 'author' => 'customer', 'body' => 'hello', 'sent_at' => now()]);

    Http::fake(); // any call would be a failure
    app(TopicClassifier::class)->classify($conv);

    expect($conv->fresh()->tags()->count())->toBe(0);
    Http::assertNothingSent();
    Tenancy::clear();
});

it('classifies a conversation only once', function () {
    $ws = autoTagWs();
    Tenancy::set($ws);
    topicProvider($ws->id);
    $shipping = Tag::create(['name' => 'Shipping', 'kind' => 'topic', 'color' => 'info']);
    $contact = Contact::create(['name' => 'C', 'phone' => '+1', 'channel' => 'whatsapp']);
    $conv = Conversation::create(['contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'open']);
    $conv->tags()->attach($shipping->id); // already tagged
    Message::create(['conversation_id' => $conv->id, 'direction' => 'in', 'author' => 'customer', 'body' => 'a refund please', 'sent_at' => now()]);

    Http::fake();
    app(TopicClassifier::class)->classify($conv);

    Http::assertNothingSent();
    expect(AiAction::where('type', 'tag')->count())->toBe(0);
    Tenancy::clear();
});

it('dispatches the topic classifier on an untagged inbound message', function () {
    $ws = autoTagWs();
    Queue::fake();

    (new ProcessInboundMessage($ws->id, 'whatsapp', ['from' => '+15551234', 'type' => 'text', 'body' => 'where is my order', 'sent_at' => now()->toIso8601String()]))->handle();

    Queue::assertPushed(ClassifyConversationTopic::class);
    Tenancy::clear();
});

it('only offers the matching topics, ignoring off-list model output', function () {
    $ws = autoTagWs();
    Tenancy::set($ws);
    topicProvider($ws->id);
    Tag::create(['name' => 'Shipping', 'kind' => 'topic', 'color' => 'info']);
    $contact = Contact::create(['name' => 'C', 'phone' => '+1', 'channel' => 'whatsapp']);
    $conv = Conversation::create(['contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'open']);
    Message::create(['conversation_id' => $conv->id, 'direction' => 'in', 'author' => 'customer', 'body' => 'random', 'sent_at' => now()]);

    Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => '["Billing dispute","Shipping"]'], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 2]], 200)]);

    app(TopicClassifier::class)->classify($conv);

    expect($conv->fresh()->tags()->pluck('name')->all())->toBe(['Shipping']); // off-list "Billing dispute" dropped
    Tenancy::clear();
});
