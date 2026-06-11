<?php

use App\Ai\SalesAgent;
use App\Jobs\GenerateAiReply;
use App\Models\AiAction;
use App\Models\AiAgent;
use App\Models\AiProvider;
use App\Models\Chatbot;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->ws = Workspace::create(['name' => 'AI WS', 'plan' => 'business']);
    Tenancy::set($this->ws);

    $this->provider = AiProvider::create([
        'workspace_id' => $this->ws->id,
        'type' => 'openai',
        'name' => 'OpenAI',
        'credentials' => ['api_key' => 'sk-test', 'model' => 'gpt-4.1-mini'],
        'status' => 'connected',
        'is_default' => true,
        'fallback_order' => 0,
    ]);

    $this->agent = AiAgent::create([
        'workspace_id' => $this->ws->id,
        'name' => 'Closer',
        'enabled' => true,
        'mode' => 'auto',
        'goal' => 'sale',
        'channel_scope' => 'all',
        'tone' => 'friendly',
        'methodology' => 'consultative_spin',
    ]);

    $this->contact = Contact::create(['name' => 'Cust', 'phone' => '+100', 'channel' => 'web', 'lifecycle_stage' => 'lead']);

    Http::fake(['api.openai.com/*' => Http::response([
        'choices' => [['message' => ['content' => 'Hi there!'], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 3],
    ], 200)]);
});

afterEach(fn () => Tenancy::clear());

function inbound(Conversation $c, string $body = 'Hello'): Message
{
    return Message::create([
        'conversation_id' => $c->id, 'direction' => 'in', 'author' => 'customer',
        'body' => $body, 'sent_at' => now(),
    ]);
}

function engage(Conversation $c, Message $m): void
{
    (new GenerateAiReply($c->workspace_id, $c->id, $m->id))->handle(app(SalesAgent::class));
}

function aiConv(int $contact, array $attrs = []): Conversation
{
    return Conversation::create(array_merge(
        ['contact_id' => $contact, 'channel' => 'web', 'status' => 'open', 'window_open' => true],
        $attrs,
    ));
}

it('auto-replies to a new inbound message and logs a reply action', function () {
    $c = aiConv($this->contact->id);
    $m = inbound($c);

    engage($c, $m);

    expect(Message::where('conversation_id', $c->id)->where('author', 'bot')->where('status', 'sent')->exists())->toBeTrue();
    expect(AiAction::where('conversation_id', $c->id)->where('type', 'reply')->exists())->toBeTrue();
    expect($c->fresh()->ai_status)->toBe('active');
});

it('does not engage when the plan lacks ai_agents', function () {
    $this->ws->update(['plan' => 'premium']);
    $c = aiConv($this->contact->id);
    $m = inbound($c);

    engage($c, $m);

    expect(Message::where('author', 'bot')->exists())->toBeFalse();
});

it('does not engage when the agent mode is off', function () {
    $this->agent->update(['mode' => 'off']);
    $c = aiConv($this->contact->id);
    $m = inbound($c);

    engage($c, $m);

    expect(Message::where('author', 'bot')->exists())->toBeFalse();
});

it('does not engage when engage_new_conversations is disabled', function () {
    $this->agent->update(['guardrails' => ['engage_new_conversations' => false]]);
    $c = aiConv($this->contact->id);
    $m = inbound($c);

    engage($c, $m);

    expect(Message::where('author', 'bot')->exists())->toBeFalse();
});

it('re-engages after a human resumes the AI, forgiving prior agent messages', function () {
    $c = aiConv($this->contact->id, ['ai_status' => 'active', 'ai_resumed_at' => now()]);
    // A human reply from BEFORE the resume — should be forgiven.
    Message::create(['conversation_id' => $c->id, 'direction' => 'out', 'author' => 'agent', 'body' => 'I had this', 'sent_at' => now()->subMinutes(5)]);
    $m = inbound($c);

    engage($c, $m);

    expect(Message::where('conversation_id', $c->id)->where('author', 'bot')->where('status', 'sent')->exists())->toBeTrue();
    expect($c->fresh()->ai_status)->toBe('active');
});

