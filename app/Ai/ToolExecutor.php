<?php

namespace App\Ai;

use App\Models\AiAction;
use App\Models\AiAgent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Services\RoutingService;
use Illuminate\Support\Str;

/**
 * Defines the agent's tools and executes them server-side. Every tool effect is
 * validated against tenant data (prices, stock, caps) so even a jailbroken model
 * cannot mint discounts or cross-tenant orders. Each execution is logged.
 */
class ToolExecutor
{
    public function __construct(
        private RoutingService $routing,
        private PaymentLinkGenerator $payments,
    ) {}

    /**
     * Tool specs available for the agent's mode. suggest/auto get lead+handoff;
     * autopilot additionally gets order + payment link.
     *
     * @return list<array<string, mixed>>
     */
    public function definitions(AiAgent $agent): array
    {
        $tools = [
            [
                'name' => 'create_lead',
                'description' => 'Qualify and capture this contact as a sales lead, notifying the team.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'interest' => ['type' => 'string', 'description' => 'What the customer is interested in'],
                    'notes' => ['type' => 'string', 'description' => 'Qualification notes'],
                ]],
            ],
            [
                'name' => 'handoff_to_human',
                'description' => 'Hand the conversation to a human agent.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'reason' => ['type' => 'string', 'description' => 'Why a human is needed'],
                ], 'required' => ['reason']],
            ],
        ];

        if ($agent->mode === 'autopilot') {
            $tools[] = [
                'name' => 'create_order',
                'description' => 'Create a store order from catalog products once the customer agrees to buy.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'items' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                        'product_id' => ['type' => 'integer'],
                        'qty' => ['type' => 'integer'],
                    ], 'required' => ['product_id', 'qty']]],
                ], 'required' => ['items']],
            ];
            $tools[] = [
                'name' => 'send_payment_link',
                'description' => 'Send a checkout/payment link for an existing order.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'order_id' => ['type' => 'integer'],
                ], 'required' => ['order_id']],
            ];
        }

        return $tools;
    }

    /**
     * Execute a tool call. Returns a result array fed back to the model.
     *
     * @return array<string, mixed>
     */
    public function execute(ToolCall $call, AiAgent $agent, Conversation $conversation): array
    {
        return match ($call->name) {
            'create_lead' => $this->createLead($call, $agent, $conversation),
            'create_order' => $this->createOrder($call, $agent, $conversation),
            'send_payment_link' => $this->sendPaymentLink($call, $agent, $conversation),
            'handoff_to_human' => $this->handoff($call, $agent, $conversation),
            default => ['ok' => false, 'error' => 'unknown tool'],
        };
    }

    /** @return array<string, mixed> */
    private function createLead(ToolCall $call, AiAgent $agent, Conversation $c): array
    {
        $contact = $c->contact;
        if ($contact->lifecycle_stage !== 'customer') {
            $contact->lifecycle_stage = 'lead';
        }
        $tags = (array) ($contact->tags ?? []);
        if (! in_array('ai-lead', $tags, true)) {
            $tags[] = 'ai-lead';
        }
        $contact->tags = $tags;
        $contact->save();
        $c->update(['unread' => $c->unread + 1]);

        $this->log($agent, $c, 'create_lead', ['interest' => $call->arguments['interest'] ?? null]);

        return ['ok' => true, 'message' => 'Lead captured; the team has been notified.'];
    }

    /** @return array<string, mixed> */
    private function createOrder(ToolCall $call, AiAgent $agent, Conversation $c): array
    {
        $items = $call->arguments['items'] ?? [];
        if (! is_array($items) || $items === []) {
            return ['ok' => false, 'error' => 'No items provided.'];
        }

        $lines = [];
        $total = 0.0;
        $currency = 'USD';
        foreach ($items as $item) {
            $product = Product::find($item['product_id'] ?? 0); // tenant-scoped
            $qty = max(1, (int) ($item['qty'] ?? 1));
            if (! $product) {
                return ['ok' => false, 'error' => "Product {$item['product_id']} not found."];
            }
            if ($product->stock < $qty) {
                return ['ok' => false, 'error' => "Only {$product->stock} of {$product->title} in stock."];
            }
            $total += (float) $product->price * $qty; // price from DB, never the model
            $currency = $product->currency;
            $lines[] = ['title' => $product->title, 'qty' => $qty, 'price' => (float) $product->price];
        }

        $cap = $agent->guardConfig()['order_total_cap'];
        if ($cap !== null && $total > (float) $cap) {
            $this->handoff(new ToolCall($call->id, 'handoff_to_human', ['reason' => 'order over cap']), $agent, $c);

            return ['ok' => false, 'error' => 'Order exceeds the auto-approval limit; a teammate will confirm it.'];
        }

        $order = Order::create([
            'contact_id' => $c->contact_id,
            'number' => 'AI-'.strtoupper(Str::random(8)),
            'total' => round($total, 2),
            'currency' => $currency,
            'status' => 'pending',
            'source' => 'chat',
            'line_items' => $lines,
        ]);

        $summary = collect($lines)->map(fn ($l) => "{$l['qty']}× {$l['title']}")->implode(', ');
        Message::create([
            'conversation_id' => $c->id,
            'direction' => 'out',
            'author' => 'system',
            'body' => "Order {$order->number} created — {$summary} · ".round($total, 2)." {$currency}",
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->log($agent, $c, 'create_order', ['order_id' => $order->id, 'total' => round($total, 2)]);

        return ['ok' => true, 'order_id' => $order->id, 'number' => $order->number, 'total' => round($total, 2), 'currency' => $currency];
    }

    /** @return array<string, mixed> */
    private function sendPaymentLink(ToolCall $call, AiAgent $agent, Conversation $c): array
    {
        $order = Order::find($call->arguments['order_id'] ?? 0);
        if (! $order) {
            return ['ok' => false, 'error' => 'Order not found.'];
        }

        $url = $this->payments->generate($order);
        if (! $url) {
            $this->log($agent, $c, 'send_payment_link', ['order_id' => $order->id], 'failed');

            return ['ok' => false, 'error' => 'Online payments are not enabled yet; a teammate will share payment instructions.'];
        }

        $this->log($agent, $c, 'send_payment_link', ['order_id' => $order->id, 'url' => $url]);

        return ['ok' => true, 'url' => $url];
    }

    /** @return array<string, mixed> */
    private function handoff(ToolCall $call, AiAgent $agent, Conversation $c): array
    {
        $c->update(['ai_status' => 'handed_off', 'unread' => $c->unread + 1]);
        if (! $c->assignee_id) {
            $this->routing->assign($c);
        }
        $this->log($agent, $c, 'handoff', ['reason' => $call->arguments['reason'] ?? null]);

        return ['ok' => true, 'message' => 'Handed off to a human teammate.'];
    }

    /** @param  array<string, mixed>  $payload */
    private function log(AiAgent $agent, Conversation $c, string $type, array $payload, string $status = 'ok'): void
    {
        AiAction::create([
            'conversation_id' => $c->id,
            'ai_agent_id' => $agent->id,
            'type' => $type,
            'payload' => $payload,
            'status' => $status,
            'created_at' => now(),
        ]);
    }
}
