<?php

use App\Ai\Drivers\AnthropicClient;
use App\Ai\Drivers\GeminiClient;
use App\Ai\Drivers\OpenAiClient;
use Illuminate\Support\Facades\Http;

$messages = [
    ['role' => 'system', 'content' => 'You are a bot.'],
    ['role' => 'user', 'content' => 'I want a mug'],
];
$tools = [['name' => 'create_lead', 'description' => 'Mark a lead', 'parameters' => ['type' => 'object', 'properties' => (object) []]]];

it('OpenAI driver sends correct shape and parses text + tool calls', function () use ($messages, $tools) {
    Http::fake(['api.openai.com/*' => Http::response([
        'choices' => [['message' => ['content' => 'Sure!', 'tool_calls' => [
            ['id' => 'call_1', 'function' => ['name' => 'create_lead', 'arguments' => '{"interest":"mug"}']],
        ]], 'finish_reason' => 'tool_calls']],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
    ], 200)]);

    $r = (new OpenAiClient('sk-test', 'gpt-4.1-mini'))->chat($messages, $tools);

    expect($r->text)->toBe('Sure!');
    expect($r->toolCalls)->toHaveCount(1);
    expect($r->toolCalls[0]->name)->toBe('create_lead');
    expect($r->toolCalls[0]->arguments)->toBe(['interest' => 'mug']);
    expect($r->usage)->toBe(['in' => 10, 'out' => 5]);

    Http::assertSent(fn ($req) => str_contains($req->url(), '/chat/completions')
        && $req['model'] === 'gpt-4.1-mini'
        && $req['tools'][0]['function']['name'] === 'create_lead'
        && $req->hasHeader('Authorization', 'Bearer sk-test'));
});

it('OpenAI driver honours a custom base_url (DeepSeek/Ollama)', function () use ($messages) {
    Http::fake(['api.deepseek.com/*' => Http::response(['choices' => [['message' => ['content' => 'hi']]]], 200)]);
    (new OpenAiClient('sk', 'deepseek-chat', 'https://api.deepseek.com'))->chat($messages);
    Http::assertSent(fn ($req) => str_contains($req->url(), 'api.deepseek.com/chat/completions'));
});

it('Anthropic driver maps system + tools and parses tool_use blocks', function () use ($messages, $tools) {
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [
            ['type' => 'text', 'text' => 'On it.'],
            ['type' => 'tool_use', 'id' => 'tu_1', 'name' => 'create_lead', 'input' => ['interest' => 'mug']],
        ],
        'usage' => ['input_tokens' => 8, 'output_tokens' => 4],
        'stop_reason' => 'tool_use',
    ], 200)]);

    $r = (new AnthropicClient('ak-test', 'claude-sonnet-4-5'))->chat($messages, $tools);

    expect($r->text)->toBe('On it.');
    expect($r->toolCalls[0]->name)->toBe('create_lead');
    expect($r->toolCalls[0]->arguments)->toBe(['interest' => 'mug']);

    Http::assertSent(fn ($req) => str_contains($req->url(), '/v1/messages')
        && $req['system'] === 'You are a bot.'
        && $req['tools'][0]['input_schema']['type'] === 'object'
        && $req->hasHeader('x-api-key', 'ak-test')
        && $req->hasHeader('anthropic-version', '2023-06-01'));
});

it('Gemini driver maps system_instruction and parses functionCall', function () use ($messages, $tools) {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
        'candidates' => [['content' => ['parts' => [
            ['text' => 'Sure'],
            ['functionCall' => ['name' => 'create_lead', 'args' => ['interest' => 'mug']]],
        ]], 'finishReason' => 'STOP']],
        'usageMetadata' => ['promptTokenCount' => 7, 'candidatesTokenCount' => 3],
    ], 200)]);

    $r = (new GeminiClient('gk-test', 'gemini-2.0-flash'))->chat($messages, $tools);

    expect($r->text)->toBe('Sure');
    expect($r->toolCalls[0]->name)->toBe('create_lead');

    Http::assertSent(fn ($req) => str_contains($req->url(), 'gemini-2.0-flash:generateContent')
        && $req['system_instruction']['parts'][0]['text'] === 'You are a bot.'
        && $req['tools'][0]['function_declarations'][0]['name'] === 'create_lead'
        && $req->hasHeader('x-goog-api-key', 'gk-test'));
});

it('throws on a non-2xx response (so the manager can fall back)', function () use ($messages) {
    Http::fake(['api.openai.com/*' => Http::response(['error' => 'nope'], 500)]);
    (new OpenAiClient('sk', 'gpt-4.1-mini'))->chat($messages);
})->throws(Exception::class);
