<?php

use App\Models\User;
use App\Models\Workspace;
use App\Support\Plans;

it('maps plans to cumulative features (§10)', function () {
    expect(Plans::includes('business', 'automation'))->toBeTrue();
    expect(Plans::includes('premium', 'automation'))->toBeFalse();
    expect(Plans::includes('premium', 'broadcasts'))->toBeTrue();
    expect(Plans::includes('enterprise', 'automation'))->toBeTrue();
    expect(Plans::includes('enterprise', 'llm'))->toBeTrue();
    expect(Plans::includes('business', 'llm'))->toBeFalse();
    expect(Plans::includes('business', 'ai_agents'))->toBeTrue();
    expect(Plans::includes('premium', 'ai_agents'))->toBeFalse();
});

function planUser(string $plan): User
{
    $ws = Workspace::create(['name' => "WS-$plan", 'plan' => $plan]);

    return User::create([
        'workspace_id' => $ws->id, 'name' => 'U', 'email' => "u-$plan@plan.test",
        'password' => bcrypt('x'), 'workspace_role' => 'owner',
    ]);
}

it('lets a Business workspace open Automations', function () {
    $this->actingAs(planUser('business'))->get('/automations')->assertOk();
});

it('blocks a Premium workspace from Automations and shares the locked feature set (C-17)', function () {
    $this->actingAs(planUser('premium'))->get('/automations')->assertForbidden();
});

it('shares the unlocked feature list to the front end', function () {
    $this->actingAs(planUser('premium'))
        ->get('/inbox')
        ->assertInertia(fn ($page) => $page->where('auth.features', fn ($f) => collect($f)->doesntContain('automation')));
});
