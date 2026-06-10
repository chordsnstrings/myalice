<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Services\WhatsAppTemplateService;
use App\Support\Tenancy;
use Illuminate\Console\Command;

/**
 * Pull WhatsApp template statuses from Meta for every workspace with a connected
 * WABA (approvals/rejections/pauses happen asynchronously on Meta's side).
 */
class SyncTemplates extends Command
{
    protected $signature = 'templates:sync';

    protected $description = 'Sync WhatsApp template approval statuses from Meta';

    public function handle(WhatsAppTemplateService $service): int
    {
        $total = 0;

        foreach (Workspace::all() as $workspace) {
            Tenancy::set($workspace);
            if ($service->configured()) {
                $total += $service->sync();
            }
            Tenancy::clear();
        }

        $this->info("Synced {$total} template(s).");

        return self::SUCCESS;
    }
}
