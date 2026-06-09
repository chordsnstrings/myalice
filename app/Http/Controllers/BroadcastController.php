<?php

namespace App\Http\Controllers;

use App\Models\Audience;
use App\Models\Broadcast;
use App\Models\Contact;
use App\Models\MessageTemplate;
use App\Support\Tenancy;
use Inertia\Inertia;
use Inertia\Response;

class BroadcastController extends Controller
{
    /** Broadcast list (B6.1). */
    public function index(): Response
    {
        $broadcasts = Broadcast::with('template')->latest()->get()->map(fn (Broadcast $b) => [
            'id' => $b->id,
            'name' => $b->name,
            'template' => $b->template?->name,
            'status' => $b->status,
            'recipients' => $b->recipients,
            'delivered' => $b->delivered,
            'read' => $b->read,
            'replied' => $b->replied,
            'credit_cost' => (float) $b->credit_cost,
            'schedule_at' => optional($b->schedule_at)->toIso8601String(),
        ]);

        return Inertia::render('Broadcasts/Index', ['broadcasts' => $broadcasts]);
    }

    /** Broadcast composer with the wallet pre-flight gate (B6.2 / C-03). */
    public function create(): Response
    {
        $templates = MessageTemplate::orderBy('name')->get()->map(fn (MessageTemplate $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'category' => $t->category,
            'approval_status' => $t->approval_status,
            'quality' => $t->quality,
            'body' => $t->body,
        ]);

        $audiences = Audience::orderBy('name')->get()->map(fn (Audience $a) => [
            'id' => $a->id,
            'name' => $a->name,
            'size' => $a->size,
        ]);

        // Per-message price estimate (illustrative; real rates come from Meta per §10).
        return Inertia::render('Broadcasts/Create', [
            'templates' => $templates,
            'audiences' => $audiences,
            'wallet' => (float) Tenancy::currentOrFail()->wallet_balance,
            'total_contacts' => Contact::count(),
            'opted_out' => 2,
            'price_per_message' => 0.0125,
        ]);
    }
}
