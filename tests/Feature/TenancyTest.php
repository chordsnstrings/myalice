<?php

use App\Models\Contact;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::clear());

it('isolates tenant data via the workspace global scope (G0.3)', function () {
    $a = Workspace::create(['name' => 'Workspace A']);
    $b = Workspace::create(['name' => 'Workspace B']);

    // Seed contacts in both workspaces (no scope active yet).
    Contact::create(['workspace_id' => $a->id, 'name' => 'Anna']);
    Contact::create(['workspace_id' => $a->id, 'name' => 'Amir']);
    Contact::create(['workspace_id' => $b->id, 'name' => 'Bob']);

    // Inside workspace A, only A's contacts are visible.
    Tenancy::set($a);
    expect(Contact::count())->toBe(2);
    expect(Contact::pluck('name')->all())->not->toContain('Bob');

    // Switching tenant flips the scope — no cross-tenant leakage.
    Tenancy::set($b);
    expect(Contact::count())->toBe(1);
    expect(Contact::first()->name)->toBe('Bob');
});

it('auto-fills workspace_id from the active tenant on create', function () {
    $ws = Workspace::create(['name' => 'Auto WS']);
    Tenancy::set($ws);

    $contact = Contact::create(['name' => 'No Workspace Given']);

    expect($contact->workspace_id)->toBe($ws->id);
});

it('binds the active workspace for authenticated requests', function () {
    $ws = Workspace::create(['name' => 'Bound WS', 'wallet_balance' => 42]);
    $user = User::create(['workspace_id' => $ws->id, 'name' => 'U', 'email' => 'u@u.test', 'password' => bcrypt('x')]);

    $this->actingAs($user)
        ->get('/inbox')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Inbox/Index'));
});

it('never leaks one workspace\'s contacts into another (HTTP layer)', function () {
    $wsA = Workspace::create(['name' => 'A']);
    $wsB = Workspace::create(['name' => 'B']);
    $userA = User::create(['workspace_id' => $wsA->id, 'name' => 'A', 'email' => 'a@a.test', 'password' => bcrypt('x')]);

    Contact::create(['workspace_id' => $wsA->id, 'name' => 'Alice Only']);
    Contact::create(['workspace_id' => $wsB->id, 'name' => 'Bob Only']);

    $this->actingAs($userA)
        ->get('/contacts')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Contacts/Index')
            ->has('contacts', 1)
            ->where('contacts.0.name', 'Alice Only')
        );
});
