<?php

use App\Channels\ChannelManager;
use App\Jobs\ProcessInboundMessage;
use App\Jobs\SendCsatSurvey;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\CsatRating;
use App\Models\Workspace;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Queue;

afterEach(fn () => Tenancy::clear());

it('dispatches a CSAT survey when a conversation is resolved', function () {
    Queue::fake();
    $ws = Workspace::create(['name' => 'S', 'csat_enabled' => true]);
    Tenancy::set($ws);
    $contact = Contact::create(['name' => 'C', 'phone' => '+1'])->id;
    $c = Conversation::create(['contact_id' => $contact, 'channel' => 'whatsapp', 'status' => 'open']);

    $c->update(['status' => 'resolved']);

    Queue::assertPushed(SendCsatSurvey::class);
});

it('does not survey when CSAT is disabled for the workspace', function () {
    Queue::fake();
    $ws = Workspace::create(['name' => 'S2', 'csat_enabled' => false]);
    Tenancy::set($ws);
    $contact = Contact::create(['name' => 'C', 'phone' => '+1'])->id;
    $c = Conversation::create(['contact_id' => $contact, 'channel' => 'whatsapp', 'status' => 'open']);

    $c->update(['status' => 'resolved']);

    Queue::assertNotPushed(SendCsatSurvey::class);
});

it('records a rating from a numeric reply and does not reopen', function () {
    $ws = Workspace::create(['name' => 'S3']);
    Tenancy::set($ws);
    Channel::create(['workspace_id' => $ws->id, 'type' => 'whatsapp', 'name' => 'WA', 'external_id' => 'P1']);
    $contact = Contact::create(['name' => 'C', 'phone' => '15551112222', 'channel' => 'whatsapp']);
    $c = Conversation::create([
        'contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'resolved',
        'resolved_at' => now()->subMinutes(10), 'awaiting_csat_at' => now()->subMinutes(5),
    ]);

    (new ProcessInboundMessage($ws->id, 'whatsapp', ['from' => '15551112222', 'body' => '5']))->handle();

    expect(CsatRating::withoutGlobalScopes()->where('conversation_id', $c->id)->where('rating', 5)->exists())->toBeTrue();
    expect($c->fresh()->awaiting_csat_at)->toBeNull();
    expect($c->fresh()->status)->toBe('resolved'); // not reopened
});

it('treats a non-numeric reply as a normal message (reopens)', function () {
    $ws = Workspace::create(['name' => 'S4']);
    Tenancy::set($ws);
    Channel::create(['workspace_id' => $ws->id, 'type' => 'whatsapp', 'name' => 'WA', 'external_id' => 'P2']);
    $contact = Contact::create(['name' => 'C', 'phone' => '15553334444', 'channel' => 'whatsapp']);
    $c = Conversation::create([
        'contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'resolved',
        'resolved_at' => now()->subMinutes(10), 'awaiting_csat_at' => now()->subMinutes(5),
    ]);

    (new ProcessInboundMessage($ws->id, 'whatsapp', ['from' => '15553334444', 'body' => 'actually I have another question']))->handle();

    expect(CsatRating::withoutGlobalScopes()->where('conversation_id', $c->id)->exists())->toBeFalse();
    expect($c->fresh()->status)->toBe('open'); // reopened
});

it('the survey job marks the conversation awaiting a rating', function () {
    $ws = Workspace::create(['name' => 'S5', 'csat_enabled' => true]);
    Tenancy::set($ws);
    $contact = Contact::create(['name' => 'C', 'phone' => '15550000000', 'channel' => 'whatsapp']);
    $c = Conversation::create(['contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'resolved', 'resolved_at' => now()]);

    (new SendCsatSurvey($c->id))->handle(app(ChannelManager::class));

    expect($c->fresh()->awaiting_csat_at)->not->toBeNull();
});
