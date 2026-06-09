<?php

namespace App\Observers;

use App\Jobs\SendCsatSurvey;
use App\Models\Conversation;
use App\Models\Workspace;

class ConversationObserver
{
    /** Keep lifecycle stamps consistent for directly-created rows (seeder/import). */
    public function creating(Conversation $conversation): void
    {
        if ($conversation->status === 'resolved' && $conversation->resolved_at === null) {
            $conversation->resolved_at = now();
        }

        if ($conversation->assignee_id && $conversation->assigned_at === null) {
            $conversation->assigned_at = now();
        }
    }

    /** Stamp resolution/assignment transitions. */
    public function updating(Conversation $conversation): void
    {
        if ($conversation->isDirty('status')) {
            $becameResolved = $conversation->status === 'resolved'
                && $conversation->getOriginal('status') !== 'resolved';

            if ($becameResolved && $conversation->resolved_at === null) {
                $conversation->resolved_at = now();
            }

            // Re-opened: clear resolution + any pending survey flag.
            if ($conversation->status !== 'resolved' && $conversation->getOriginal('status') === 'resolved') {
                $conversation->resolved_at = null;
                $conversation->awaiting_csat_at = null;
            }
        }

        if ($conversation->isDirty('assignee_id') && $conversation->assignee_id && $conversation->assigned_at === null) {
            $conversation->assigned_at = now();
        }
    }

    /** After a resolve transition, queue the CSAT survey (if enabled). */
    public function updated(Conversation $conversation): void
    {
        if (! $conversation->wasChanged('status') || $conversation->status !== 'resolved') {
            return;
        }

        $workspace = Workspace::find($conversation->workspace_id);

        if ($workspace && $workspace->csat_enabled) {
            SendCsatSurvey::dispatch($conversation->id);
        }
    }
}
