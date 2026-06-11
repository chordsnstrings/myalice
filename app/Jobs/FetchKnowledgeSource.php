<?php

namespace App\Jobs;

use App\Ai\Embedder;
use App\Models\Channel;
use App\Models\KnowledgeSnippet;
use App\Models\KnowledgeSource;
use App\Models\Workspace;
use App\Support\Knowledge;
use App\Support\Tenancy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Fetches a knowledge source into plain-text snippets (replacing prior snippets).
 * Website = HTTP GET + strip HTML; Facebook = Page Graph API; manual = no fetch
 * (its text was stored when created). SiteGround-safe: one source per job, short
 * timeout, capped snippet count.
 */
class FetchKnowledgeSource implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $workspaceId, public int $sourceId) {}

    public function handle(): void
    {
        $workspace = Workspace::find($this->workspaceId);
        if (! $workspace) {
            return;
        }

        Tenancy::set($workspace);

        try {
            $source = KnowledgeSource::find($this->sourceId);
            if (! $source || $source->type === 'manual') {
                return; // manual text is stored at creation time
            }

            try {
                $text = $source->type === 'facebook_page'
                    ? $this->fetchFacebookPage()
                    : $this->fetchWebsite((string) $source->url);

                $this->replaceSnippets($source, $text);
                app(Embedder::class)->embedSnippets($source);
                $source->update(['status' => 'fetched', 'last_fetched_at' => now(), 'error' => null]);
            } catch (Throwable $e) {
                $source->update(['status' => 'error', 'error' => mb_substr($e->getMessage(), 0, 250)]);
            }
        } finally {
            Tenancy::clear();
        }
    }

    private function fetchWebsite(string $url): string
    {
        $html = Http::timeout((int) config('ai.knowledge.fetch_timeout', 10))
            ->get($url)->throw()->body();

        // Drop script/style blocks, then strip tags and collapse whitespace.
        $clean = (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html);
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($clean)));

        if ($text === '') {
            throw new \RuntimeException('No readable text at that URL.');
        }

        return $text;
    }

    private function fetchFacebookPage(): string
    {
        $token = Channel::where('type', 'messenger')->first()?->credentials['page_token']
            ?? config('services.messenger.page_token');

        if (blank($token)) {
            throw new \RuntimeException('No connected Facebook Page token.');
        }

        $info = Http::timeout((int) config('ai.knowledge.fetch_timeout', 10))
            ->get('https://graph.facebook.com/v21.0/me', [
                'fields' => 'name,about,description,website,emails,phone',
                'access_token' => $token,
            ])->throw()->json();

        $parts = array_filter([
            $info['name'] ?? null,
            $info['about'] ?? null,
            $info['description'] ?? null,
            isset($info['website']) ? 'Website: '.$info['website'] : null,
        ]);

        return trim(implode("\n", $parts));
    }

    private function replaceSnippets(KnowledgeSource $source, string $text): void
    {
        $source->snippets()->delete();

        $size = (int) config('ai.knowledge.chunk_chars', 800);
        $max = (int) config('ai.knowledge.max_snippets_per_source', 40);

        foreach (array_slice(Knowledge::chunk($text, $size), 0, $max) as $chunk) {
            KnowledgeSnippet::create([
                'knowledge_source_id' => $source->id,
                'title' => $source->title,
                'content' => $chunk,
                'char_count' => mb_strlen($chunk),
            ]);
        }
    }
}
