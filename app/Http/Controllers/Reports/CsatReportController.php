<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Concerns\StreamsCsv;
use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use App\Support\AnalyticsFilters;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class CsatReportController extends Controller
{
    use StreamsCsv;

    /** CSAT report (B10.4). */
    public function index(Request $request, AnalyticsService $analytics): Response|InertiaResponse
    {
        $filters = AnalyticsFilters::fromRequest($request);
        $report = $analytics->csatReport($filters);

        if ($request->query('export') === 'csv') {
            return $this->streamCsv('csat-by-agent.csv',
                ['Agent', 'Average', 'Responses'],
                array_map(fn ($a) => [$a['name'], $a['average'], $a['count']], $report['by_agent']),
            );
        }

        return inertia('Reports/Csat', [
            'report' => $report,
            'channels' => $analytics->channels(),
            'agents' => $analytics->agents(),
            'filters' => $filters->state(),
        ]);
    }
}
