<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed contact_channels from existing contacts so the broadcast pipeline has a
 * per-channel identity for everyone already in the system. Existing contacts are
 * treated as legacy opt-ins. Idempotent — safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('contacts')->orderBy('id')->chunkById(500, function ($contacts) use ($now) {
            $rows = [];
            foreach ($contacts as $c) {
                $externalId = $c->phone ?: $c->email;
                if (! $externalId) {
                    continue; // nothing to address them by
                }

                $exists = DB::table('contact_channels')
                    ->where('workspace_id', $c->workspace_id)
                    ->where('channel', $c->channel)
                    ->where('external_id', $externalId)
                    ->exists();
                if ($exists) {
                    continue;
                }

                $rows[] = [
                    'workspace_id' => $c->workspace_id,
                    'contact_id' => $c->id,
                    'channel' => $c->channel,
                    'external_id' => $externalId,
                    'opted_in_at' => $now,
                    'opt_in_source' => 'legacy',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows !== []) {
                DB::table('contact_channels')->insert($rows);
            }
        });
    }

    public function down(): void
    {
        DB::table('contact_channels')->where('opt_in_source', 'legacy')->delete();
    }
};
