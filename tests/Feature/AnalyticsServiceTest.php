<?php

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\CsatRating;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AnalyticsService;
use App\Support\AnalyticsFilters;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;

afterEach(fn () => Tenancy::clear());

function filters(string $range = '7d', ?string $channel = null, ?int $agent = null): AnalyticsFilters
{
    $days = $range === '90d' ? 90 : ($range === '30d' ? 30 : 7);

    return new AnalyticsFilters(
        from: Carbon::now()->startOfDay()->subDays($days - 1)->utc(),
        to: Carbon::now()->endOfDay()->utc(),
        range: $range,
        channel: $channel,
        agentId: $agent,
    );
}

function makeConv(Workspace $ws, int $contact, ?int $agent, array $attrs): Conversation
{
    $c = Conversation::create(array_merge([
        'workspace_id' => $ws->id, 'contact_id' => $contact, 'channel' => 'whatsapp',
        'status' => 'open', 'assignee_id' => $agent,
    ], $attrs));

    if (isset($attrs['created_at'])) {
        Conversation::where('id', $c->id)->update(['created_at' => $attrs['created_at']]);
    }

    return $c;
}

it('computes resolution rate, avg response and CSAT', function () {
    $ws = Workspace::create(['name' => 'A']);
    Tenancy::set($ws);
    $contact = Contact::create(['name' => 'C', 'phone' => '+1'])->id;

    // Two conversations now: one resolved (response 120s), one open (response 240s).
    $resolved = makeConv($ws, $contact, null, [
        'status' => 'resolved', 'created_at' => now(),
        'first_response_at' => now()->addSeconds(120), 'resolved_at' => now()->addHour(),
    ]);
    makeConv($ws, $contact, null, [
        'status' => 'open', 'created_at' => now(),
        'first_response_at' => now()->addSeconds(240),
    ]);

    CsatRating::create(['workspace_id' => $ws->id, 'conversation_id' => $resolved->id, 'channel' => 'whatsapp', 'rating' => 4, 'rated_at' => now()]);
    CsatRating::create(['workspace_id' => $ws->id, 'conversation_id' => $resolved->id, 'channel' => 'whatsapp', 'rating' => 2, 'rated_at' => now()]);

    $kpis = collect(app(AnalyticsService::class)->kpis(filters()))->keyBy('label');

    expect($kpis['Conversations']['value'])->toBe('2');
    expect($kpis['Resolution rate']['value'])->toBe('50%');
    expect($kpis['Avg response']['value'])->toBe('3m 0s'); // (120+240)/2 = 180s
    expect($kpis['CSAT']['value'])->toBe('3'); // (4+2)/2
});

it('changes results when the date range filter changes', function () {
    $ws = Workspace::create(['name' => 'B']);
    Tenancy::set($ws);
    $contact = Contact::create(['name' => 'C', 'phone' => '+1'])->id;

    makeConv($ws, $contact, null, ['created_at' => now()]);                 // in 7d & 90d
    makeConv($ws, $contact, null, ['created_at' => now()->subDays(30)]);    // only in 90d

    $svc = app(AnalyticsService::class);
    expect(collect($svc->kpis(filters('7d')))->firstWhere('label', 'Conversations')['value'])->toBe('1');
    expect(collect($svc->kpis(filters('90d')))->firstWhere('label', 'Conversations')['value'])->toBe('2');
});

it('filters by channel', function () {
    $ws = Workspace::create(['name' => 'C']);
    Tenancy::set($ws);
    $contact = Contact::create(['name' => 'C', 'phone' => '+1'])->id;

    makeConv($ws, $contact, null, ['channel' => 'whatsapp', 'created_at' => now()]);
    makeConv($ws, $contact, null, ['channel' => 'instagram', 'created_at' => now()]);

    $svc = app(AnalyticsService::class);
    expect(collect($svc->kpis(filters('7d', 'whatsapp')))->firstWhere('label', 'Conversations')['value'])->toBe('1');
    expect(collect($svc->kpis(filters('7d')))->firstWhere('label', 'Conversations')['value'])->toBe('2');
});

it('groups the leaderboard per agent', function () {
    $ws = Workspace::create(['name' => 'D']);
    Tenancy::set($ws);
    $a = User::create(['workspace_id' => $ws->id, 'name' => 'Ana', 'email' => 'ana@d.test', 'password' => bcrypt('x'), 'workspace_role' => 'agent']);
    $b = User::create(['workspace_id' => $ws->id, 'name' => 'Ben', 'email' => 'ben@d.test', 'password' => bcrypt('x'), 'workspace_role' => 'agent']);
    $contact = Contact::create(['name' => 'C', 'phone' => '+1'])->id;

    makeConv($ws, $contact, $a->id, ['created_at' => now()]);
    makeConv($ws, $contact, $a->id, ['created_at' => now()]);
    makeConv($ws, $contact, $b->id, ['created_at' => now()]);

    $board = collect(app(AnalyticsService::class)->agentLeaderboard(filters()))->keyBy('name');
    expect($board['Ana']['handled'])->toBe(2);
    expect($board['Ben']['handled'])->toBe(1);
});
