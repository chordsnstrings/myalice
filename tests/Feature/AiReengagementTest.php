<?php

use App\Ai\SalesAgent;
use App\Jobs\SendAiReengagement;
use App\Models\AiAction;
use App\Models\AiAgent;
use App\Models\AiProvider;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->ws = Workspace::create(['name' => 'RE WS', 'plan' => 'business']);
    Tenancy::set($this->ws);

    AiProvider::create([
        'workspace_id' => $this->ws->id, 'type' => 'openai', 'name' => 'OpenAI',
        'credentials' => ['api_key' => 'sk', 'model' => 'gpt-4.1-mini'],
        'status' => 'connected', 'is_default' => true, 'fallback_order' => 0,
    ]);

    $this->agent = AiAgent::create([
        'workspace_id' => $this->ws->id, 'name' => 'Ava', 'enabled' => true, 'mode' => 'auto',
        'goal' => 'sale', 'channel_scope' => 'all', 'tone' => 'friendly', 'methodology' => 'consultative_spin',
        'guardrails' => ['reengage' => ['enabled' => true, 'min_customer_messages' => 1]],
    ]);

    $this->contact = Contact::create(['name' => 'Sam', 'phone' => '+1', 'channel' => 'web', 'lifecycle_stage' => 'lead']);

    Http::fake(['api.openai.com/*' => Http::response([
        'choices' => [['message' => ['content' => 'Still thinking it over? The blue mug is almost gone!'], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 6, 'completion_tokens' => 4],
    ], 200)]);
});

afterEach(fn () => Tenancy::clear());

/** A stalled, customer-started conversation whose last message was `hoursAgo` ago. */
function stalled(int $contactId, float $hoursAgo = 23.5, string $body = 'Do you ship internationally?', array $attrs = []): Conversation
{
    $at = now()->subHours((int) $hoursAgo)->subMinutes((int) (($hoursAgo - (int) $hoursAgo) * 60));
    $c = Conversation::create(array_merge([
        'contact_id' => $contactId, 'channel' => 'web', 'status' => 'open',
        'window_open' => true, 'last_message_at' => $at,
    ], $attrs));
    Message::create(['conversation_id' => $c->id, 'direction' => 'in', 'author' => 'customer', 'body' => $body, 'sent_at' => $at]);

    return $c;
}

it('queues a re-engagement for a stalled customer-started chat and stamps the marker', function () {
    Queue::fake();
    $c = stalled($this->contact->id);

    $this->artisan('ai:reengage')->assertSuccessful();

    Tenancy::set($this->ws);
    Queue::assertPushed(SendAiReengagement::class, fn ($j) => $j->conversationId === $c->id);
    expect($c->fresh()->reengaged_at)->not->toBeNull();
});

it('dry-run lists candidates without sending or marking', function () {
    Queue::fake();
    $c = stalled($this->contact->id);

    $this->artisan('ai:reengage --dry-run')->assertSuccessful();

    Tenancy::set($this->ws);
    Queue::assertNothingPushed();
    expect($c->fresh()->reengaged_at)->toBeNull();
});

it('ignores conversations outside the 23-24h band', function () {
    Queue::fake();
    stalled($this->contact->id, 10);   // too recent
    stalled($this->contact->id, 30);   // window already closed

    $this->artisan('ai:reengage')->assertSuccessful();
    Queue::assertNothingPushed();
});

it('skips when re-engagement is disabled', function () {
    Queue::fake();
    $this->agent->update(['guardrails' => ['reengage' => ['enabled' => false]]]);
    stalled($this->contact->id);

    $this->artisan('ai:reengage')->assertSuccessful();
    Queue::assertNothingPushed();
});

it('skips chats a human already replied to, or that were already re-engaged', function () {
    Queue::fake();
    $human = stalled($this->contact->id);
    Message::create(['conversation_id' => $human->id, 'direction' => 'out', 'author' => 'agent', 'body' => 'Hi', 'sent_at' => now()->subHours(23)]);

    $done = stalled($this->contact->id);
    $done->update(['reengaged_at' => now()->subHour()]);

    $this->artisan('ai:reengage')->assertSuccessful();
    Queue::assertNothingPushed();
});

it('skips low-intent chats with no question and no bot reply', function () {
    Queue::fake();
    stalled($this->contact->id, 23.5, 'hi');

    $this->artisan('ai:reengage')->assertSuccessful();
    Queue::assertNothingPushed();
});

it('respects an opt-out as the last customer message', function () {
    Queue::fake();
    $c = stalled($this->contact->id);
    Message::create(['conversation_id' => $c->id, 'direction' => 'in', 'author' => 'customer', 'body' => 'stop', 'sent_at' => now()->subHours(23)]);

    $this->artisan('ai:reengage')->assertSuccessful();
    Queue::assertNothingPushed();
});

it('the job sends one tailored follow-up and logs a reengage action', function () {
    $c = stalled($this->contact->id);

    (new SendAiReengagement($this->ws->id, $c->id))->handle(app(SalesAgent::class));

    Tenancy::set($this->ws);
    expect(Message::where('conversation_id', $c->id)->where('author', 'bot')->where('status', 'sent')->exists())->toBeTrue();
    expect(AiAction::where('conversation_id', $c->id)->where('type', 'reengage')->exists())->toBeTrue();
});

it('the job bails if a human replied between scan and run', function () {
    $c = stalled($this->contact->id);
    Message::create(['conversation_id' => $c->id, 'direction' => 'out', 'author' => 'agent', 'body' => 'Got it', 'sent_at' => now()]);

    (new SendAiReengagement($this->ws->id, $c->id))->handle(app(SalesAgent::class));

    Tenancy::set($this->ws);
    expect(Message::where('conversation_id', $c->id)->where('author', 'bot')->exists())->toBeFalse();
});
