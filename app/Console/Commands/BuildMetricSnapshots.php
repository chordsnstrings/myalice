<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\CsatRating;
use App\Models\MetricSnapshot;
use App\Models\Order;
use App\Models\Workspace;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Rolls up a single day's metrics into metric_snapshots (sums + counts) per
 * (channel, agent) plus all-up rollups. Idempotent via the unique index, so it
 * can be re-run safely. Scheduled nightly for finalized days (scale path).
 */
class BuildMetricSnapshots extends Command
{
    protected $signature = 'analytics:snapshot {--day= : Y-m-d (defaults to yesterday)}';

    protected $description = 'Build daily analytics snapshots for every workspace';

    public function handle(): int
    {
        foreach (Workspace::all() as $workspace) {
            Tenancy::set($workspace);
            $tz = $workspace->timezone ?: 'UTC';
            $day = $this->option('day')
                ? Carbon::parse((string) $this->option('day'), $tz)
                : Carbon::now($tz)->subDay();

            $this->buildDay($workspace->id, $day, $tz);
        }

        Tenancy::clear();

        return self::SUCCESS;
    }

    private function buildDay(int $workspaceId, Carbon $day, string $tz): void
    {
        $start = $day->clone()->startOfDay()->utc();
        $end = $day->clone()->endOfDay()->utc();
        $dateStr = $day->format('Y-m-d');

        $convs = Conversation::whereBetween('created_at', [$start, $end])
            ->get(['channel', 'assignee_id', 'created_at', 'first_response_at', 'resolved_at']);
        $ratings = CsatRating::whereBetween('rated_at', [$start, $end])->get(['channel', 'agent_id', 'rating']);
        $orders = Order::where('source', 'chat')->whereBetween('created_at', [$start, $end])->get(['total']);

        // All-up rollup (channel=null, agent=null).
        $this->upsert($workspaceId, $dateStr, null, null, $convs, $ratings, $orders->sum('total'), $orders->count());

        // Per-channel (agent=null).
        foreach ($convs->pluck('channel')->unique() as $channel) {
            $cc = $convs->where('channel', $channel);
            $cr = $ratings->where('channel', $channel);
            $this->upsert($workspaceId, $dateStr, $channel, null, $cc, $cr, 0, 0);
        }

        // Per-agent (channel=null).
        foreach ($convs->pluck('assignee_id')->filter()->unique() as $agentId) {
            $ac = $convs->where('assignee_id', $agentId);
            $ar = $ratings->where('agent_id', $agentId);
            $this->upsert($workspaceId, $dateStr, null, (int) $agentId, $ac, $ar, 0, 0);
        }
    }

    /**
     * @param  Collection<int, Conversation>  $convs
     * @param  Collection<int, CsatRating>  $ratings
     */
    private function upsert(int $workspaceId, string $day, ?string $channel, ?int $agentId, $convs, $ratings, float $revenue, int $orders): void
    {
        $responded = $convs->whereNotNull('first_response_at');
        $resolved = $convs->whereNotNull('resolved_at');

        $values = [
            'conversations' => $convs->count(),
            'resolved' => $resolved->count(),
            'first_response_seconds_sum' => (int) $responded->sum(fn ($c) => abs($c->first_response_at->diffInSeconds($c->created_at))),
            'first_response_count' => $responded->count(),
            'resolution_seconds_sum' => (int) $resolved->sum(fn ($c) => abs($c->resolved_at->diffInSeconds($c->created_at))),
            'resolution_count' => $resolved->count(),
            'csat_sum' => (int) $ratings->sum('rating'),
            'csat_count' => $ratings->count(),
            'revenue' => $revenue,
            'orders' => $orders,
        ];

        // Manual upsert — Eloquent's updateOrCreate matches null columns with
        // `= NULL` (never true), so we use whereNull for the null dimensions.
        $existing = MetricSnapshot::where('workspace_id', $workspaceId)
            ->whereDate('day', $day)
            ->when($channel === null, fn ($q) => $q->whereNull('channel'), fn ($q) => $q->where('channel', $channel))
            ->when($agentId === null, fn ($q) => $q->whereNull('agent_id'), fn ($q) => $q->where('agent_id', $agentId))
            ->first();

        if ($existing) {
            $existing->update($values);
        } else {
            MetricSnapshot::create([
                'workspace_id' => $workspaceId, 'day' => $day, 'channel' => $channel, 'agent_id' => $agentId,
                ...$values,
            ]);
        }
    }
}
