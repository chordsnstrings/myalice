<?php

namespace App\Http\Controllers;

use App\Models\Chatbot;
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
}
