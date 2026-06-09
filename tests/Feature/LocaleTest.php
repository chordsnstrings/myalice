<?php

use App\Models\User;
use App\Models\Workspace;

it('switches locale and shares Arabic translations', function () {
    $ws = Workspace::create(['name' => 'Locale WS']);
    $user = User::create(['workspace_id' => $ws->id, 'name' => 'L', 'email' => 'l@l.test', 'password' => bcrypt('x'), 'workspace_role' => 'owner']);

    $this->actingAs($user)->post('/locale', ['locale' => 'ar'])->assertRedirect();

    $this->actingAs($user)
        ->get('/inbox')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('locale', 'ar')
            ->where('translations', fn ($t) => collect($t)->get('nav.inbox') === 'الوارد')
        );
});

it('rejects an unsupported locale', function () {
    $ws = Workspace::create(['name' => 'WS']);
    $user = User::create(['workspace_id' => $ws->id, 'name' => 'L', 'email' => 'l2@l.test', 'password' => bcrypt('x'), 'workspace_role' => 'owner']);

    $this->actingAs($user)->post('/locale', ['locale' => 'zz'])->assertSessionHasErrors('locale');
});
