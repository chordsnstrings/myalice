<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;

class ConversationApiController extends Controller
{
    /** Workspace-scoped conversations (M19). */
    public function index(): JsonResponse
    {
        $conversations = Conversation::with('contact')
            ->orderByDesc('last_message_at')
            ->paginate(50)
            ->through(fn (Conversation $c) => [
                'id' => $c->id,
                'contact' => $c->contact->name,
                'channel' => $c->channel,
                'status' => $c->status,
                'last_message' => $c->last_message,
                'last_message_at' => optional($c->last_message_at)->toIso8601String(),
            ]);

        return response()->json($conversations);
    }
}
