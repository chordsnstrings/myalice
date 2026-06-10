<?php

namespace App\Jobs;

use App\Models\MessageTemplate;
use App\Models\Workspace;
use App\Support\Tenancy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Applies async WhatsApp template approval/rejection/pause updates from Meta
 * webhooks onto local templates.
 *
 * @phpstan-type Update array{meta_template_id?: string, name?: string, language?: string, event: string, reason?: string|null}
 */
class ApplyTemplateStatus implements ShouldQueue
{
    use Queueable;

    private const EVENT_MAP = [
        'APPROVED' => 'approved',
        'REJECTED' => 'rejected',
        'PAUSED' => 'paused',
        'DISABLED' => 'disabled',
        'PENDING' => 'pending',
        'FLAGGED' => 'paused',
    ];

    /**
     * @param  array<int, Update>  $updates
     */
    public function __construct(
        public int $workspaceId,
        public array $updates,
    ) {}

    public function handle(): void
    {
        $workspace = Workspace::find($this->workspaceId);
        if (! $workspace) {
            return;
        }

        Tenancy::set($workspace);

        try {
            foreach ($this->updates as $update) {
                $template = MessageTemplate::query()
                    ->when(! empty($update['meta_template_id']), fn ($q) => $q->where('meta_template_id', $update['meta_template_id']))
                    ->when(empty($update['meta_template_id']), fn ($q) => $q
                        ->where('name', $update['name'] ?? '')
                        ->where('language', $update['language'] ?? ''))
                    ->first();

                if (! $template) {
                    continue;
                }

                $status = self::EVENT_MAP[strtoupper($update['event'])] ?? $template->approval_status;
                $template->update([
                    'approval_status' => $status,
                    'rejection_reason' => $status === 'rejected' ? ($update['reason'] ?? null) : $template->rejection_reason,
                ]);
            }
        } finally {
            Tenancy::clear();
        }
    }
}
