<?php

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use App\Services\RoutingService;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::clear());

it('load-balances conversations across agents (M5)', function () {
    $ws = Workspace::create(['name' => 'Route WS']);
    Tenancy::set($ws);

    $agents = collect(['a', 'b', 'c'])->map(fn ($s) => User::create([
        'workspace_id' => $ws->id, 'name' => $s, 'email' => "$s@r.test", 'password' => bcrypt('x'), 'workspace_role' => 'agent',
    ]));

    $contact = Contact::create(['name' => 'C', 'phone' => '+1']);
    $routing = app(RoutingService::class);

    $assigned = [];
    for ($i = 0; $i < 3; $i++) {
        $conv = Conversation::create(['contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'open']);
        $agent = $routing->assign($conv);
        $assigned[] = $agent->id;
    }

    // Each of the three agents should have received exactly one conversation.
    expect(collect($assigned)->unique()->count())->toBe(3);
});

it('returns null when no agent is available', function () {
    $ws = Workspace::create(['name' => 'Empty WS']);
    Tenancy::set($ws);
    $contact = Contact::create(['name' => 'C', 'phone' => '+1']);
    $conv = Conversation::create(['contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'open']);

    expect(app(RoutingService::class)->assign($conv))->toBeNull();
});
