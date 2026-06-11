<?php

use App\Jobs\SendOutboundMessage;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->ws = Workspace::create(['name' => 'Inbox WS', 'plan' => 'business']);
    Tenancy::set($this->ws);
    $this->agent = User::create(['workspace_id' => $this->ws->id, 'name' => 'Agent', 'email' => 'agent@inbox.test', 'password' => bcrypt('x'), 'workspace_role' => 'agent']);
    $this->contact = Contact::create(['name' => 'Cust', 'phone' => '+1700', 'channel' => 'web', 'lifecycle_stage' => 'lead']);
});

afterEach(fn () => Tenancy::clear());

function inboxConv(int $contact, array $attrs = []): Conversation
{
    return Conversation::create(array_merge(['contact_id' => $contact, 'channel' => 'web', 'status' => 'open', 'window_open' => true], $attrs));
}

it('persists a human reply inside the 24h window and takes the chat off the AI', function () {
    $c = inboxConv($this->contact->id, ['ai_status' => 'active']);

    $this->actingAs($this->agent)
        ->postJson("/conversations/{$c->id}/messages", ['body' => 'Hi there!'])
        ->assertOk()
        ->assertJsonPath('message.author', 'agent')
        ->assertJsonPath('message.body', 'Hi there!');

    $msg = Message::where('conversation_id', $c->id)->where('author', 'agent')->first();
    expect($msg)->not->toBeNull();
    expect($c->fresh()->ai_status)->toBe('suppressed');
    expect($c->fresh()->last_message)->toBe('Hi there!');
});

it('dispatches the connector for a supported channel', function () {
    Queue::fake();
    $wa = Contact::create(['name' => 'WA', 'phone' => '+1999', 'channel' => 'whatsapp', 'lifecycle_stage' => 'lead']);
    $c = inboxConv($wa->id, ['channel' => 'whatsapp']);

    $this->actingAs($this->agent)->postJson("/conversations/{$c->id}/messages", ['body' => 'yo'])->assertOk();

    Queue::assertPushed(SendOutboundMessage::class);
});

it('blocks a free-text reply outside the window', function () {
    $c = inboxConv($this->contact->id, ['window_open' => false]);

    $this->actingAs($this->agent)
        ->postJson("/conversations/{$c->id}/messages", ['body' => 'late reply'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('template_id');

    expect(Message::where('conversation_id', $c->id)->where('author', 'agent')->exists())->toBeFalse();
});

it('allows an approved template outside the window', function () {
    $c = inboxConv($this->contact->id, ['window_open' => false]);
    $tpl = MessageTemplate::create(['workspace_id' => $this->ws->id, 'name' => 'reorder', 'category' => 'marketing', 'language' => 'en', 'body' => 'Time to reorder?', 'approval_status' => 'approved', 'quality' => 'green']);

    $this->actingAs($this->agent)
        ->postJson("/conversations/{$c->id}/messages", ['template_id' => $tpl->id])
        ->assertOk()
        ->assertJsonPath('message.body', 'Time to reorder?');
});

it('rejects an unapproved template outside the window', function () {
    $c = inboxConv($this->contact->id, ['window_open' => false]);
    $tpl = MessageTemplate::create(['workspace_id' => $this->ws->id, 'name' => 'draft', 'category' => 'marketing', 'language' => 'en', 'body' => 'x', 'approval_status' => 'pending', 'quality' => 'unknown']);

    $this->actingAs($this->agent)
        ->postJson("/conversations/{$c->id}/messages", ['template_id' => $tpl->id])
        ->assertStatus(422);
});

it('resolves and reopens a conversation', function () {
    Queue::fake();
    $c = inboxConv($this->contact->id);

    $this->actingAs($this->agent)->put("/conversations/{$c->id}/resolve")->assertRedirect();
    expect($c->fresh()->status)->toBe('resolved');
    expect($c->fresh()->resolved_at)->not->toBeNull();

    $this->actingAs($this->agent)->put("/conversations/{$c->id}/resolve")->assertRedirect();
    expect($c->fresh()->status)->toBe('open');
    expect($c->fresh()->resolved_at)->toBeNull();
});

it('resumes the AI on a handed-off conversation', function () {
    $c = inboxConv($this->contact->id, ['ai_status' => 'handed_off']);

    $this->actingAs($this->agent)->put("/conversations/{$c->id}/resume-ai")->assertRedirect();

    expect($c->fresh()->ai_status)->toBe('active');
    expect($c->fresh()->ai_resumed_at)->not->toBeNull();
});

it('assigns and unassigns a conversation', function () {
    $c = inboxConv($this->contact->id);

    $this->actingAs($this->agent)->put("/conversations/{$c->id}/assign", ['assignee_id' => $this->agent->id])->assertRedirect();
    expect($c->fresh()->assignee_id)->toBe($this->agent->id);
    expect($c->fresh()->assigned_at)->not->toBeNull();

    $this->actingAs($this->agent)->put("/conversations/{$c->id}/assign", ['assignee_id' => null])->assertRedirect();
    expect($c->fresh()->assignee_id)->toBeNull();
});

it('rejects assigning to a user from another workspace', function () {
    $c = inboxConv($this->contact->id);
    $other = Workspace::create(['name' => 'Other']);
    $outsider = User::create(['workspace_id' => $other->id, 'name' => 'Out', 'email' => 'out@x.test', 'password' => bcrypt('x'), 'workspace_role' => 'agent']);

    $this->actingAs($this->agent)->put("/conversations/{$c->id}/assign", ['assignee_id' => $outsider->id])->assertStatus(422);
    expect($c->fresh()->assignee_id)->toBeNull();
});

it('cannot reply to another workspace conversation (tenant isolation)', function () {
    $other = Workspace::create(['name' => 'Other2', 'plan' => 'business']);
    Tenancy::set($other);
    $oc = Conversation::create(['contact_id' => Contact::create(['name' => 'X', 'phone' => '+2', 'channel' => 'web'])->id, 'channel' => 'web', 'status' => 'open', 'window_open' => true]);
    Tenancy::set($this->ws);

    $this->actingAs($this->agent)->postJson("/conversations/{$oc->id}/messages", ['body' => 'hi'])->assertNotFound();
});
