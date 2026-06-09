<?php

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\MetricSnapshot;
use App\Models\Workspace;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::clear());

it('builds idempotent per-workspace snapshots whose sums match the data', function () {
    $ws = Workspace::create(['name' => 'Snap', 'timezone' => 'UTC']);
    Tenancy::set($ws);
    $contact = Contact::create(['name' => 'C', 'phone' => '+1'])->id;

    $day = now()->subDay();
    foreach (range(1, 3) as $i) {
        $c = Conversation::create(['contact_id' => $contact, 'channel' => 'whatsapp', 'status' => 'resolved', 'resolved_at' => $day, 'first_response_at' => $day]);
        Conversation::where('id', $c->id)->update(['created_at' => $day]);
    }
    Tenancy::clear();

    $this->artisan('analytics:snapshot', ['--day' => $day->format('Y-m-d')])->assertSuccessful();
    $this->artisan('analytics:snapshot', ['--day' => $day->format('Y-m-d')])->assertSuccessful();

    // All-up rollup row for that day.
    $rollup = MetricSnapshot::withoutGlobalScopes()
        ->where('workspace_id', $ws->id)->whereNull('channel')->whereNull('agent_id')
        ->whereDate('day', $day->format('Y-m-d'))->get();

    expect($rollup)->toHaveCount(1);              // idempotent (not duplicated)
    expect($rollup->first()->conversations)->toBe(3);
    expect($rollup->first()->resolved)->toBe(3);
});

it('keeps snapshots isolated per workspace', function () {
    $a = Workspace::create(['name' => 'A', 'timezone' => 'UTC']);
    $b = Workspace::create(['name' => 'B', 'timezone' => 'UTC']);
    $day = now()->subDay();

    Tenancy::set($a);
    $ca = Contact::create(['name' => 'CA', 'phone' => '+1'])->id;
    $c = Conversation::create(['contact_id' => $ca, 'channel' => 'whatsapp', 'status' => 'open']);
    Conversation::where('id', $c->id)->update(['created_at' => $day]);
    Tenancy::clear();

    $this->artisan('analytics:snapshot', ['--day' => $day->format('Y-m-d')])->assertSuccessful();

    expect(MetricSnapshot::withoutGlobalScopes()->where('workspace_id', $a->id)->whereNull('channel')->whereNull('agent_id')->sum('conversations'))->toBe(1);
    expect(MetricSnapshot::withoutGlobalScopes()->where('workspace_id', $b->id)->whereNull('channel')->whereNull('agent_id')->sum('conversations'))->toBe(0);
});
