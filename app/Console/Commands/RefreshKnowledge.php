<?php

namespace App\Console\Commands;

use App\Jobs\FetchKnowledgeSource;
use App\Models\KnowledgeSource;
use App\Models\Workspace;
use App\Support\Tenancy;
use Illuminate\Console\Command;

/**
 * Re-fetch website/Facebook knowledge sources so the agent's context stays
 * current. Manual sources are skipped (their text doesn't change remotely).
 */
class RefreshKnowledge extends Command
{
    protected $signature = 'knowledge:refresh';

    protected $description = 'Re-fetch website/Facebook knowledge sources for every workspace';

    public function handle(): int
    {
        $count = 0;

        foreach (Workspace::all() as $workspace) {
            Tenancy::set($workspace);
            foreach (KnowledgeSource::whereIn('type', ['website', 'facebook_page'])->get() as $source) {
                FetchKnowledgeSource::dispatch($workspace->id, $source->id);
                $count++;
            }
            Tenancy::clear();
        }

        $this->info("Queued {$count} knowledge refresh(es).");

        return self::SUCCESS;
    }
}
