<?php

use App\Models\Contact;
use App\Models\User;
use App\Models\Workspace;
use Laravel\Sanctum\Sanctum;

it('serves workspace-scoped contacts over the REST API', function () {
    $wsA = Workspace::create(['name' => 'API A']);
    $wsB = Workspace::create(['name' => 'API B']);
    $user = User::create(['workspace_id' => $wsA->id, 'name' => 'Dev', 'email' => 'dev@a.test', 'password' => bcrypt('x')]);

    Contact::create(['workspace_id' => $wsA->id, 'name' => 'Mine']);
    Contact::create(['workspace_id' => $wsB->id, 'name' => 'Theirs']);

    Sanctum::actingAs($user);

    $this->getJson('/api/contacts')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Mine');
});

it('rejects unauthenticated API calls', function () {
    $this->getJson('/api/contacts')->assertUnauthorized();
});
