<?php

namespace App\Ai\Drivers;

use App\Ai\LlmClient;
use App\Ai\LlmResponse;
use App\Ai\ToolCall;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI Chat Completions driver. Also serves any OpenAI-compatible endpoint
 * (DeepSeek, Ollama/vLLM, Groq, Together, OpenRouter) via a custom base_url.
 */
class OpenAiClient implements LlmClient
{
    public function __construct(
        private string $apiKey,
        private string $model,
        private string $baseUrl = 'https://api.openai.com/v1',
    ) {}

    public function chat(array $messages, array $tools = [], array $opts = []): LlmResponse
    {
        $body = [
            'model' => $this->model,
            'messages' => array_map([$this, 'mapMessage'], $messages),
            'temperature' => $opts['temperature'] ?? 0.4,
        ];
        if ($tools !== []) {
            $body['tools'] = array_map(fn ($t) => [
                'type' => 'function',
                'function' => ['name' => $t['name'], 'description' => $t['description'] ?? '', 'parameters' => $t['parameters'] ?? ['type' => 'object', 'properties' => (object) []]],
            ], $tools);
        }

        $res = Http::withToken($this->apiKey)
            ->timeout((int) config('ai.timeout', 20))
            ->post(rtrim($this->baseUrl, '/').'/chat/completions', $body)
            ->throw();

        $msg = $res->json('choices.0.message', []);
        $calls = [];
        foreach ($msg['tool_calls'] ?? [] as $tc) {
            $args = json_decode($tc['function']['arguments'] ?? '{}', true);
            $calls[] = new ToolCall($tc['id'] ?? uniqid('call_'), $tc['function']['name'] ?? '', is_array($args) ? $args : []);
        }

        return new LlmResponse(
            text: (string) ($msg['content'] ?? ''),
            toolCalls: $calls,
            usage: ['in' => (int) $res->json('usage.prompt_tokens', 0), 'out' => (int) $res->json('usage.completion_tokens', 0)],
            stopReason: (string) $res->json('choices.0.finish_reason', 'stop'),
        );
    }

    /**
     * @param  array<string, mixed>  $m
     * @return array<string, mixed>
     */
    private function mapMessage(array $m): array
    {
        if (($m['role'] ?? '') === 'tool') {
            return ['role' => 'tool', 'tool_call_id' => $m['tool_call_id'] ?? '', 'content' => (string) ($m['content'] ?? '')];
        }

        $out = ['role' => $m['role'], 'content' => (string) ($m['content'] ?? '')];

        if (! empty($m['tool_calls'])) {
            $out['tool_calls'] = array_map(fn (ToolCall $c) => [
                'id' => $c->id,
                'type' => 'function',
                'function' => ['name' => $c->name, 'arguments' => json_encode($c->arguments)],
            ], $m['tool_calls']);
        }

        return $out;
    }
}
