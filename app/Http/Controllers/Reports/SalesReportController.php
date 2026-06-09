<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Concerns\StreamsCsv;
use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use App\Support\AnalyticsFilters;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class SalesReportController extends Controller
{
    use StreamsCsv;

    /** Sales / conversion report (B10.3). */
    public function index(Request $request, AnalyticsService $analytics): Response|InertiaResponse
    {
        $filters = AnalyticsFilters::fromRequest($request);
        $sales = $analytics->salesConversion($filters);

        if ($request->query('export') === 'csv') {
            return $this->streamCsv('sales-by-agent.csv',
                ['Agent', 'Handled', 'Revenue'],
                array_map(fn ($a) => [$a['name'], $a['handled'], $a['revenue']], $sales['by_agent']),
            );
        }

        return inertia('Reports/Sales', [
            'sales' => $sales,
            'channels' => $analytics->channels(),
            'agents' => $analytics->agents(),
            'filters' => $filters->state(),
        ]);
    }
}
