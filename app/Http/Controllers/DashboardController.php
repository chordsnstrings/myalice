<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Analytics overview (B10.1). Seeded metrics; the queued aggregation
     * pipeline lands in Phase 11.
     */
    public function index(): Response
    {
        return Inertia::render('Dashboard', [
            'kpis' => [
                ['label' => 'Conversations', 'value' => '1,284', 'delta' => 12, 'spark' => [30, 42, 38, 50, 47, 60, 72]],
                ['label' => 'Avg response', 'value' => '2m 18s', 'delta' => -8, 'spark' => [60, 55, 58, 50, 44, 40, 38]],
                ['label' => 'Resolution rate', 'value' => '94%', 'delta' => 3, 'spark' => [80, 82, 85, 88, 90, 92, 94]],
                ['label' => 'CSAT', 'value' => '4.8', 'delta' => 2, 'spark' => [40, 44, 43, 46, 47, 47, 48]],
            ],
            'agents' => [
                ['name' => 'You', 'handled' => 312, 'csat' => 96, 'response' => '1m 40s'],
                ['name' => 'Maya Osei', 'handled' => 287, 'csat' => 94, 'response' => '2m 05s'],
                ['name' => 'Sara Lopez', 'handled' => 241, 'csat' => 91, 'response' => '2m 32s'],
                ['name' => 'Omar Aziz', 'handled' => 198, 'csat' => 89, 'response' => '3m 10s'],
            ],
            'revenue' => 48230,
            'recovered' => 9120,
        ]);
    }
}
