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

function opsUser(string $role, ?Workspace $ws = null): User
{
    $ws ??= Workspace::create(['name' => 'Ops-'.uniqid(), 'plan' => 'business']);

    return User::create([
        'workspace_id' => $ws->id, 'name' => ucfirst($role), 'email' => $role.uniqid().'@ops.test',
        'password' => bcrypt('x'), 'workspace_role' => $role,
    ]);
}

/** Create a conversation with controlled timing (bypasses auto-timestamps). */
function opsConv(Workspace $ws, int $contactId, array $attrs, int $responseSecs, ?int $resolveSecs): void
{
    $c = Conversation::create(array_merge(['workspace_id' => $ws->id, 'contact_id' => $contactId, 'channel' => 'whatsapp', 'status' => 'open'], $attrs));
    $created = Carbon::now('UTC')->subDays(2)->startOfHour();
    Conversation::withoutGlobalScopes()->where('id', $c->id)->update([
        'created_at' => $created,
        'first_response_at' => $created->clone()->addSeconds($responseSecs),
        'resolved_at' => $resolveSecs ? $created->clone()->addSeconds($resolveSecs) : null,
    ]);
}

it('computes median + p90 timings, status split and SLA attainment', function () {
    $ws = Workspace::create(['name' => 'OpsCalc', 'plan' => 'business']);
    Tenancy::set($ws);
    $contact = Contact::create(['name' => 'C', 'phone' => '+1', 'channel' => 'whatsapp']);

    opsConv($ws, $contact->id, ['status' => 'resolved'], 30, 100);
    opsConv($ws, $contact->id, ['status' => 'resolved'], 120, 200);
    opsConv($ws, $contact->id, ['status' => 'open'], 600, null);
    opsConv($ws, $contact->id, ['status' => 'pending', 'sla_breaching' => true], 2400, null);

    $f = new AnalyticsFilters(Carbon::now('UTC')->subDays(10), Carbon::now('UTC'), '90d');
    $ops = app(AnalyticsService::class)->operations($f);

    expect($ops['total'])->toBe(4);
    expect($ops['resolved'])->toBe(2);
    expect($ops['resolution_rate'])->toBe(50.0);
    expect($ops['median_first_response'])->toBe('6m 0s');   // median of 30,120,600,2400 = 360s
    expect($ops['p90_first_response'])->toBe('40m 0s');     // p90 = 2400s
    expect($ops['sla_attainment'])->toBe(75.0);            // 1 breach of 4
    expect($ops['sla_breaches'])->toBe(1);

    $split = collect($ops['status_split'])->keyBy('label');
    expect($split['Resolved']['value'])->toBe(2);
    expect($split['Open']['value'])->toBe(1);
    expect($split['Pending']['value'])->toBe(1);

    expect($ops['heatmap']['grid'])->toHaveCount(7);
    expect($ops['heatmap']['grid'][0])->toHaveCount(24);
    Tenancy::clear();
});

it('lets a manager open the operations report', function () {
    $this->actingAs(opsUser('manager'))->get('/reports/operations')->assertOk()
        ->assertInertia(fn ($p) => $p->component('Reports/Operations')->has('ops.heatmap')->has('ops.median_first_response'));
});

it('forbids an agent from the operations report', function () {
    $this->actingAs(opsUser('agent'))->get('/reports/operations')->assertForbidden();
});

it('exports the operations channel breakdown as CSV', function () {
    $res = $this->actingAs(opsUser('manager'))->get('/reports/operations?export=csv');
    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('text/csv');
});

it('includes a 1–5 rating distribution in the CSAT report', function () {
    $ws = Workspace::create(['name' => 'CsatDist', 'plan' => 'business']);
    $owner = opsUser('owner', $ws);
    Tenancy::set($ws);
    $contact = Contact::create(['name' => 'C', 'phone' => '+1', 'channel' => 'whatsapp']);
    $conv = Conversation::create(['contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'resolved']);
    foreach ([5, 5, 4, 2] as $r) {
        CsatRating::create(['conversation_id' => $conv->id, 'agent_id' => $owner->id, 'channel' => 'whatsapp', 'rating' => $r, 'rated_at' => now()]);
    }
    Tenancy::clear();

    $this->actingAs($owner)->get('/reports/csat?range=90d')->assertOk()
        ->assertInertia(fn ($p) => $p->component('Reports/Csat')->has('report.distribution', 5)
            ->where('report.distribution.0.rating', 5)
            ->where('report.distribution.0.count', 2));
});
