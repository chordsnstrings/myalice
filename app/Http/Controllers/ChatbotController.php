<?php

namespace App\Http\Controllers;

use App\Models\Chatbot;
use App\Services\FlowValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class ChatbotController extends Controller
{
    /** Bot list (B5.1). */
    public function index(): Response
    {
        $bots = Chatbot::latest()->get()->map(fn (Chatbot $b) => [
            'id' => $b->id,
            'name' => $b->name,
            'channel_scope' => $b->channel_scope,
            'status' => $b->status,
            'version' => $b->version,
        ]);

        return Inertia::render('Chatbots/Index', ['bots' => $bots]);
    }

    /** Flow builder canvas (B5.2) — loads the persisted graph + live validation. */
    public function edit(Chatbot $chatbot, FlowValidator $validator): Response
    {
        $graph = $this->normalize($chatbot->graph);

        return Inertia::render('Chatbots/Builder', [
            'bot' => [
                'id' => $chatbot->id,
                'name' => $chatbot->name,
                'status' => $chatbot->status,
                'version' => $chatbot->version,
            ],
            'graph' => $graph,
            'issues' => $validator->validate($graph),
        ]);
    }

    /** Persist the edited flow graph (autosave) and return live validation. */
    public function update(Request $request, Chatbot $chatbot, FlowValidator $validator): JsonResponse
    {
        $check = Validator::make($request->all(), [
            'graph' => ['required', 'array'],
            'graph.nodes' => ['present', 'array'],
            'graph.nodes.*.id' => ['required', 'string', 'max:60'],
            'graph.nodes.*.type' => ['required', 'string', 'max:30'],
            'graph.nodes.*.label' => ['nullable', 'string', 'max:120'],
            'graph.nodes.*.text' => ['nullable', 'string', 'max:2000'],
            'graph.nodes.*.x' => ['nullable', 'numeric'],
            'graph.nodes.*.y' => ['nullable', 'numeric'],
            'graph.nodes.*.next' => ['nullable', 'string', 'max:60'],
            'graph.nodes.*.fallback' => ['nullable', 'string', 'max:60'],
        ]);

        if ($check->fails()) {
            return response()->json(['errors' => $check->errors()], 422);
        }

        /** @var array{nodes: array<int, array<string, mixed>>} $graph */
        $graph = $check->validated()['graph'];
        $chatbot->update(['graph' => $graph]);

        return response()->json(['ok' => true, 'issues' => $validator->validate($graph)]);
    }

    /**
     * Ensure every node carries the visual keys the canvas needs (label / x / y /
     * next), back-filling older graphs so they render and stay editable.
     *
     * @param  array<string, mixed>|null  $graph
     * @return array{nodes: array<int, array<string, mixed>>}
     */
    private function normalize(?array $graph): array
    {
        $graph ??= [];
        /** @var array<int, array<string, mixed>> $nodes */
        $nodes = $graph['nodes'] ?? [['id' => 'start', 'type' => 'start']];

        $out = [];
        foreach (array_values($nodes) as $i => $n) {
            $id = (string) ($n['id'] ?? 'n'.$i);
            $out[] = [
                'id' => $id,
                'type' => (string) ($n['type'] ?? 'message'),
                'label' => (string) ($n['label'] ?? ucfirst(str_replace('_', ' ', $id))),
                'text' => isset($n['text']) ? (string) $n['text'] : null,
                'x' => isset($n['x']) ? (float) $n['x'] : 40.0,
                'y' => isset($n['y']) ? (float) $n['y'] : 40.0 + $i * 110,
                'next' => isset($n['next']) ? (string) $n['next'] : null,
                'fallback' => isset($n['fallback']) ? (string) $n['fallback'] : null,
            ];
        }

        return ['nodes' => $out];
    }

    /**
     * Publish a bot — blocked while the flow has validation errors
     * (dead ends, missing fallbacks; C-10). Publish swaps atomically.
     */
    public function publish(Chatbot $chatbot, FlowValidator $validator): RedirectResponse
    {
        $graph = $chatbot->graph ?? [];

        if (! $validator->canPublish($graph)) {
            $issues = collect($validator->validate($graph))
                ->where('severity', 'error')
                ->pluck('message')
                ->implode(' ');

            return back()->withErrors(['flow' => "Can't publish: {$issues}"]);
        }

        $chatbot->update(['status' => 'live', 'version' => $chatbot->version + 1]);

        return back()->with('success', 'Bot published — changes are live.');
    }
}
