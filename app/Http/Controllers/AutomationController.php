<?php

namespace App\Http\Controllers;

use App\Models\AutomationRule;
use Inertia\Inertia;
use Inertia\Response;

class AutomationController extends Controller
{
    /** Automation list (B7.1). */
    public function index(): Response
    {
        $rules = AutomationRule::latest()->get()->map(fn (AutomationRule $r) => [
            'id' => $r->id,
            'name' => $r->name,
            'trigger_type' => $r->trigger_type,
            'status' => $r->status,
            'sent' => $r->sent,
            'recovered_revenue' => (float) $r->recovered_revenue,
        ]);

        return Inertia::render('Automations/Index', ['rules' => $rules]);
    }
}