it('re-suppresses if a human replies again after resuming', function () {
    $c = aiConv($this->contact->id, ['ai_status' => 'active', 'ai_resumed_at' => now()->subMinutes(5)]);
    // A human reply AFTER the resume — fresh takeover.
    Message::create(['conversation_id' => $c->id, 'direction' => 'out', 'author' => 'agent', 'body' => 'taking over', 'sent_at' => now()]);
    $m = inbound($c);

    engage($c, $m);

    expect($c->fresh()->ai_status)->toBe('suppressed');
    expect(Message::where('author', 'bot')->exists())->toBeFalse();
});

it('backs off and suppresses once a human agent has replied', function () {
    $c = aiConv($this->contact->id);
    Message::create(['conversation_id' => $c->id, 'direction' => 'out', 'author' => 'agent', 'body' => 'I got this', 'sent_at' => now()]);
    $m = inbound($c);

    engage($c, $m);

    expect($c->fresh()->ai_status)->toBe('suppressed');
    expect(Message::where('author', 'bot')->exists())->toBeFalse();
});

it('yields to a live chatbot flow covering the channel', function () {
    Chatbot::create(['workspace_id' => $this->ws->id, 'name' => 'Flow', 'status' => 'live', 'channel_scope' => 'all', 'graph' => ['nodes' => []]]);
    $c = aiConv($this->contact->id);
    $m = inbound($c);

    engage($c, $m);

    expect(Message::where('author', 'bot')->exists())->toBeFalse();
});

it('does not re-engage a handed-off conversation', function () {
    $c = aiConv($this->contact->id, ['ai_status' => 'handed_off']);
    $m = inbound($c);

    engage($c, $m);

    expect(Message::where('author', 'bot')->exists())->toBeFalse();
});

it('skips stale jobs that are not the latest inbound message (debounce)', function () {
    $c = aiConv($this->contact->id);
    $stale = inbound($c, 'first');
    inbound($c, 'second'); // newer message arrived

    engage($c, $stale);

    expect(Message::where('author', 'bot')->exists())->toBeFalse();
});

it('hands off when the customer asks for a human', function () {
    $c = aiConv($this->contact->id);
    $m = inbound($c, 'can I talk to a human please');

    engage($c, $m);

    expect($c->fresh()->ai_status)->toBe('handed_off');
    expect(AiAction::where('conversation_id', $c->id)->where('type', 'handoff')->exists())->toBeTrue();
    Http::assertNothingSent(); // deterministic handoff, no LLM
});

it('hands off once the message-count guardrail is reached', function () {
    $this->agent->update(['guardrails' => ['max_messages_per_conversation' => 2]]);
    $c = aiConv($this->contact->id);
    AiAction::create(['conversation_id' => $c->id, 'ai_agent_id' => $this->agent->id, 'type' => 'reply', 'payload' => [], 'status' => 'ok', 'created_at' => now()]);
    AiAction::create(['conversation_id' => $c->id, 'ai_agent_id' => $this->agent->id, 'type' => 'reply', 'payload' => [], 'status' => 'ok', 'created_at' => now()]);
    $m = inbound($c);

    engage($c, $m);

    expect($c->fresh()->ai_status)->toBe('handed_off');
});

it('downgrades to a draft when the 24h window is closed', function () {
    $c = aiConv($this->contact->id, ['window_open' => false]);
    $m = inbound($c);

    engage($c, $m);

    expect(Message::where('conversation_id', $c->id)->where('author', 'bot')->where('status', 'draft')->exists())->toBeTrue();
    expect(Message::where('author', 'bot')->where('status', 'sent')->exists())->toBeFalse();
});
