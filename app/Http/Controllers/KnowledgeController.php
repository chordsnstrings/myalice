<?php

namespace App\Http\Controllers;

use App\Ai\Embedder;
use App\Jobs\FetchKnowledgeSource;
use App\Models\AiAgent;
use App\Models\KnowledgeSnippet;
use App\Models\KnowledgeSource;
use App\Support\Knowledge;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Manages an agent's knowledge sources (website / Facebook page / pasted text).
 * Website + Facebook are fetched asynchronously; manual text is chunked inline.
 */
class KnowledgeController extends Controller
{
    public function addSource(Request $request): RedirectResponse
    {
        $ws = Tenancy::currentOrFail();

        $data = $request->validate([
            'scope' => ['required', 'string'],
            'type' => ['required', Rule::in(['website', 'facebook_page', 'manual'])],
            'title' => ['required', 'string', 'max:120'],
            'url' => ['nullable', 'url', 'required_if:type,website'],
            'text' => ['nullable', 'string', 'max:20000', 'required_if:type,manual'],
        ]);

        // 'all' knowledge is shared; a page scope ties it to that page's agent.
        $agentId = $data['scope'] === 'all' ? null : AiAgent::where('channel_scope', $data['scope'])->value('id');

        $source = KnowledgeSource::create([
            'ai_agent_id' => $agentId,
            'type' => $data['type'],
            'url' => $data['url'] ?? null,
            'title' => $data['title'],
            'status' => $data['type'] === 'manual' ? 'fetched' : 'pending',
        ]);

        if ($data['type'] === 'manual') {
            foreach (Knowledge::chunk((string) $data['text'], (int) config('ai.knowledge.chunk_chars', 800)) as $chunk) {
                KnowledgeSnippet::create([
                    'knowledge_source_id' => $source->id, 'title' => $source->title,
                    'content' => $chunk, 'char_count' => mb_strlen($chunk),
                ]);
            }
            app(Embedder::class)->embedSnippets($source);
            $source->update(['last_fetched_at' => now()]);
        } else {
            FetchKnowledgeSource::dispatch($ws->id, $source->id);
        }

        return back()->with('success', 'Knowledge source added.');
    }

    public function refreshSource(KnowledgeSource $source): RedirectResponse
    {
        if ($source->type !== 'manual') {
            $source->update(['status' => 'pending']);
            FetchKnowledgeSource::dispatch(Tenancy::id() ?? 0, $source->id);
        }

        return back()->with('success', 'Refreshing knowledge…');
    }

    public function deleteSource(KnowledgeSource $source): RedirectResponse
    {
        $source->delete();

        return back()->with('success', 'Knowledge source removed.');
    }
}
