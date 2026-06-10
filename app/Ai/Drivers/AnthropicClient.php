<?php

namespace App\Ai\Drivers;

use App\Ai\LlmClient;
use App\Ai\LlmResponse;
use App\Ai\ToolCall;
use Illuminate\Support\Facades\Http;

/** Anthropic Messages API driver. */
class AnthropicClient implements LlmClient
{
    public function __construct(
        private string $apiKey,
        private string $model,
        private string $baseUrl = 'https://api.anthropic.com',
    ) {}

    public function chat(array $messages, array $tools = [], array $opts = []): LlmResponse
    {
        $system = [];
        $turns = [];
        foreach ($messages as $m) {
            $role = $m['role'] ?? 'user';
            if ($role === 'system') {
                $system[] = (string) ($m['content'] ?? '');

                continue;
            }
            if ($role === 'tool') {
                // Tool results go in a user turn as tool_result blocks.
                $turns[] = ['role' => 'user', 'content' => [[
                    'type' => 'tool_result',
                    'tool_use_id' => $m['tool_call_id'] ?? '',
                    'content' => (string) ($m['content'] ?? ''),
                ]]];

                continue;
            }
            if (! empty($m['tool_calls'])) {
                $blocks = [];
                if (($m['content'] ?? '') !== '') {
                    $blocks[] = ['type' => 'text', 'text' => (string) $m['content']];
                }
                foreach ($m['tool_calls'] as $c) {
                    $blocks[] = ['type' => 'tool_use', 'id' => $c->id, 'name' => $c->name, 'input' => (object) $c->arguments];
                }
                $turns[] = ['role' => 'assistant', 'content' => $blocks];

                continue;
            }
            $turns[] = ['role' => $role, 'content' => (string) ($m['content'] ?? '')];
        }

        $body = [
            'model' => $this->model,
            'max_tokens' => $opts['max_tokens'] ?? 1024,
            'system' => implode("\n\n", $system),
            'messages' => $turns,
        ];
        if ($tools !== []) {
            $body['tools'] = array_map(fn ($t) => [
                'name' => $t['name'],
                'description' => $t['description'] ?? '',
                'input_schema' => $t['parameters'] ?? ['type' => 'object', 'properties' => (object) []],
            ], $tools);
        }

        $res = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
        ])->timeout((int) config('ai.timeout', 20))
            ->post(rtrim($this->baseUrl, '/').'/v1/messages', $body)
            ->throw();

        $text = '';
        $calls = [];
        foreach ($res->json('content', []) as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'] ?? '';
            } elseif (($block['type'] ?? '') === 'tool_use') {
                $calls[] = new ToolCall($block['id'] ?? uniqid('call_'), $block['name'] ?? '', (array) ($block['input'] ?? []));
            }
        }

        return new LlmResponse(
            text: $text,
            toolCalls: $calls,
            usage: ['in' => (int) $res->json('usage.input_tokens', 0), 'out' => (int) $res->json('usage.output_tokens', 0)],
            stopReason: (string) $res->json('stop_reason', 'end_turn'),
        );
    }
}
