<?php

namespace App\Http\Controllers;

use App\Models\AutomationRule;
use App\Services\AnalyticsService;
use App\Support\AnalyticsFilters;
use App\Support\Plans;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Analytics overview (B10.1) — real, filterable metrics computed by the
     * AnalyticsService (cached) from the workspace's conversations/orders/ratings.
     */
    public function index(Request $request, AnalyticsService $analytics): Response
    {
        $filters = AnalyticsFilters::fromRequest($request);

        $hasAi = Plans::includes(Tenancy::currentOrFail()->plan, 'ai_agents');

        return Inertia::render('Dashboard', [
            'kpis' => $analytics->kpis($filters),
            'revenueTrend' => $analytics->dailySeries($filters, 'revenue'),
            'leaderboard' => $analytics->agentLeaderboard($filters),
            'recovered' => (float) AutomationRule::sum('recovered_revenue'),
            'channels' => $analytics->channels(),
            'agents' => $analytics->agents(),
            'ai' => $hasAi ? $analytics->aiPerformance($filters) : null,
            'filters' => $filters->state(),
        ]);
    }
}
