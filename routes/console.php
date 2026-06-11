<?php

use Illuminate\Support\Facades\Schedule;

/*
| SiteGround has no persistent daemons (§3). A single cron entry runs the
| scheduler every minute; the scheduler drains the database queue in short,
| self-terminating bursts. Never run `queue:work` as a daemon here.
|
|   * * * * * php /home/USER/path/artisan schedule:run >> /dev/null 2>&1
*/

// Drain the database queue without a long-running worker.
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();

// Nightly analytics rollup for finalized days (trend backing).
Schedule::command('analytics:snapshot')->dailyAt('00:20')->withoutOverlapping();

// Hourly ~23h in-window AI re-engagement for stalled, customer-started chats.
Schedule::command('ai:reengage')->hourly()->withoutOverlapping();

// Pull WhatsApp template approval statuses from Meta (async approvals).
Schedule::command('templates:sync')->everyThirtyMinutes()->withoutOverlapping();

// Launch broadcasts whose scheduled time has arrived.
Schedule::command('broadcasts:launch-due')->everyMinute()->withoutOverlapping();

// Keep agent knowledge (website/Facebook) fresh.
Schedule::command('knowledge:refresh')->daily()->withoutOverlapping();

// Housekeeping kept light to respect shared-CPU limits.
Schedule::command('queue:prune-batches --hours=48')->daily();
Schedule::command('auth:clear-resets')->daily();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
