<?php

namespace App\Ai;

use App\Models\AiProvider;
use App\Models\KnowledgeSource;
use Illuminate\Support\Facades\Http;

/**
 * Turns text into embedding vectors using the workspace's connected provider, so
 * knowledge retrieval can rank by semantic similarity. Mirrors LlmManager: it
 * reuses the existing AiProvider credentials rather than a separate account.
 *
 * Embeddings are best-effort: any failure (no capable provider, HTTP error)
 * returns null so callers fall back to keyword-only retrieval and never break a
 * reply. Anthropic has no first-party embeddings API and is skipped.
 */
class Embedder
{
    /** Provider types that expose an embeddings endpoint we support. */
    private const CAPABLE = ['openai', 'openai_compatible', 'gemini'];

    public function available(): bool
    {
        return $this->provider() !== null;
    }

    /**
     * Embed a single string. Null when unavailable or on failure.
     *
     * @return list<float>|null
     */
    public function embedOne(string $text): ?array
    {
        $vectors = $this->embed([$text]);

        return $vectors[0] ?? null;
    }

    /**
     * Batch-embed texts, preserving order. Null when unavailable or on failure.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>|null
     */
    public function embed(array $texts): ?array
    {
        if ($texts === [] || ! config('ai.knowledge.semantic', true)) {
            return null;
        }

        $provider = $this->provider();
        if (! $provider) {
            return null;
        }

        try {
            return $provider->type === 'gemini'
                ? $this->embedGemini($provider, $texts)
                : $this->embedOpenAi($provider, $texts);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Embed and persist vectors onto a source's snippets (best-effort, in order). */
    public function embedSnippets(KnowledgeSource $source): void
    {
        $snippets = $source->snippets()->orderBy('id')->get();
        if ($snippets->isEmpty()) {
            return;
        }

        $vectors = $this->embed($snippets->pluck('content')->all());
        if ($vectors === null) {
            return;
        }

        $model = $this->modelFor($this->provider());
        foreach ($snippets as $i => $snippet) {
            if (isset($vectors[$i])) {
                $snippet->forceFill(['embedding' => $vectors[$i], 'embedding_model' => $model])->save();
            }
        }
    }

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    private function embedOpenAi(AiProvider $provider, array $texts): array
    {
        $base = $provider->credentials['base_url'] ?? 'https://api.openai.com/v1';

        $res = Http::withToken((string) ($provider->credentials['api_key'] ?? ''))
            ->timeout((int) config('ai.knowledge.embed_timeout', 15))
            ->post(rtrim((string) $base, '/').'/embeddings', [
                'model' => $this->modelFor($provider),
                'input' => $texts,
            ])
            ->throw();

        $out = [];
        foreach ((array) $res->json('data', []) as $row) {
            $out[] = array_map('floatval', (array) ($row['embedding'] ?? []));
        }

        return $out;
    }

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    private function embedGemini(AiProvider $provider, array $texts): array
    {
        $base = $provider->credentials['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta';
        $model = $this->modelFor($provider);

        $res = Http::withHeaders(['x-goog-api-key' => (string) ($provider->credentials['api_key'] ?? '')])
            ->timeout((int) config('ai.knowledge.embed_timeout', 15))
            ->post(rtrim((string) $base, '/')."/models/{$model}:batchEmbedContents", [
                'requests' => array_map(fn ($t) => [
                    'model' => 'models/'.$model,
                    'content' => ['parts' => [['text' => $t]]],
                ], $texts),
            ])
            ->throw();

        $out = [];
        foreach ((array) $res->json('embeddings', []) as $row) {
            $out[] = array_map('floatval', (array) ($row['values'] ?? []));
        }

        return $out;
    }

    private function modelFor(?AiProvider $provider): string
    {
        if (! $provider) {
            return (string) config('ai.knowledge.embedding_models.openai', 'text-embedding-3-small');
        }

        $configured = (string) ($provider->credentials['embedding_model'] ?? '');

        return $configured !== ''
            ? $configured
            : (string) config("ai.knowledge.embedding_models.{$provider->type}", 'text-embedding-3-small');
    }

    /** The preferred embeddings-capable connected provider (default first). */
    private function provider(): ?AiProvider
    {
        return AiProvider::where('status', 'connected')
            ->whereIn('type', self::CAPABLE)
            ->orderByDesc('is_default')
            ->orderBy('fallback_order')
            ->first();
    }
}
