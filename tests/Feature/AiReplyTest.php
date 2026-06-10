<?php

use App\Ai\SalesAgent;
use App\Ai\ToolExecutor;
use App\Models\AiAction;
use App\Models\AiAgent;
use App\Models\AiProvider;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->ws = Workspace::create(['name' => 'Reply WS', 'plan' => 'business']);
    Tenancy::set($this->ws);

    $this->contact = Contact::create(['name' => 'Cust', 'phone' => '+200', 'channel' => 'web', 'lifecycle_stage' => 'lead']);
    $this->conv = Conversation::create(['contact_id' => $this->contact->id, 'channel' => 'web', 'status' => 'open', 'window_open' => true]);
    Message::create(['conversation_id' => $this->conv->id, 'direction' => 'in', 'author' => 'customer', 'body' => 'Hi', 'sent_at' => now()]);
});

afterEach(fn () => Tenancy::clear());

function makeProvider(Workspace $ws, string $type, array $creds, int $order = 0, bool $default = false): AiProvider
{
    return AiProvider::create([
        'workspace_id' => $ws->id, 'type' => $type, 'name' => ucfirst($type),
        'credentials' => $creds, 'status' => 'connected', 'is_default' => $default, 'fallback_order' => $order,
    ]);
}

function makeAgent(Workspace $ws, string $mode): AiAgent
{
    return AiAgent::create([
        'workspace_id' => $ws->id, 'name' => 'A', 'enabled' => true, 'mode' => $mode,
        'goal' => 'sale', 'channel_scope' => 'all', 'tone' => 'friendly', 'methodology' => 'consultative_spin',
    ]);
}

it('auto mode sends a bot reply and logs token usage', function () {
    makeProvider($this->ws, 'openai', ['api_key' => 'sk', 'model' => 'gpt-4.1-mini'], 0, true);
    Http::fake(['api.openai.com/*' => Http::response([
        'choices' => [['message' => ['content' => 'Sure, the blue mug is $12.'], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 11, 'completion_tokens' => 7],
    ], 200)]);

    app(SalesAgent::class)->run(makeAgent($this->ws, 'auto'), $this->conv);

    $reply = AiAction::where('type', 'reply')->first();
    expect($reply)->not->toBeNull();
    expect($reply->tokens_in)->toBe(11);
    expect($reply->tokens_out)->toBe(7);
    expect(Message::where('author', 'bot')->where('status', 'sent')->value('body'))->toBe('Sure, the blue mug is $12.');
});

it('suggest mode stores a draft and sends nothing', function () {
    makeProvider($this->ws, 'openai', ['api_key' => 'sk', 'model' => 'gpt-4.1-mini'], 0, true);
    Http::fake(['api.openai.com/*' => Http::response([
        'choices' => [['message' => ['content' => 'Draft reply'], 'finish_reason' => 'stop']],
    ], 200)]);

    app(SalesAgent::class)->run(makeAgent($this->ws, 'suggest'), $this->conv);

    expect(Message::where('author', 'bot')->where('status', 'draft')->exists())->toBeTrue();
    expect(Message::where('author', 'bot')->where('status', 'sent')->exists())->toBeFalse();
    expect(AiAction::where('type', 'draft')->exists())->toBeTrue();
});

it('falls back to the next provider when the default fails', function () {
    makeProvider($this->ws, 'openai', ['api_key' => 'sk', 'model' => 'gpt-4.1-mini'], 0, true);
    makeProvider($this->ws, 'anthropic', ['api_key' => 'ak', 'model' => 'claude-sonnet-4-5'], 1, false);

    Http::fake([
        'api.openai.com/*' => Http::response(['error' => 'down'], 500),
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'Backup here']],
            'usage' => ['input_tokens' => 4, 'output_tokens' => 2],
        ], 200),
    ]);

    app(SalesAgent::class)->run(makeAgent($this->ws, 'auto'), $this->conv);

    $reply = AiAction::where('type', 'reply')->first();
    expect($reply->payload['provider'])->toBe('anthropic');
    expect(Message::where('author', 'bot')->where('status', 'sent')->value('body'))->toBe('Backup here');
});

it('logs an error and stays silent when every provider fails', function () {
    makeProvider($this->ws, 'openai', ['api_key' => 'sk', 'model' => 'gpt-4.1-mini'], 0, true);
    Http::fake(['api.openai.com/*' => Http::response(['error' => 'down'], 500)]);

    app(SalesAgent::class)->run(makeAgent($this->ws, 'auto'), $this->conv);

    expect(AiAction::where('type', 'error')->where('status', 'failed')->exists())->toBeTrue();
    expect(Message::where('author', 'bot')->exists())->toBeFalse();
});

it('exposes order tools only in autopilot mode', function () {
    $executor = app(ToolExecutor::class);
    $agent = makeAgent($this->ws, 'auto');

    $autoNames = collect($executor->definitions($agent))->pluck('name');
    $agent->mode = 'autopilot';
    $pilotNames = collect($executor->definitions($agent))->pluck('name');

    expect($autoNames)->not->toContain('create_order');
    expect($autoNames)->toContain('create_lead', 'handoff_to_human');
    expect($pilotNames)->toContain('create_order', 'send_payment_link');
});
