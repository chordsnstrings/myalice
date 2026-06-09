<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Concerns\StreamsCsv;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Support\AnalyticsFilters;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class AgentPerformanceController extends Controller
{
    use StreamsCsv;

    /** Agent performance report (B10.2). */
    public function index(Request $request, AnalyticsService $analytics): Response|InertiaResponse
    {
        $filters = AnalyticsFilters::fromRequest($request);
        $leaderboard = $analytics->agentLeaderboard($filters);

        if ($request->query('export') === 'csv') {
            return $this->streamCsv('agent-performance.csv',
                ['Agent', 'Handled', 'Avg response', 'Resolution %', 'CSAT', 'Revenue'],
                array_map(fn ($a) => [$a['name'], $a['handled'], $a['avg_response'], $a['resolution_rate'], $a['csat'] ?? '', $a['revenue']], $leaderboard),
            );
        }

        return inertia('Reports/AgentPerformance', [
            'leaderboard' => $leaderboard,
            'channels' => $analytics->channels(),
            'agents' => $analytics->agents(),
            'filters' => $filters->state(),
        ]);
    }

    /** Per-agent drill-down (B10.2). */
    public function show(Request $request, User $agent, AnalyticsService $analytics): InertiaResponse
    {
        abort_unless($agent->workspace_id === Tenancy::id(), 404);

        $filters = AnalyticsFilters::fromRequest($request);

        return inertia('Reports/AgentDetail', [
            'detail' => $analytics->agentDetail($filters, $agent),
            'channels' => $analytics->channels(),
            'filters' => $filters->state(),
        ]);
    }
}
