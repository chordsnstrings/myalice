<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Concerns\StreamsCsv;
use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use App\Support\AnalyticsFilters;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class OperationsReportController extends Controller
{
    use StreamsCsv;

    /** Granular operations report — timing distributions, SLA, staffing heatmap. */
    public function index(Request $request, AnalyticsService $analytics): Response|InertiaResponse
    {
        $filters = AnalyticsFilters::fromRequest($request);
        $ops = $analytics->operations($filters);

        if ($request->query('export') === 'csv') {
            /** @var array<int, array<string, mixed>> $byChannel */
            $byChannel = $ops['by_channel'];

            return $this->streamCsv('operations.csv',
                ['Channel', 'Conversations', 'Avg response', 'Resolution %', 'CSAT'],
                array_map(fn ($c) => [$c['channel'], $c['conversations'], $c['avg_response'], $c['resolution_rate'], $c['csat'] ?? ''], $byChannel),
            );
        }

        return inertia('Reports/Operations', [
            'ops' => $ops,
            'channels' => $analytics->channels(),
            'agents' => $analytics->agents(),
            'filters' => $filters->state(),
        ]);
    }
}
