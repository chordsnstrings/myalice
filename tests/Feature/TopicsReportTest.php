<?php

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AnalyticsService;
use App\Support\AnalyticsFilters;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;

afterEach(fn () => Tenancy::clear());

function topicUser(Workspace $ws, string $role): User
{
    return User::create(['workspace_id' => $ws->id, 'name' => ucfirst($role), 'email' => $role.uniqid().'@t.test', 'password' => bcrypt('x'), 'workspace_role' => $role]);
}

it('aggregates topics with volume, share, resolution and coverage', function () {
    $ws = Workspace::create(['name' => 'TopWS', 'plan' => 'business']);
    Tenancy::set($ws);
    $contact = Contact::create(['name' => 'C', 'phone' => '+1', 'channel' => 'whatsapp']);
    $shipping = Tag::create(['name' => 'Shipping', 'color' => 'info']);
    $returns = Tag::create(['name' => 'Returns', 'color' => 'warning']);

    $c1 = Conversation::create(['contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'resolved']);
    Conversation::withoutGlobalScopes()->where('id', $c1->id)->update(['resolved_at' => now()]);
    $c1->tags()->attach($shipping->id);
    $c2 = Conversation::create(['contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'open']);
    $c2->tags()->attach([$shipping->id, $returns->id]);
    Conversation::create(['contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'open']); // untagged

    $f = new AnalyticsFilters(Carbon::now('UTC')->subDays(3), Carbon::now('UTC')->addDay(), '7d');
    $t = app(AnalyticsService::class)->topics($f);

    expect($t['total'])->toBe(3);
    expect($t['tagged'])->toBe(2);
    expect($t['untagged'])->toBe(1);
    expect($t['coverage'])->toBe(66.7);

    $byName = collect($t['tags'])->keyBy('name');
    expect($byName['Shipping']['count'])->toBe(2);
    expect($byName['Shipping']['resolution_rate'])->toBe(50.0); // c1 resolved, c2 not
    expect($byName['Returns']['count'])->toBe(1);
    Tenancy::clear();
});

it('tags and untags a conversation from the inbox', function () {
    $ws = Workspace::create(['name' => 'TagWS', 'plan' => 'business']);
    $u = topicUser($ws, 'agent');
    Tenancy::set($ws);
    $contact = Contact::create(['name' => 'C', 'phone' => '+1', 'channel' => 'whatsapp']);
    $conv = Conversation::create(['contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'open']);
    Tenancy::clear();

    $this->actingAs($u)->postJson("/conversations/{$conv->id}/tags", ['name' => 'Shipping'])
        ->assertOk()->assertJsonPath('tag.name', 'Shipping');

    Tenancy::set($ws);
    expect($conv->tags()->count())->toBe(1);
    $tagId = $conv->tags()->first()->id;
    Tenancy::clear();

    $this->actingAs($u)->deleteJson("/conversations/{$conv->id}/tags/{$tagId}")->assertOk();
    Tenancy::set($ws);
    expect($conv->fresh()->tags()->count())->toBe(0);
    Tenancy::clear();
});

it('does not create duplicate tags on the same conversation', function () {
    $ws = Workspace::create(['name' => 'DupWS', 'plan' => 'business']);
    $u = topicUser($ws, 'agent');
    Tenancy::set($ws);
    $contact = Contact::create(['name' => 'C', 'phone' => '+1', 'channel' => 'whatsapp']);
    $conv = Conversation::create(['contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'open']);
    Tenancy::clear();

    $this->actingAs($u)->postJson("/conversations/{$conv->id}/tags", ['name' => 'Shipping']);
    $this->actingAs($u)->postJson("/conversations/{$conv->id}/tags", ['name' => 'Shipping']);

    Tenancy::set($ws);
    expect($conv->tags()->count())->toBe(1);
    expect(Tag::where('name', 'Shipping')->count())->toBe(1);
    Tenancy::clear();
});

it('lets a manager open the topics report and export CSV', function () {
    $ws = Workspace::create(['name' => 'TopRep', 'plan' => 'business']);
    $mgr = topicUser($ws, 'manager');

    $this->actingAs($mgr)->get('/reports/topics')->assertOk()
        ->assertInertia(fn ($p) => $p->component('Reports/Topics')->has('topics.tags')->has('topics.coverage'));

    $res = $this->actingAs($mgr)->get('/reports/topics?export=csv');
    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('text/csv');
});

it('forbids an agent from the topics report', function () {
    $ws = Workspace::create(['name' => 'TopGate', 'plan' => 'business']);
    $this->actingAs(topicUser($ws, 'agent'))->get('/reports/topics')->assertForbidden();
});
