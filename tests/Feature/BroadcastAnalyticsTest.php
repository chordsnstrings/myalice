<?php

use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\ContactChannel;
use App\Models\Workspace;
use App\Services\AnalyticsService;
use App\Support\AnalyticsFilters;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::clear());

function bcRange(): AnalyticsFilters
{
    return new AnalyticsFilters(now()->subDays(7), now()->endOfDay(), '7d', null, null);
}

it('attributes an inbound reply to the broadcast that reached the contact', function () {
    $ws = Workspace::create(['name' => 'Attr WS']);
    Channel::create(['workspace_id' => $ws->id, 'type' => 'whatsapp', 'name' => 'WA', 'external_id' => 'PNA']);
    Tenancy::set($ws);
    $contact = Contact::create(['name' => 'Sam', 'phone' => '+15550100', 'channel' => 'whatsapp']);
    ContactChannel::create(['workspace_id' => $ws->id, 'contact_id' => $contact->id, 'channel' => 'whatsapp', 'external_id' => '+15550100', 'opted_in_at' => now()]);
    $b = Broadcast::create(['name' => 'B', 'channel' => 'whatsapp', 'status' => 'completed', 'replied' => 0]);
    $r = BroadcastRecipient::create(['workspace_id' => $ws->id, 'broadcast_id' => $b->id, 'contact_id' => $contact->id, 'channel' => 'whatsapp', 'external_id' => '+15550100', 'status' => 'delivered', 'sent_at' => now()->subHour()]);
    Tenancy::clear();

    // Inbound reply from that contact.
    $payload = ['object' => 'whatsapp_business_account', 'entry' => [[
        'id' => 'WABA', 'changes' => [['field' => 'messages', 'value' => [
            'metadata' => ['phone_number_id' => 'PNA'],
            'messages' => [['id' => 'wamid.reply', 'from' => '+15550100', 'type' => 'text', 'text' => ['body' => 'Yes please!']]],
        ]]],
    ]]];
    $this->postJson('/api/webhooks/whatsapp', $payload)->assertOk();

    expect($r->fresh()->status)->toBe('replied');
    expect($r->fresh()->replied_at)->not->toBeNull();
    expect($b->fresh()->replied)->toBe(1);
});

it('aggregates broadcast performance with a cumulative funnel', function () {
    $ws = Workspace::create(['name' => 'Perf WS']);
    Tenancy::set($ws);
    $b = Broadcast::create(['name' => 'B', 'channel' => 'whatsapp', 'status' => 'completed', 'spent_cost' => 5]);
    $mk = function (string $status) use ($ws, $b) {
        $contact = Contact::create(['name' => 'C', 'phone' => '+'.uniqid(), 'channel' => 'whatsapp'])->id;

        return BroadcastRecipient::create(['workspace_id' => $ws->id, 'broadcast_id' => $b->id, 'contact_id' => $contact, 'channel' => 'whatsapp', 'external_id' => uniqid(), 'status' => $status, 'sent_at' => now()]);
    };
    $mk('sent');
    $mk('delivered');
    $mk('read');
    $mk('replied');
    $mk('failed');

    $perf = app(AnalyticsService::class)->broadcastPerformance(bcRange());

    expect($perf['sent'])->toBe(4);        // sent+delivered+read+replied
    expect($perf['delivered'])->toBe(3);   // delivered+read+replied
    expect($perf['read'])->toBe(2);
    expect($perf['replied'])->toBe(1);
    expect($perf['failed'])->toBe(1);
    expect($perf['delivery_rate'])->toBe(75.0); // 3/4
    expect($perf['spend'])->toBe(5.0);
    Tenancy::clear();
});
