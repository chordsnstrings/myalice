<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    /**
     * The unified inbox (B3). Tenant-scoped conversations + messages render the
     * 3-pane workspace. Live channel ingestion lands in Phase 3.
     */
    public function index(): Response
    {
        $conversations = Conversation::with('contact')
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn (Conversation $c) => [
                'id' => $c->id,
                'contact' => [
                    'id' => $c->contact->id,
                    'name' => $c->contact->name,
                    'channel' => $c->contact->channel,
                    'lifecycle' => $c->contact->lifecycle_stage,
                ],
                'last_message' => $c->last_message,
                'last_message_at' => optional($c->last_message_at)->toIso8601String(),
                'unread' => $c->unread,
                'channel' => $c->channel,
                'status' => $c->status,
                'assignee' => $c->assignee_id ? ['id' => $c->assignee_id, 'name' => 'You'] : null,
                'sla_breaching' => $c->sla_breaching,
                'window_open' => $c->window_open,
                'ai_status' => $c->ai_status,
            ])->values();

        $messages = Message::orderBy('sent_at')
            ->get()
            ->groupBy('conversation_id')
            ->map(fn ($group) => $group->map(fn (Message $m) => [
                'id' => $m->id,
                'direction' => $m->direction,
                'author' => $m->author,
                'body' => $m->body,
                'sent_at' => $m->sent_at->toIso8601String(),
                'status' => $m->status,
            ])->values()->all());

        return Inertia::render('Inbox/Index', [
            'conversations' => $conversations,
            'messages' => $messages,
        ]);
    }
}
