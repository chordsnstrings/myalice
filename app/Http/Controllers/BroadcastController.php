<?php

namespace App\Http\Controllers;

use App\Jobs\SendBroadcast;
use App\Models\Audience;
use App\Models\Broadcast;
use App\Models\Contact;
use App\Models\MessageTemplate;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    /**
     * Persist + dispatch a broadcast. The wallet is the gate: a send that costs
     * more than the balance is blocked, not half-sent (C-03 / Part D money rule).
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'message_template_id' => ['nullable', 'integer', 'exists:message_templates,id'],
            'audience_id' => ['nullable', 'integer', 'exists:audiences,id'],
            'recipients' => ['required', 'integer', 'min:1'],
            'credit_cost' => ['required', 'numeric', 'min:0'],
            'schedule_at' => ['nullable', 'date'],
        ]);

        $workspace = Tenancy::currentOrFail();

        if ((float) $data['credit_cost'] > (float) $workspace->wallet_balance) {
            return back()->withErrors([
                'credit_cost' => 'Insufficient wallet balance for this send. Top up to continue.',
            ]);
        }

        $broadcast = Broadcast::create([
            ...$data,
            'status' => empty($data['schedule_at']) ? 'sending' : 'scheduled',
        ]);

        if (empty($data['schedule_at'])) {
            SendBroadcast::dispatch($broadcast->id);
        }

        return redirect('/broadcasts')->with('success', 'Broadcast queued.');
    }
}
