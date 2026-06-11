<?php

namespace App\Console\Commands;

use App\Ai\Embedder;
use App\Models\KnowledgeSnippet;
use App\Models\KnowledgeSource;
use App\Models\Workspace;
use App\Support\Tenancy;
use Illuminate\Console\Command;

/**
 * Backfill embeddings for snippets that don't have one yet (manual sources, or
 * rows created before semantic retrieval existed). Best-effort per workspace:
 * skips workspaces with no embeddings-capable provider.
 */
class EmbedKnowledge extends Command
{
    protected $signature = 'knowledge:embed';

    protected $description = 'Embed knowledge snippets that are missing an embedding';

    public function handle(Embedder $embedder): int
    {
        $count = 0;

        foreach (Workspace::all() as $workspace) {
            Tenancy::set($workspace);

            if ($embedder->available()) {
                $sourceIds = KnowledgeSnippet::whereNull('embedding')
                    ->distinct()->pluck('knowledge_source_id');

                foreach (KnowledgeSource::whereIn('id', $sourceIds)->get() as $source) {
                    $embedder->embedSnippets($source);
                    $count++;
                }
            }

            Tenancy::clear();
        }

        $this->info("Embedded snippets for {$count} source(s).");

        return self::SUCCESS;
    }
}
