<?php

namespace App\Ai\Drivers;

use App\Ai\LlmClient;
use App\Ai\LlmResponse;
use App\Ai\ToolCall;
use Illuminate\Support\Facades\Http;

/** Google Gemini generateContent driver. */
class GeminiClient implements LlmClient
{
    public function __construct(
        private string $apiKey,
        private string $model,
        private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta',
    ) {}

    public function chat(array $messages, array $tools = [], array $opts = []): LlmResponse
    {
        $system = [];
        $contents = [];
        foreach ($messages as $m) {
            $role = $m['role'] ?? 'user';
            if ($role === 'system') {
                $system[] = (string) ($m['content'] ?? '');

                continue;
            }
            if ($role === 'tool') {
                $contents[] = ['role' => 'user', 'parts' => [[
                    'functionResponse' => ['name' => $m['name'] ?? 'tool', 'response' => ['result' => (string) ($m['content'] ?? '')]],
                ]]];

                continue;
            }
            if (! empty($m['tool_calls'])) {
                $parts = [];
                foreach ($m['tool_calls'] as $c) {
                    $parts[] = ['functionCall' => ['name' => $c->name, 'args' => (object) $c->arguments]];
                }
                $contents[] = ['role' => 'model', 'parts' => $parts];

                continue;
            }
            $contents[] = ['role' => $role === 'assistant' ? 'model' : 'user', 'parts' => [['text' => (string) ($m['content'] ?? '')]]];
        }

        $body = ['contents' => $contents];
        if ($system !== []) {
            $body['system_instruction'] = ['parts' => [['text' => implode("\n\n", $system)]]];
        }
        if ($tools !== []) {
            $body['tools'] = [['function_declarations' => array_map(fn ($t) => [
                'name' => $t['name'],
                'description' => $t['description'] ?? '',
                'parameters' => $t['parameters'] ?? ['type' => 'object', 'properties' => (object) []],
            ], $tools)]];
        }

        $res = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
            ->timeout((int) config('ai.timeout', 20))
            ->post(rtrim($this->baseUrl, '/')."/models/{$this->model}:generateContent", $body)
            ->throw();

        $text = '';
        $calls = [];
        foreach ($res->json('candidates.0.content.parts', []) as $part) {
            if (isset($part['text'])) {
                $text .= $part['text'];
            } elseif (isset($part['functionCall'])) {
                $calls[] = new ToolCall(
                    (string) ($part['functionCall']['name'] ?? uniqid('call_')),
                    (string) ($part['functionCall']['name'] ?? ''),
                    (array) ($part['functionCall']['args'] ?? []),
                );
            }
        }

        return new LlmResponse(
            text: $text,
            toolCalls: $calls,
            usage: ['in' => (int) $res->json('usageMetadata.promptTokenCount', 0), 'out' => (int) $res->json('usageMetadata.candidatesTokenCount', 0)],
            stopReason: (string) $res->json('candidates.0.finishReason', 'STOP'),
        );
    }
}
