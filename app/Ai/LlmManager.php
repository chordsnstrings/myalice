<?php

namespace App\Ai;

use App\Ai\Drivers\AnthropicClient;
use App\Ai\Drivers\GeminiClient;
use App\Ai\Drivers\OpenAiClient;
use App\Models\AiProvider;
use RuntimeException;

/**
 * Resolves the workspace's LLM provider(s) and runs a chat with automatic
 * fallback to the next connected provider on failure.
 */
class LlmManager
{
    public function clientFor(AiProvider $provider): LlmClient
    {
        $c = $provider->credentials;
        $key = (string) ($c['api_key'] ?? '');
        $model = (string) ($c['model'] ?? '');
        $base = $c['base_url'] ?? null;

        return match ($provider->type) {
            'anthropic' => new AnthropicClient($key, $model),
            'gemini' => new GeminiClient($key, $model),
            default => $base
                ? new OpenAiClient($key, $model, $base)
                : new OpenAiClient($key, $model),
        };
    }

    /**
     * Chat through the preferred provider, falling back through the rest
     * (ordered by fallback_order) on failure. Tags the response with the
     * provider/model actually used.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @param  array<string, mixed>  $opts
     */
    public function chat(array $messages, array $tools = [], array $opts = [], ?AiProvider $preferred = null): LlmResponse
    {
        $providers = $this->ordered($preferred);
        if ($providers === []) {
            throw new RuntimeException('No AI provider configured.');
        }

        $last = null;
        foreach ($providers as $provider) {
            try {
                $response = $this->clientFor($provider)->chat($messages, $tools, $opts);
                $response->provider = $provider->type;
                $response->model = (string) ($provider->credentials['model'] ?? '');

                return $response;
            } catch (\Throwable $e) {
                $last = $e;
            }
        }

        throw new RuntimeException('All AI providers failed: '.$last->getMessage(), 0, $last);
    }

    /** @return list<AiProvider> */
    private function ordered(?AiProvider $preferred): array
    {
        $all = AiProvider::where('status', 'connected')->orderBy('fallback_order')->get();
        $default = $preferred ?? $all->firstWhere('is_default', true);

        return $all->sortBy(fn (AiProvider $p) => $p->id === $default?->id ? -1 : $p->fallback_order)
            ->values()->all();
    }
}
