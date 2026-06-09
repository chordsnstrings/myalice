<?php

use App\Models\Chatbot;
use App\Models\User;
use App\Models\Workspace;

function botOwner(): array
{
    $ws = Workspace::create(['name' => 'Bot WS']);
    $user = User::create(['workspace_id' => $ws->id, 'name' => 'O', 'email' => 'bot@o.test', 'password' => bcrypt('x'), 'workspace_role' => 'owner']);

    return [$ws, $user];
}

it('publishes a valid flow', function () {
    [$ws, $user] = botOwner();
    $bot = Chatbot::create([
        'workspace_id' => $ws->id, 'name' => 'OK Bot', 'status' => 'draft',
        'graph' => ['nodes' => [
            ['id' => 'start', 'type' => 'start', 'next' => 'm'],
            ['id' => 'm', 'type' => 'message', 'next' => 'h'],
            ['id' => 'h', 'type' => 'handoff'],
        ]],
    ]);

    $this->actingAs($user)->post("/chatbots/{$bot->id}/publish")->assertRedirect();
    expect($bot->fresh()->status)->toBe('live');
});

it('blocks publishing a flow with a dead end (C-10)', function () {
    [$ws, $user] = botOwner();
    $bot = Chatbot::create([
        'workspace_id' => $ws->id, 'name' => 'Broken Bot', 'status' => 'draft',
        'graph' => ['nodes' => [
            ['id' => 'start', 'type' => 'start', 'next' => 'm'],
            ['id' => 'm', 'type' => 'message'], // dead end
        ]],
    ]);

    $this->actingAs($user)
        ->from("/chatbots/{$bot->id}/edit")
        ->post("/chatbots/{$bot->id}/publish")
        ->assertSessionHasErrors('flow');

    expect($bot->fresh()->status)->toBe('draft');
});
