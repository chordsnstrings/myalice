<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Concerns\StreamsCsv;
use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use App\Support\AnalyticsFilters;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class TopicsReportController extends Controller
{
    use StreamsCsv;

    /** Topic analytics — what conversations are about (tag volume / quality). */
    public function index(Request $request, AnalyticsService $analytics): Response|InertiaResponse
    {
        $filters = AnalyticsFilters::fromRequest($request);
        $topics = $analytics->topics($filters);

        if ($request->query('export') === 'csv') {
            /** @var array<int, array<string, mixed>> $tags */
            $tags = $topics['tags'];

            return $this->streamCsv('topics.csv',
                ['Topic', 'Conversations', 'Share %', 'Resolution %', 'CSAT'],
                array_map(fn ($t) => [$t['name'], $t['count'], $t['share'], $t['resolution_rate'], $t['csat'] ?? ''], $tags),
            );
        }

        return inertia('Reports/Topics', [
            'topics' => $topics,
            'channels' => $analytics->channels(),
            'agents' => $analytics->agents(),
            'filters' => $filters->state(),
        ]);
    }
}
