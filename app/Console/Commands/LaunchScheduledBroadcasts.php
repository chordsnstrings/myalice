<?php

namespace App\Console\Commands;

use App\Models\Broadcast;
use App\Models\Workspace;
use App\Services\BroadcastLauncher;
use App\Support\InsufficientFundsException;
use App\Support\Tenancy;
use Illuminate\Console\Command;

/**
 * Launches broadcasts whose scheduled time has arrived. Runs on the cron-drained
 * scheduler; the launcher reserves the wallet and enqueues the paced chunks.
 */
class LaunchScheduledBroadcasts extends Command
{
    protected $signature = 'broadcasts:launch-due';

    protected $description = 'Launch scheduled broadcasts that are now due';

    public function handle(BroadcastLauncher $launcher): int
    {
        $launched = 0;

        foreach (Workspace::all() as $workspace) {
            Tenancy::set($workspace);

            $due = Broadcast::where('status', 'scheduled')->where('schedule_at', '<=', now())->get();
            foreach ($due as $broadcast) {
                $broadcast->update(['status' => 'launching']);
                try {
                    $launcher->launch($broadcast);
                    $launched++;
                } catch (InsufficientFundsException) {
                    $broadcast->update(['status' => 'failed']);
                }
            }

            Tenancy::clear();
        }

        $this->info("Launched {$launched} broadcast(s).");

        return self::SUCCESS;
    }
}
