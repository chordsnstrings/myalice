<?php

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AnalyticsService;
use App\Support\AnalyticsFilters;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;

afterEach(fn () => Tenancy::clear());

function granWs(): Workspace
{
    return Workspace::create(['name' => 'Gran-'.uniqid(), 'plan' => 'business']);
}

function granUser(Workspace $ws, string $role): User
{
    return User::create(['workspace_id' => $ws->id, 'name' => ucfirst($role), 'email' => $role.uniqid().'@g.test', 'password' => bcrypt('x'), 'workspace_role' => $role]);
}

it('increments reopened_count when a resolved conversation is reopened', function () {
    $ws = granWs();
    $u = granUser($ws, 'manager');
    Tenancy::set($ws);
    $contact = Contact::create(['name' => 'C', 'phone' => '+1', 'channel' => 'whatsapp']);
    $conv = Conversation::create(['contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'resolved']);
    Tenancy::clear();

    $this->actingAs($u)->put("/conversations/{$conv->id}/resolve")->assertRedirect(); // reopen
    expect($conv->fresh()->status)->toBe('open');
    expect($conv->fresh()->reopened_count)->toBe(1);

    $this->actingAs($u)->put("/conversations/{$conv->id}/resolve"); // resolve (no increment)
    $this->actingAs($u)->put("/conversations/{$conv->id}/resolve"); // reopen again
    expect($conv->fresh()->reopened_count)->toBe(2);
});

it('reports discount totals, the order funnel and top products', function () {
    $ws = granWs();
    Tenancy::set($ws);
    $contact = Contact::create(['name' => 'C', 'phone' => '+1', 'channel' => 'whatsapp']);
    Conversation::create(['contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'open']);
    Order::create(['contact_id' => $contact->id, 'number' => '#1', 'subtotal' => 100, 'total' => 90, 'discount_amount' => 10, 'currency' => 'USD', 'status' => 'paid', 'source' => 'chat', 'line_items' => [['title' => 'Mug', 'qty' => 2]]]);
    Order::create(['contact_id' => $contact->id, 'number' => '#2', 'subtotal' => 50, 'total' => 50, 'currency' => 'USD', 'status' => 'pending', 'source' => 'chat', 'line_items' => [['title' => 'Mug', 'qty' => 1], ['title' => 'Hat', 'qty' => 3]]]);

    $f = new AnalyticsFilters(Carbon::now('UTC')->subDays(5), Carbon::now('UTC'), '7d');
    $s = app(AnalyticsService::class)->salesConversion($f);

    expect($s['discount_total'])->toBe(10.0);
    expect($s['discounted_orders'])->toBe(1);
    $funnel = collect($s['funnel'])->keyBy('label');
    expect($funnel['Orders']['value'])->toBe(2);
    expect($funnel['Paid']['value'])->toBe(1);
    $top = collect($s['top_products'])->keyBy('title');
    expect($top['Mug']['qty'])->toBe(3); // 2 + 1
    expect($top['Hat']['qty'])->toBe(3);
    Tenancy::clear();
});

it('buckets the trend into fewer points when grouped by week', function () {
    $ws = granWs();
    Tenancy::set($ws);
    $contact = Contact::create(['name' => 'C', 'phone' => '+1', 'channel' => 'whatsapp']);
    for ($i = 0; $i < 14; $i++) {
        $c = Conversation::create(['contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'open']);
        Conversation::withoutGlobalScopes()->where('id', $c->id)->update(['created_at' => Carbon::now('UTC')->subDays($i)]);
    }

    $svc = app(AnalyticsService::class);
    $day = new AnalyticsFilters(Carbon::now('UTC')->subDays(13)->startOfDay(), Carbon::now('UTC')->endOfDay(), 'custom', null, null, 'day');
    $week = $day->withGroup('week');

    expect(count($svc->dailySeries($day, 'conversations')))->toBe(14);
    expect(count($svc->dailySeries($week, 'conversations')))->toBeLessThan(14);
    Tenancy::clear();
});

it('exposes granular agent stats including reopen rate', function () {
    $ws = granWs();
    $mgr = granUser($ws, 'manager');
    $agent = granUser($ws, 'agent');
    Tenancy::set($ws);
    $contact = Contact::create(['name' => 'C', 'phone' => '+1', 'channel' => 'whatsapp']);
    $c1 = Conversation::create(['contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'resolved', 'assignee_id' => $agent->id]);
    $c2 = Conversation::create(['contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'resolved', 'assignee_id' => $agent->id]);
    $base = Carbon::now('UTC')->subDay();
    Conversation::withoutGlobalScopes()->whereIn('id', [$c1->id, $c2->id])->update([
        'created_at' => $base, 'first_response_at' => $base->clone()->addSeconds(60), 'resolved_at' => $base->clone()->addSeconds(600),
    ]);
    Conversation::withoutGlobalScopes()->where('id', $c1->id)->update(['reopened_count' => 1]);
    Tenancy::clear();

    $this->actingAs($mgr)->get("/reports/agents/{$agent->id}?range=7d")->assertOk()
        ->assertInertia(fn ($p) => $p->component('Reports/AgentDetail')
            ->has('detail.stats', 5)
            ->where('detail.stats.4.label', 'Reopen rate')
            ->where('detail.stats.4.value', '50%'));
});

it('accepts a custom date range', function () {
    $ws = granWs();
    $mgr = granUser($ws, 'manager');
    $from = Carbon::now('UTC')->subDays(20)->toDateString();
    $to = Carbon::now('UTC')->toDateString();

    $this->actingAs($mgr)->get("/reports/operations?range=custom&from={$from}&to={$to}")->assertOk()
        ->assertInertia(fn ($p) => $p->component('Reports/Operations')->where('filters.range', 'custom')->where('filters.from', $from));
});
