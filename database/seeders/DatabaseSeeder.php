<?php

namespace Database\Seeders;

use App\Models\Audience;
use App\Models\AutomationRule;
use App\Models\Broadcast;
use App\Models\Channel;
use App\Models\Chatbot;
use App\Models\Contact;
use App\Models\Conversation;
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
            ['name' => 'Acme DTC'],
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
            ['type' => 'whatsapp', 'name' => 'Acme WhatsApp', 'status' => 'connected'],
            ['type' => 'instagram', 'name' => '@acme', 'status' => 'connected'],
            ['type' => 'messenger', 'name' => 'Acme Page', 'status' => 'connected'],
            ['type' => 'web', 'name' => 'Storefront widget', 'status' => 'connected'],
            ['type' => 'telegram', 'name' => 'Acme Bot', 'status' => 'action_needed'],
        ]));

        foreach (['VIP' => 'info', 'Returning' => 'neutral', 'Lead' => 'accent', 'Wholesale' => 'warning'] as $name => $color) {
            Tag::create(['name' => $name, 'color' => $color]);
        }

        foreach ([
            ['/thanks', 'Thanks for reaching out! How can I help today?'],
            ['/shipping', 'We ship worldwide. Domestic orders arrive in 2–4 days.'],
            ['/returns', 'You can return any item within 30 days for a full refund.'],
        ] as [$s, $b]) {
            QuickReply::create(['shortcut' => $s, 'body' => $b]);
        }

        StoreConnection::create(['platform' => 'shopify', 'store_url' => 'acme.myshopify.com', 'status' => 'connected', 'last_synced_at' => now()->subMinutes(8)]);

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

        Chatbot::create(['name' => 'FAQ Assistant', 'status' => 'live', 'graph' => ['nodes' => [], 'edges' => []]]);
        Chatbot::create(['name' => 'Lead Capture', 'status' => 'draft', 'graph' => ['nodes' => [], 'edges' => []]]);

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
