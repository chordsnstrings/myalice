<?php

use App\Models\User;
use App\Models\Workspace;

it('shows the login screen to guests', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/Login'));
});

it('registers a brand and creates its workspace', function () {
    $this->post('/register', [
        'name' => 'Sam Patel',
        'workspace' => 'Bright Goods',
        'email' => 'sam@bright.test',
        'password' => 'secret-password',
    ])->assertRedirect('/inbox');

    $this->assertDatabaseHas('workspaces', ['name' => 'Bright Goods']);
    $this->assertAuthenticated();

    $user = User::where('email', 'sam@bright.test')->first();
    expect($user->workspace_role)->toBe('owner');
    expect($user->workspace_id)->not->toBeNull();
});

it('rejects bad credentials with a non-enumerating error (C-24)', function () {
    $ws = Workspace::create(['name' => 'WS']);
    User::create([
        'workspace_id' => $ws->id,
        'name' => 'A',
        'email' => 'a@a.test',
        'password' => bcrypt('correct-password'),
    ]);

    $this->from('/login')
        ->post('/login', ['email' => 'a@a.test', 'password' => 'wrong'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('redirects guests away from the inbox', function () {
    $this->get('/inbox')->assertRedirect('/login');
});

it('throttles repeated failed logins (C-24 brute-force protection)', function () {
    $payload = ['email' => 'victim@a.test', 'password' => 'wrong'];

    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', $payload);
    }

    // The 6th attempt within the window is rate-limited.
    $this->post('/login', $payload)->assertStatus(429);
});
