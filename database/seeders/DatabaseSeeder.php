<?php

namespace Database\Seeders;

use App\Models\Audience;
use App\Models\AutomationRule;
use App\Models\Broadcast;
use App\Models\Channel;
use App\Models\Chatbot;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\CsatRating;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Order;
use App\Models\Product;
use App\Models\QuickReply;
use App\Models\StoreConnection;
use App\Models\Subscription;
use App\Models\Tag;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\Workspace;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $workspace = Workspace::firstOrCreate(
            ['name' => 'ARKS DTC'],
            ['plan' => 'business', 'wallet_balance' => 128.50, 'currency' => 'USD', 'timezone' => 'Asia/Dubai'],
        );

        $owner = User::firstOrCreate(
            ['email' => 'demo@myalice.test'],
            [
                'workspace_id' => $workspace->id,
                'name' => 'Alex Morgan',
                'password' => Hash::make('password'),
                'workspace_role' => 'owner',
            ],
        );
        $owner->syncRoles('owner');

        foreach (['Maya Osei' => 'maya', 'Sara Lopez' => 'sara', 'Omar Aziz' => 'omar'] as $name => $slug) {
            $agent = User::firstOrCreate(
                ['email' => "$slug@myalice.test"],
                ['workspace_id' => $workspace->id, 'name' => $name, 'password' => Hash::make('password'), 'workspace_role' => 'agent'],
            );
            $agent->syncRoles('agent');
        }

        // Scope all subsequent tenant-owned creates to this workspace.
        Tenancy::set($workspace);

        if (Contact::count() > 0) {
            return; // already seeded
        }

        Channel::insert(array_map(fn ($c) => array_merge($c, ['workspace_id' => $workspace->id, 'created_at' => now(), 'updated_at' => now()]), [
            ['type' => 'whatsapp', 'name' => 'ARKS WhatsApp', 'external_id' => '100000000000001', 'status' => 'connected'],
            ['type' => 'instagram', 'name' => '@arks', 'external_id' => '200000000000002', 'status' => 'connected'],
            ['type' => 'messenger', 'name' => 'ARKS Page', 'external_id' => '300000000000003', 'status' => 'connected'],
            ['type' => 'web', 'name' => 'Storefront widget', 'external_id' => null, 'status' => 'connected'],
            ['type' => 'telegram', 'name' => 'ARKS Bot', 'external_id' => null, 'status' => 'action_needed'],
        ]));

        foreach (['VIP' => 'info', 'Returning' => 'neutral', 'Lead' => 'accent', 'Wholesale' => 'warning'] as $name => $color) {
            Tag::create(['name' => $name, 'color' => $color]);
        }

        // Topic tags — "what conversations are about" — power the Topics report.
        foreach (['Shipping' => 'info', 'Returns' => 'warning', 'Sizing' => 'accent', 'Order status' => 'success', 'Payment' => 'danger'] as $name => $color) {
            Tag::create(['name' => $name, 'color' => $color, 'kind' => 'topic']);
        }
        $topicTagIds = Tag::whereIn('name', ['Shipping', 'Returns', 'Sizing', 'Order status', 'Payment'])->pluck('id')->all();

        foreach ([
            ['/thanks', 'Thanks for reaching out! How can I help today?'],
            ['/shipping', 'We ship worldwide. Domestic orders arrive in 2–4 days.'],
            ['/returns', 'You can return any item within 30 days for a full refund.'],
        ] as [$s, $b]) {
            QuickReply::create(['shortcut' => $s, 'body' => $b]);
        }

        StoreConnection::create(['platform' => 'shopify', 'store_url' => 'arks.myshopify.com', 'status' => 'connected', 'last_synced_at' => now()->subMinutes(8)]);

        $products = collect([
            ['Linen Abaya — Navy', 84, 24], ['Silk Hijab — Rose', 29, 120], ['Cotton Kaftan — Sand', 65, 0],
            ['Embroidered Dress — Black', 129, 12], ['Leather Sandals', 49, 38], ['Gold Hoop Earrings', 35, 64],
        ])->map(fn ($p) => Product::create(['title' => $p[0], 'price' => $p[1], 'stock' => $p[2], 'source' => 'shopify']));

        $channels = ['whatsapp', 'instagram', 'messenger', 'web', 'telegram'];
        $lifecycles = ['Lead', 'Customer', 'VIP', 'Returning'];
        $names = ['Layla Hassan', 'Marcus Reed', 'Priya Nair', 'João Silva', 'Sara Khan', 'Daniel Cohen', 'Aisha Rahman', 'Liam Walsh', 'Fatima Noor', 'Diego Torres', 'Yuki Tanaka', 'Nora Berg'];

        foreach ($names as $i => $name) {
            $contact = Contact::create([
                'name' => $name,
                'phone' => '+9715'.rand(10000000, 99999999),
                'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
                'channel' => $channels[$i % count($channels)],
                'lifecycle_stage' => $lifecycles[$i % count($lifecycles)],
                'tags' => $i % 3 === 0 ? ['VIP', 'Returning'] : ['Lead'],
            ]);

            $conv = Conversation::create([
                'contact_id' => $contact->id,
                'channel' => $contact->channel,
                'status' => $i % 4 === 0 ? 'resolved' : ($i % 3 === 0 ? 'pending' : 'open'),
                'assignee_id' => $i % 2 === 0 ? 1 : null,
                'unread' => $i % 3,
                'window_open' => $i % 5 !== 0,
                'sla_breaching' => $i % 6 === 0,
                'last_message' => 'Thanks for the quick reply!',
                'last_message_at' => now()->subMinutes($i * 11 + 3),
            ]);

            Message::create(['conversation_id' => $conv->id, 'direction' => 'in', 'author' => 'customer', 'body' => 'Hi! Is this available?', 'sent_at' => now()->subMinutes($i * 11 + 20)]);
            Message::create(['conversation_id' => $conv->id, 'direction' => 'out', 'author' => 'agent', 'body' => 'Yes — in stock now.', 'status' => 'read', 'sent_at' => now()->subMinutes($i * 11 + 15)]);
            Message::create(['conversation_id' => $conv->id, 'direction' => 'in', 'author' => 'customer', 'body' => 'Thanks for the quick reply!', 'sent_at' => now()->subMinutes($i * 11 + 3)]);

            if (rand(1, 100) <= 75) {
                $conv->tags()->attach(collect($topicTagIds)->random(rand(1, 2))->all());
            }

            if ($i % 2 === 0) {
                Order::create([
                    'contact_id' => $contact->id,
                    'number' => '#'.(1100 + $i),
                    'total' => $products->random()->price,
                    'status' => ['pending', 'paid', 'fulfilled', 'delivered'][$i % 4],
                    'source' => 'chat',
                    'line_items' => [['title' => $products->random()->title, 'qty' => 1]],
                ]);
            }
        }

        // ---- Historical spread for analytics (90 days, agent-skewed) ----
        $contactIds = Contact::pluck('id')->all();
        $agentIds = User::where('workspace_id', $workspace->id)->orderBy('id')->pluck('id')->all();
        // Per-agent skew: [base response seconds, csat bias] — differentiates the leaderboard.
        $skew = [];
        foreach (array_values($agentIds) as $idx => $aid) {
            $skew[$aid] = [60 + $idx * 90, 5 - $idx * 0.4]; // first agent fastest / highest CSAT
        }
        $comments = [
            'Super fast and helpful, thank you!', 'Sorted my issue in minutes.', 'Great service as always.',
            'Took a while but got there.', 'Friendly and clear.', 'Could have been quicker.',
            'Loved the product recommendation.', 'Resolved my order problem perfectly.',
        ];
        $orderSeq = 2000;

        for ($n = 0; $n < 220; $n++) {
            $createdAt = now()->subDays(rand(0, 89))->subMinutes(rand(0, 1439));
            $channel = $channels[array_rand($channels)];
            $agentId = $agentIds[array_rand($agentIds)];
            [$base, $csatBias] = $skew[$agentId];

            $roll = rand(1, 100);
            $status = $roll <= 60 ? 'resolved' : ($roll <= 85 ? 'open' : 'pending');

            $responseSecs = max(20, (int) ($base + rand(-40, 600)));
            $firstResponseAt = $createdAt->clone()->addSeconds($responseSecs);
            $resolvedAt = $status === 'resolved' ? $createdAt->clone()->addSeconds(rand(3600, 172800)) : null;
            $lastAt = $resolvedAt ?? $firstResponseAt;

            $conv = Conversation::create([
                'contact_id' => $contactIds[array_rand($contactIds)],
                'channel' => $channel,
                'status' => $status,
                'assignee_id' => $agentId,
                'assigned_at' => $createdAt->clone()->addSeconds(rand(0, 300)),
                'first_response_at' => $firstResponseAt,
                'resolved_at' => $resolvedAt,
                'window_open' => $status !== 'resolved',
                'last_message' => 'Thanks!',
                'last_message_at' => $lastAt,
            ]);
            // Back-date created_at (query update bypasses observers/timestamps).
            Conversation::where('id', $conv->id)->update(['created_at' => $createdAt, 'updated_at' => $lastAt]);

            if (rand(1, 100) <= 78) {
                $conv->tags()->attach(collect($topicTagIds)->random(rand(1, 2))->all());
            }

            Message::create(['conversation_id' => $conv->id, 'direction' => 'in', 'author' => 'customer', 'body' => 'Hello, I need help.', 'sent_at' => $createdAt]);
            Message::create(['conversation_id' => $conv->id, 'direction' => 'out', 'author' => 'agent', 'body' => 'Happy to help!', 'status' => 'read', 'sent_at' => $firstResponseAt]);

            // CSAT for ~70% of resolved, rating biased by agent.
            if ($resolvedAt && rand(1, 100) <= 70) {
                $rating = (int) max(1, min(5, round($csatBias + (rand(-15, 10) / 10))));
                CsatRating::create([
                    'conversation_id' => $conv->id,
                    'agent_id' => $agentId,
                    'channel' => $channel,
                    'rating' => $rating,
                    'comment' => rand(1, 100) <= 30 ? $comments[array_rand($comments)] : null,
                    'rated_at' => $resolvedAt->clone()->addSeconds(rand(300, 3600)),
                ]);
            }

            // ~35% of resolved produce a chat order in range.
            if ($resolvedAt && rand(1, 100) <= 35) {
                $product = $products->random();
                $order = Order::create([
                    'contact_id' => $conv->contact_id,
                    'number' => '#'.(++$orderSeq),
                    'total' => $product->price,
                    'status' => ['paid', 'fulfilled', 'delivered'][rand(0, 2)],
                    'source' => 'chat',
                    'line_items' => [['title' => $product->title, 'qty' => 1]],
                ]);
                Order::where('id', $order->id)->update(['created_at' => $resolvedAt, 'updated_at' => $resolvedAt]);
            }
        }

        foreach ([
            ['Cart Reminder', 'utility', 'approved', null, 'green', 'Hi {{1}}, you left items in your cart 🛒'],
            ['Order Confirmation', 'utility', 'approved', null, 'green', 'Your order {{1}} is confirmed!'],
            ['Eid Sale', 'marketing', 'pending', null, 'green', 'Eid Mubarak! Enjoy 25% off this week.'],
            ['Winback', 'marketing', 'rejected', 'Promotional content in a utility category.', 'red', 'We miss you — here is 15% off.'],
        ] as [$n, $cat, $st, $reason, $quality, $body]) {
            MessageTemplate::create(['name' => $n, 'category' => $cat, 'approval_status' => $st, 'body' => $body, 'rejection_reason' => $reason, 'quality' => $quality]);
        }

        Audience::create(['name' => 'VIP customers', 'type' => 'dynamic', 'size' => 1840]);
        Audience::create(['name' => 'Lapsed 90 days', 'type' => 'dynamic', 'size' => 3120]);

        $tpl = MessageTemplate::where('name', 'Eid Sale')->first();
        Broadcast::create(['name' => 'Eid Sale Blast', 'message_template_id' => $tpl?->id, 'status' => 'scheduled', 'schedule_at' => now()->addDay(), 'credit_cost' => 152.40, 'recipients' => 12210]);
        Broadcast::create(['name' => 'New Arrivals', 'status' => 'sent', 'credit_cost' => 88.10, 'recipients' => 8800, 'delivered' => 8740, 'read' => 6120, 'replied' => 410]);

        $validGraph = ['nodes' => [
            ['id' => 'start', 'type' => 'start', 'next' => 'welcome'],
            ['id' => 'welcome', 'type' => 'message', 'next' => 'ask'],
            ['id' => 'ask', 'type' => 'buttons', 'fallback' => 'handoff', 'next' => 'handoff'],
            ['id' => 'handoff', 'type' => 'handoff'],
        ]];
        Chatbot::create(['name' => 'FAQ Assistant', 'status' => 'live', 'graph' => $validGraph]);
        Chatbot::create(['name' => 'Lead Capture', 'status' => 'draft', 'graph' => $validGraph]);

        AutomationRule::create(['name' => 'Abandoned cart — 1h', 'trigger_type' => 'abandoned_cart', 'status' => 'active', 'sent' => 1204, 'recovered_revenue' => 9120]);
        AutomationRule::create(['name' => 'Order confirmation', 'trigger_type' => 'order_confirmation', 'status' => 'active', 'sent' => 980]);
        AutomationRule::create(['name' => 'Shipping update', 'trigger_type' => 'shipping', 'status' => 'paused', 'sent' => 640]);

        Subscription::create(['plan' => 'business', 'billing_cycle' => 'monthly', 'seats' => 5, 'status' => 'active', 'renews_at' => now()->addDays(18)]);

        $balance = 0;
        foreach ([
            ['credit', 200, 'Top-up'],
            ['debit', 88.10, 'Broadcast: New Arrivals'],
            ['debit', 12.40, 'Conversations (utility)'],
            ['credit', 50, 'Trial credit'],
            ['debit', 21.00, 'Conversations (marketing)'],
        ] as [$type, $amt, $desc]) {
            $balance += $type === 'credit' ? $amt : -$amt;
            WalletTransaction::create(['type' => $type, 'amount' => $amt, 'balance_after' => $balance, 'description' => $desc]);
        }

        Tenancy::clear();
    }
}
