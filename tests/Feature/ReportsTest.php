<?php

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;

function reportUser(string $role, ?Workspace $ws = null): User
{
    $ws ??= Workspace::create(['name' => "WS-$role-".uniqid(), 'plan' => 'business']);

    return User::create([
        'workspace_id' => $ws->id, 'name' => ucfirst($role), 'email' => $role.uniqid().'@r.test',
        'password' => bcrypt('x'), 'workspace_role' => $role,
    ]);
}

it('lets managers and owners open every report', function (string $url, string $component) {
    $this->actingAs(reportUser('manager'))
        ->get($url)->assertOk()
        ->assertInertia(fn ($p) => $p->component($component));
})->with([
    ['/reports/agents', 'Reports/AgentPerformance'],
    ['/reports/sales', 'Reports/Sales'],
    ['/reports/csat', 'Reports/Csat'],
]);

it('forbids agents from reports (manager gate)', function (string $url) {
    $this->actingAs(reportUser('agent'))->get($url)->assertForbidden();
})->with(['/reports/agents', '/reports/sales', '/reports/csat']);

it('404s a cross-workspace agent drill-down', function () {
    $owner = reportUser('owner');
    $otherAgent = reportUser('agent'); // different workspace

    $this->actingAs($owner)->get("/reports/agents/{$otherAgent->id}")->assertNotFound();
});

it('never leaks another workspace into the leaderboard', function () {
    $wsA = Workspace::create(['name' => 'A', 'plan' => 'business']);
    $owner = reportUser('owner', $wsA);
    $agentA = reportUser('agent', $wsA);
    $cA = Contact::create(['workspace_id' => $wsA->id, 'name' => 'CA', 'phone' => '+1']);
    Conversation::create(['workspace_id' => $wsA->id, 'contact_id' => $cA->id, 'channel' => 'whatsapp', 'status' => 'open', 'assignee_id' => $agentA->id]);

    $wsB = Workspace::create(['name' => 'B', 'plan' => 'business']);
    $agentB = reportUser('agent', $wsB);
    $cB = Contact::create(['workspace_id' => $wsB->id, 'name' => 'CB', 'phone' => '+2']);
    Conversation::create(['workspace_id' => $wsB->id, 'contact_id' => $cB->id, 'channel' => 'whatsapp', 'status' => 'open', 'assignee_id' => $agentB->id]);

    $this->actingAs($owner)->get('/reports/agents?range=90d')
        ->assertInertia(fn ($p) => $p
            ->component('Reports/AgentPerformance')
            ->has('leaderboard', 1)
            ->where('leaderboard.0.name', $agentA->name)
        );
});

it('streams a CSV export', function () {
    $this->actingAs(reportUser('owner'))
        ->get('/reports/agents?export=csv')
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});
