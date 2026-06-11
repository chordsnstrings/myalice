<?php

use App\Models\Chatbot;
use App\Models\User;
use App\Models\Workspace;

function builderOwner(): array
{
    $ws = Workspace::create(['name' => 'Builder WS', 'plan' => 'business']);
    $user = User::create(['workspace_id' => $ws->id, 'name' => 'O', 'email' => 'b-'.uniqid().'@o.test', 'password' => bcrypt('x'), 'workspace_role' => 'owner']);

    return [$ws, $user];
}

it('loads the persisted graph (normalised) + live issues into the builder', function () {
    [$ws, $user] = builderOwner();
    $bot = Chatbot::create([
        'workspace_id' => $ws->id, 'name' => 'My Bot', 'status' => 'draft',
        'graph' => ['nodes' => [['id' => 'start', 'type' => 'start']]], // no x/y/label, dead end
    ]);

    $this->actingAs($user)->get("/chatbots/{$bot->id}/edit")->assertOk()
        ->assertInertia(fn ($p) => $p->component('Chatbots/Builder')
            ->where('graph.nodes.0.id', 'start')
            ->where('graph.nodes.0.label', 'Start')   // back-filled
            ->where('graph.nodes.0.x', 40)            // back-filled position
            ->has('issues')                            // validation surfaced
        );
});

it('persists an edited graph via autosave and returns validation', function () {
    [$ws, $user] = builderOwner();
    $bot = Chatbot::create(['workspace_id' => $ws->id, 'name' => 'Edit Bot', 'status' => 'draft', 'graph' => ['nodes' => []]]);

    $graph = ['nodes' => [
        ['id' => 'start', 'type' => 'start', 'label' => 'Start', 'x' => 40, 'y' => 40, 'next' => 'm'],
        ['id' => 'm', 'type' => 'message', 'label' => 'Welcome', 'text' => 'Hi!', 'x' => 40, 'y' => 150, 'next' => 'h'],
        ['id' => 'h', 'type' => 'handoff', 'label' => 'Handoff', 'x' => 40, 'y' => 260],
    ]];

    $this->actingAs($user)->putJson("/chatbots/{$bot->id}", ['graph' => $graph])
        ->assertOk()->assertJson(['ok' => true, 'issues' => []]);

    expect($bot->fresh()->graph['nodes'])->toHaveCount(3);
    expect($bot->fresh()->graph['nodes'][1]['text'])->toBe('Hi!');
});

it('returns validation issues for a flow with a dead end', function () {
    [$ws, $user] = builderOwner();
    $bot = Chatbot::create(['workspace_id' => $ws->id, 'name' => 'Bad Bot', 'status' => 'draft', 'graph' => ['nodes' => []]]);

    $graph = ['nodes' => [
        ['id' => 'start', 'type' => 'start', 'next' => 'm'],
        ['id' => 'm', 'type' => 'message'], // dead end → error
    ]];

    $res = $this->actingAs($user)->putJson("/chatbots/{$bot->id}", ['graph' => $graph])->assertOk();
    expect(collect($res->json('issues'))->where('severity', 'error'))->not->toBeEmpty();
});

it('rejects a malformed graph', function () {
    [$ws, $user] = builderOwner();
    $bot = Chatbot::create(['workspace_id' => $ws->id, 'name' => 'X', 'status' => 'draft', 'graph' => ['nodes' => []]]);

    $this->actingAs($user)->putJson("/chatbots/{$bot->id}", ['graph' => ['nodes' => [['type' => 'message']]]])
        ->assertStatus(422); // missing node id
});

it('forbids a non-manager from saving the flow', function () {
    $ws = Workspace::create(['name' => 'G WS', 'plan' => 'business']);
    $agent = User::create(['workspace_id' => $ws->id, 'name' => 'A', 'email' => 'a@g.test', 'password' => bcrypt('x'), 'workspace_role' => 'agent']);
    $bot = Chatbot::create(['workspace_id' => $ws->id, 'name' => 'X', 'status' => 'draft', 'graph' => ['nodes' => []]]);

    $this->actingAs($agent)->putJson("/chatbots/{$bot->id}", ['graph' => ['nodes' => []]])->assertForbidden();
});
