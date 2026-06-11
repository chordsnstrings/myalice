<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\JsonResponse;

/**
 * Lightweight "needs attention" feed for the top-bar bell: open conversations
 * that are unassigned or breaching SLA, newest first. Workspace-scoped via the
 * Conversation global scope.
 */
class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $items = Conversation::with('contact')
            ->where('status', '!=', 'resolved')
            ->where(fn ($q) => $q->whereNull('assignee_id')->orWhere('sla_breaching', true))
            ->latest('last_message_at')
            ->limit(8)
            ->get()
            ->map(fn (Conversation $c) => [
                'id' => $c->id,
                'title' => $c->contact->name,
                'text' => $c->sla_breaching ? 'SLA breaching' : 'Waiting — unassigned',
                'tone' => $c->sla_breaching ? 'warning' : 'info',
                'channel' => $c->channel,
                'at' => $c->last_message_at,
            ]);

        return response()->json(['items' => $items, 'count' => $items->count()]);
    }
}
