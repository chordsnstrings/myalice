<?php

namespace App\Http\Controllers;

use App\Channels\ChannelManager;
use App\Models\Audience;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Channel;
use App\Models\MessageTemplate;
use App\Services\AnalyticsService;
use App\Services\BroadcastLauncher;
use App\Services\BroadcastPricing;
use App\Services\WalletService;
use App\Support\AnalyticsFilters;
use App\Support\InsufficientFundsException;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            'channel' => $b->channel,
            'template' => $b->template?->name,
            'status' => $b->status,
            'recipients' => $b->recipients,
            'delivered' => $b->delivered,
            'read' => $b->read,
            'replied' => $b->replied,
            'failed' => $b->failed,
            'credit_cost' => (float) $b->credit_cost,
            'spent_cost' => (float) $b->spent_cost,
            'schedule_at' => optional($b->schedule_at)->toIso8601String(),
        ]);

        $filters = new AnalyticsFilters(now()->subDays(30)->startOfDay(), now()->endOfDay(), '30d', null, null);

        return Inertia::render('Broadcasts/Index', [
            'broadcasts' => $broadcasts,
            'summary' => app(AnalyticsService::class)->broadcastPerformance($filters),
        ]);
    }

    /** Broadcast composer with the wallet pre-flight gate (B6.2 / C-03). */
    public function create(): Response
    {
        $templates = MessageTemplate::where('approval_status', 'approved')->orderBy('name')->get()
            ->map(fn (MessageTemplate $t) => [
                'id' => $t->id, 'name' => $t->name, 'category' => $t->category,
                'language' => $t->language, 'body' => $t->body, 'variable_count' => $t->variable_count,
            ]);

        $audiences = Audience::orderBy('name')->get()->map(fn (Audience $a) => [
            'id' => $a->id, 'name' => $a->name, 'size' => $a->size,
        ]);

        $senders = Channel::whereIn('type', ['whatsapp', 'messenger', 'instagram'])->where('status', 'connected')
            ->get()->map(fn (Channel $c) => ['id' => $c->id, 'type' => $c->type, 'name' => $c->name]);

        return Inertia::render('Broadcasts/Create', [
            'templates' => $templates,
            'audiences' => $audiences,
            'senders' => $senders,
            'wallet' => (float) Tenancy::currentOrFail()->wallet_balance,
            'contact_fields' => ['name', 'email', 'phone'],
        ]);
    }

    /** Audience size + cost preview (AJAX). */
    public function preview(Request $request, BroadcastLauncher $launcher): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in(['whatsapp', 'messenger', 'instagram'])],
            'audience_id' => ['nullable', 'integer', 'exists:audiences,id'],
            'message_template_id' => ['nullable', 'integer', 'exists:message_templates,id'],
        ]);

        $audience = ($data['audience_id'] ?? null) ? Audience::find($data['audience_id']) : null;
        $category = ($data['message_template_id'] ?? null)
            ? (string) (MessageTemplate::whereKey($data['message_template_id'])->value('category') ?? 'marketing')
            : 'marketing';

        return response()->json($launcher->estimate($data['channel'], $audience, $category));
    }

    /**
     * Create + launch (or schedule) a broadcast. Cost is computed server-side and
     * the wallet is the gate — a send that exceeds the balance is blocked (C-03).
     */
    public function store(Request $request, BroadcastLauncher $launcher): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', Rule::in(['whatsapp', 'messenger', 'instagram'])],
            'message_template_id' => ['nullable', 'integer', 'exists:message_templates,id'],
            'audience_id' => ['nullable', 'integer', 'exists:audiences,id'],
            'variable_map' => ['array'],
            'schedule_at' => ['nullable', 'date', 'after:now'],
        ]);

        $workspace = Tenancy::currentOrFail();
        $audience = ($data['audience_id'] ?? null) ? Audience::find($data['audience_id']) : null;
        $template = ($data['message_template_id'] ?? null) ? MessageTemplate::find($data['message_template_id']) : null;

        // Every broadcast needs message content (a template body).
        if (! $template) {
            return back()->withErrors(['message_template_id' => 'Choose a template.']);
        }
        // WhatsApp broadcasts must use an approved template (Meta policy).
        if ($data['channel'] === 'whatsapp' && ! $template->isSendable()) {
            return back()->withErrors(['message_template_id' => 'Choose an approved WhatsApp template.']);
        }

        $category = (string) (MessageTemplate::whereKey($data['message_template_id'] ?? null)->value('category') ?? 'marketing');
        $estimate = $launcher->estimate($data['channel'], $audience, $category);

        if ($estimate['recipients'] === 0) {
            return back()->withErrors(['audience_id' => 'No eligible, opted-in recipients for this channel.']);
        }
        if ($estimate['cost'] > (float) $workspace->wallet_balance) {
            return back()->withErrors(['credit_cost' => 'Insufficient wallet balance for this send. Top up to continue.']);
        }

        $broadcast = Broadcast::create([
            'name' => $data['name'],
            'channel' => $data['channel'],
            'message_template_id' => $template->id,
            'variable_map' => $data['variable_map'] ?? [],
            'audience_id' => $audience?->id,
            'credit_cost' => $estimate['cost'],
            'recipients' => $estimate['recipients'],
            'status' => empty($data['schedule_at']) ? 'launching' : 'scheduled',
            'schedule_at' => $data['schedule_at'] ?? null,
        ]);

        if (empty($data['schedule_at'])) {
            try {
                $launcher->launch($broadcast);
            } catch (InsufficientFundsException) {
                return back()->withErrors(['credit_cost' => 'Insufficient wallet balance for this send.']);
            }
        }

        return redirect('/broadcasts')->with('success', empty($data['schedule_at']) ? 'Broadcast sending.' : 'Broadcast scheduled.');
    }

    /** Broadcast detail with live per-status breakdown. */
    public function show(Broadcast $broadcast): Response
    {
        $counts = BroadcastRecipient::where('broadcast_id', $broadcast->id)
            ->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return Inertia::render('Broadcasts/Show', [
            'broadcast' => [
                'id' => $broadcast->id, 'name' => $broadcast->name, 'channel' => $broadcast->channel,
                'status' => $broadcast->status, 'template' => $broadcast->template?->name,
                'recipients' => $broadcast->recipients, 'delivered' => $broadcast->delivered,
                'read' => $broadcast->read, 'replied' => $broadcast->replied, 'failed' => $broadcast->failed,
                'reserved_cost' => (float) $broadcast->reserved_cost, 'spent_cost' => (float) $broadcast->spent_cost,
                'schedule_at' => optional($broadcast->schedule_at)->toIso8601String(),
                'started_at' => optional($broadcast->started_at)->toIso8601String(),
                'completed_at' => optional($broadcast->completed_at)->toIso8601String(),
            ],
            'breakdown' => $counts,
        ]);
    }

    public function pause(Broadcast $broadcast): RedirectResponse
    {
        if ($broadcast->status === 'sending') {
            $broadcast->update(['status' => 'paused']);
        }

        return back()->with('success', 'Broadcast paused.');
    }

    public function resume(Broadcast $broadcast, BroadcastLauncher $launcher): RedirectResponse
    {
        if ($broadcast->status === 'paused') {
            $broadcast->update(['status' => 'sending']);
            $launcher->dispatchQueued($broadcast);
        }

        return back()->with('success', 'Broadcast resumed.');
    }

    /** Cancel a running/scheduled broadcast and refund the unsent reserve. */
    public function cancel(Broadcast $broadcast): RedirectResponse
    {
        if (in_array($broadcast->status, ['completed', 'canceled', 'failed'], true)) {
            return back();
        }

        $queued = BroadcastRecipient::where('broadcast_id', $broadcast->id)->where('status', 'queued');
        $refund = round((float) $queued->sum('cost'), 2);
        $queued->update(['status' => 'skipped', 'skip_reason' => 'canceled']);

        if ($refund > 0) {
            app(WalletService::class)->credit(Tenancy::currentOrFail(), $refund, "Broadcast canceled: {$broadcast->name}");
        }

        $broadcast->update(['status' => 'canceled', 'completed_at' => now()]);

        return back()->with('success', 'Broadcast canceled.');
    }

    /** Send a single template to a test number — no recipients, no wallet. */
    public function testSend(Request $request, ChannelManager $channels, BroadcastPricing $pricing): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in(['whatsapp', 'messenger', 'instagram'])],
            'message_template_id' => ['required', 'integer', 'exists:message_templates,id'],
            'to' => ['required', 'string', 'max:64'],
            'sample' => ['array'],
            'sample.*' => ['string'],
        ]);

        $template = MessageTemplate::findOrFail($data['message_template_id']);
        $params = array_values($data['sample'] ?? []);

        $components = [];
        if ($params !== []) {
            $components[] = ['type' => 'body', 'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => $p], $params)];
        }
        $tpl = ['name' => $template->name, 'language' => ['code' => $template->language]];
        if ($components !== []) {
            $tpl['components'] = $components;
        }
        $payload = $data['channel'] === 'whatsapp'
            ? ['type' => 'template', 'template' => $tpl]
            : ['type' => 'text', 'text' => ['body' => $template->render($params)]];

        $channels->for($data['channel'])->send($data['to'], $payload);

        return back()->with('success', 'Test message sent.');
    }
}
