<?php

namespace App\Jobs;

use App\Ai\SalesAgent;
use App\Models\AiAgent;
use App\Models\Conversation;
use App\Models\Workspace;
use App\Support\Plans;
use App\Support\Tenancy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs the SalesAgent's re-engagement on one stalled conversation. tries=1 — a
 * retry could double-message. Eligibility was filtered by the SendReengagements
 * command; here we only re-check the time-sensitive dynamic guards, since state
 * may have changed between the scan and this run.
 */
class SendAiReengagement implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public int $workspaceId,
        public int $conversationId,
    ) {}

    public function handle(SalesAgent $agent): void
    {
        $workspace = Workspace::find($this->workspaceId);
        if (! $workspace || ! Plans::includes($workspace->plan, 'ai_agents')) {
            return;
        }

        Tenancy::set($workspace);

        try {
            $conversation = Conversation::with('contact')->find($this->conversationId);
            if (! $conversation) {
                return;
            }

            // Re-validate dynamic guards (a human may have replied, or it may have
            // been resolved/handed off since the scan queued this job).
            if ($conversation->status === 'resolved' || ! $conversation->window_open) {
                return;
            }
            if (in_array($conversation->ai_status, ['handed_off', 'suppressed'], true)) {
                return;
            }
            if ($conversation->messages()->where('author', 'agent')->exists()) {
                return;
            }

            $config = AiAgent::resolveFor($conversation->channel);
            if (! $config || ! $config->enabled || ! in_array($config->mode, ['auto', 'autopilot'], true)) {
                return;
            }

            $agent->reengage($config, $conversation);
        } finally {
            Tenancy::clear();
        }
    }
}
