<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;

/**
 * Smart ticket assignment (M5). Load-balanced: routes a conversation to the
 * eligible agent with the fewest currently-open conversations (ties broken by
 * id for determinism). Returns null when no agent is available.
 */
class RoutingService
{
    /** Roles that work the inbox (§4.3). */
    private const WORKING_ROLES = ['owner', 'manager', 'agent'];

    public function assign(Conversation $conversation): ?User
    {
        $agent = $this->pickAgent($conversation->workspace_id);

        if ($agent) {
            $conversation->update(['assignee_id' => $agent->id]);
        }

        return $agent;
    }

    private function pickAgent(int $workspaceId): ?User
    {
        $agents = User::where('workspace_id', $workspaceId)
            ->whereIn('workspace_role', self::WORKING_ROLES)
            ->get();

        if ($agents->isEmpty()) {
            return null;
        }

        $load = Conversation::query()
            ->where('workspace_id', $workspaceId)
            ->where('status', 'open')
            ->whereNotNull('assignee_id')
            ->selectRaw('assignee_id, count(*) as c')
            ->groupBy('assignee_id')
            ->pluck('c', 'assignee_id');

        // Sort by load ascending, then id for determinism.
        return $agents
            ->sortBy(fn (User $a) => ((int) ($load[$a->id] ?? 0) * 1_000_000) + $a->id)
            ->first();
    }
}
