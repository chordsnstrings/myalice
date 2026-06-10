<?php

use App\Models\Chatbot;
use App\Models\User;
use App\Models\Workspace;

function userWithRole(string $role): User
{
    $ws = Workspace::create(['name' => "WS-$role"]);

    return User::create([
        'workspace_id' => $ws->id,
        'name' => ucfirst($role),
        'email' => "$role@rbac.test",
        'password' => bcrypt('x'),
        'workspace_role' => $role,
    ]);
}

it('lets an owner reach billing', function () {
    $this->actingAs(userWithRole('owner'))
        ->get('/settings/billing')
        ->assertOk();
});

it('blocks an agent from billing (§4.3 / C-17)', function () {
    $this->actingAs(userWithRole('agent'))
        ->get('/settings/billing')
        ->assertForbidden();
});

it('blocks an agent from the developer settings', function () {
    $this->actingAs(userWithRole('agent'))
        ->get('/settings/developer')
        ->assertForbidden();
});

it('lets a developer reach the developer settings', function () {
    $this->actingAs(userWithRole('developer'))
        ->get('/settings/developer')
        ->assertOk();
});

it('blocks an agent from creating a broadcast (wallet spend)', function () {
    $agent = userWithRole('agent');
    $this->actingAs($agent)->get('/broadcasts/create')->assertForbidden();
    $this->actingAs($agent)->post('/broadcasts', [
        'name' => 'X', 'recipients' => 1, 'credit_cost' => 0.1,
    ])->assertForbidden();
});

it('lets a manager open the broadcast composer', function () {
    $this->actingAs(userWithRole('manager'))->get('/broadcasts/create')->assertOk();
});

it('blocks an agent from editing or publishing chatbots', function () {
    $agent = userWithRole('agent');
    $bot = Chatbot::create(['workspace_id' => $agent->workspace_id, 'name' => 'B', 'status' => 'draft', 'graph' => ['nodes' => []]]);
    $this->actingAs($agent)->get("/chatbots/{$bot->id}/edit")->assertForbidden();
    $this->actingAs($agent)->post("/chatbots/{$bot->id}/publish")->assertForbidden();
});

it('restricts team and channel settings to managers', function () {
    $agent = userWithRole('agent');
    $this->actingAs($agent)->get('/settings/team')->assertForbidden();
    $this->actingAs($agent)->get('/settings/channels')->assertForbidden();

    $manager = userWithRole('manager');
    $this->actingAs($manager)->get('/settings/team')->assertOk();
    $this->actingAs($manager)->get('/settings/channels')->assertOk();
});

it('shares role capabilities to the front end', function () {
    $this->actingAs(userWithRole('agent'))
        ->get('/settings')
        ->assertInertia(fn ($page) => $page
            ->where('auth.can.manage_billing', false)
            ->where('auth.can.manage_team', false)
        );
});
