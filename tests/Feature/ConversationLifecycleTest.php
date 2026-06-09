<?php

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->ws = Workspace::create(['name' => 'L']);
    Tenancy::set($this->ws);
    $this->contact = Contact::create(['name' => 'C', 'phone' => '+1'])->id;
});

afterEach(fn () => Tenancy::clear());

function conv(int $contact, array $attrs = []): Conversation
{
    return Conversation::create(array_merge(['contact_id' => $contact, 'channel' => 'whatsapp', 'status' => 'open'], $attrs));
}

it('stamps first_response_at on the first outbound agent message only', function () {
    $c = conv($this->contact);

    Message::create(['conversation_id' => $c->id, 'direction' => 'out', 'author' => 'agent', 'body' => 'Hi', 'sent_at' => now()]);
    $first = $c->fresh()->first_response_at;
    expect($first)->not->toBeNull();

    Message::create(['conversation_id' => $c->id, 'direction' => 'out', 'author' => 'agent', 'body' => 'Again', 'sent_at' => now()->addMinutes(5)]);
    expect($c->fresh()->first_response_at->equalTo($first))->toBeTrue();
});

it('does not stamp first_response_at for bot or customer messages', function () {
    $c = conv($this->contact);

    Message::create(['conversation_id' => $c->id, 'direction' => 'in', 'author' => 'customer', 'body' => 'Hi', 'sent_at' => now()]);
    Message::create(['conversation_id' => $c->id, 'direction' => 'out', 'author' => 'bot', 'body' => 'Auto', 'sent_at' => now()]);

    expect($c->fresh()->first_response_at)->toBeNull();
});

it('stamps resolved_at on resolve and clears it on reopen', function () {
    Queue::fake();
    $c = conv($this->contact);

    $c->update(['status' => 'resolved']);
    expect($c->fresh()->resolved_at)->not->toBeNull();

    $c->update(['status' => 'open']);
    expect($c->fresh()->resolved_at)->toBeNull();
});

it('stamps assigned_at when an assignee is first set', function () {
    $user = User::create(['workspace_id' => $this->ws->id, 'name' => 'A', 'email' => 'a@l.test', 'password' => bcrypt('x'), 'workspace_role' => 'agent']);
    $c = conv($this->contact);

    $c->update(['assignee_id' => $user->id]);
    expect($c->fresh()->assigned_at)->not->toBeNull();
});
