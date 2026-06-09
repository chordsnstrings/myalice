<?php

namespace App\Http\Controllers;

use App\Models\Chatbot;
use App\Services\FlowValidator;
use Illuminate\Http\RedirectResponse;
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

    /** Flow builder canvas (B5.2). */
    public function edit(Chatbot $chatbot): Response
    {
        return Inertia::render('Chatbots/Builder', [
            'bot' => [
                'id' => $chatbot->id,
                'name' => $chatbot->name,
                'status' => $chatbot->status,
            ],
        ]);
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
