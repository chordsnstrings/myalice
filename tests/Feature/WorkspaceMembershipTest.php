<?php

use App\Models\User;
use App\Models\Workspace;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::clear());

function wsMember(Workspace $ws, string $role = 'owner', ?string $email = null): User
{
    $user = User::create([
        'workspace_id' => $ws->id,
        'name' => ucfirst($role).' '.uniqid(),
        'email' => $email ?? $role.'-'.uniqid().'@ws.test',
        'password' => bcrypt('secret'),
        'workspace_role' => $role,
    ]);
    $ws->members()->attach($user->id, ['workspace_role' => $role]);

    return $user;
}

it('attaches the owner as a member on registration', function () {
    $this->post('/register', [
        'name' => 'Alex', 'workspace' => 'Acme', 'email' => 'alex@reg.test', 'password' => 'password123',
    ])->assertRedirect('/inbox');

    $user = User::firstWhere('email', 'alex@reg.test');
    expect($user->workspaces()->count())->toBe(1);
    expect($user->roleIn((int) $user->workspace_id))->toBe('owner');
});

it('switches the active workspace for a member and updates the role', function () {
    $a = Workspace::create(['name' => 'A']);
    $b = Workspace::create(['name' => 'B']);
    $user = wsMember($a, 'owner', 'multi@ws.test');
    $b->members()->attach($user->id, ['workspace_role' => 'manager']);

    $this->actingAs($user)->post("/workspaces/{$b->id}/switch")->assertRedirect('/inbox');

    $user->refresh();
    expect($user->workspace_id)->toBe($b->id);
    expect($user->workspace_role)->toBe('manager');
});

it('forbids switching to a workspace you do not belong to', function () {
    $a = Workspace::create(['name' => 'A']);
    $other = Workspace::create(['name' => 'Other']);
    $user = wsMember($a, 'owner');

    $this->actingAs($user)->post("/workspaces/{$other->id}/switch")->assertForbidden();
    expect($user->fresh()->workspace_id)->toBe($a->id);
});

it('creates a new workspace and makes the user its owner + active', function () {
    $a = Workspace::create(['name' => 'A']);
    $user = wsMember($a, 'agent');

    $this->actingAs($user)->post('/workspaces', ['name' => 'Northwind'])->assertRedirect('/inbox');

    $user->refresh();
    $new = Workspace::where('name', 'Northwind')->first();
    expect($new)->not->toBeNull();
    expect($user->workspace_id)->toBe($new->id);
    expect($user->workspace_role)->toBe('owner');
    expect($user->workspaces()->count())->toBe(2);
});

it('lists only the current workspace members on the team page', function () {
    $a = Workspace::create(['name' => 'A', 'plan' => 'business']);
    $b = Workspace::create(['name' => 'B', 'plan' => 'business']);
    $owner = wsMember($a, 'owner', 'owner@team.test');
    wsMember($b, 'owner', 'elsewhere@team.test');

    $this->actingAs($owner)->get('/settings/team')->assertOk()
        ->assertInertia(fn ($p) => $p->component('Settings/Team')->has('members', 1)->where('members.0.email', 'owner@team.test'));
});

it('adds an existing user to the workspace', function () {
    $a = Workspace::create(['name' => 'A', 'plan' => 'business']);
    $b = Workspace::create(['name' => 'B', 'plan' => 'business']);
    $owner = wsMember($a, 'owner');
    $existing = wsMember($b, 'owner', 'existing@team.test');

    $this->actingAs($owner)->post('/settings/team', ['email' => 'existing@team.test', 'role' => 'agent'])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($a->members()->whereKey($existing->id)->exists())->toBeTrue();
    expect($existing->fresh()->workspaces()->count())->toBe(2);
});

it('creates and adds a brand-new teammate', function () {
    $a = Workspace::create(['name' => 'A', 'plan' => 'business']);
    $owner = wsMember($a, 'owner');

    $this->actingAs($owner)->post('/settings/team', ['email' => 'fresh@team.test', 'name' => 'Fresh', 'role' => 'manager'])
        ->assertRedirect()->assertSessionHasNoErrors();

    $u = User::firstWhere('email', 'fresh@team.test');
    expect($u)->not->toBeNull();
    expect($a->members()->whereKey($u->id)->exists())->toBeTrue();
});

it('rejects adding someone already in the workspace', function () {
    $a = Workspace::create(['name' => 'A', 'plan' => 'business']);
    $owner = wsMember($a, 'owner', 'dup@team.test');

    $this->actingAs($owner)->post('/settings/team', ['email' => 'dup@team.test', 'role' => 'agent'])
        ->assertSessionHasErrors('email');
});

it('removes a teammate from the workspace', function () {
    $a = Workspace::create(['name' => 'A', 'plan' => 'business']);
    $owner = wsMember($a, 'owner');
    $agent = wsMember($a, 'agent');

    $this->actingAs($owner)->delete("/settings/team/{$agent->id}")->assertRedirect();
    expect($a->members()->whereKey($agent->id)->exists())->toBeFalse();
});

it('will not remove the last owner', function () {
    $a = Workspace::create(['name' => 'A', 'plan' => 'business']);
    $owner = wsMember($a, 'owner');
    $manager = wsMember($a, 'manager');

    $this->actingAs($manager)->delete("/settings/team/{$owner->id}")->assertSessionHasErrors('member');
    expect($a->members()->whereKey($owner->id)->exists())->toBeTrue();
});

it('forbids removing yourself', function () {
    $a = Workspace::create(['name' => 'A', 'plan' => 'business']);
    $owner = wsMember($a, 'owner');

    $this->actingAs($owner)->delete("/settings/team/{$owner->id}")->assertForbidden();
});

it('shares the user workspaces for the switcher', function () {
    $a = Workspace::create(['name' => 'A']);
    $b = Workspace::create(['name' => 'B']);
    $user = wsMember($a, 'owner');
    $b->members()->attach($user->id, ['workspace_role' => 'agent']);

    $this->actingAs($user)->get('/inbox')
        ->assertInertia(fn ($p) => $p->has('auth.workspaces', 2));
});
