<?php

namespace App\Ai;

use App\Jobs\PlaceShopifyOrder;
use App\Models\AiAction;
use App\Models\AiAgent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Services\RoutingService;
use App\Services\ShopifyClient;
use Illuminate\Support\Carbon;
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

        // Pre-approved discounts (auto + autopilot). The model only asks for the
        // NEXT layer; the server picks and caps the actual concession.
        if ($agent->guardConfig()['discount']['enabled'] ?? false) {
            $tools[] = [
                'name' => 'offer_discount',
                'description' => 'Request the next pre-approved discount layer when the customer shows buying intent but hesitates (e.g. price objection). The system decides the exact, capped concession — you cannot choose the amount. Present what it returns; do not promise discounts otherwise.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'reason' => ['type' => 'string', 'description' => 'The hesitation signal you observed (for the audit log)'],
                ], 'required' => ['reason']],
            ];
        }

        if ($agent->mode === 'autopilot') {
            $tools[] = [
                'name' => 'create_order',
                'description' => 'Create a store order from catalog products once the customer agrees to buy. Set apply_offer=true to apply the discount you already offered via offer_discount.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'items' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                        'product_id' => ['type' => 'integer'],
                        'qty' => ['type' => 'integer'],
                    ], 'required' => ['product_id', 'qty']]],
                    'apply_offer' => ['type' => 'boolean', 'description' => 'Apply the latest non-expired discount offered in this chat'],
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
            'offer_discount' => $this->offerDiscount($call, $agent, $conversation),
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
            $lines[] = ['title' => $product->title, 'qty' => $qty, 'price' => (float) $product->price, 'type' => $product->type, 'external_id' => $product->external_id];
        }

        $subtotal = round($total, 2);

        // Apply a previously-offered, non-expired discount (server-computed only).
        $discountType = null;
        $discountAmount = 0.0;
        $shippingAmount = 0.0;
        if ($call->arguments['apply_offer'] ?? false) {
            [$discountType, $discountAmount, $shippingAmount] = $this->applyOffer($c, $agent, $lines, $subtotal);
        }

        $finalTotal = round($subtotal - $discountAmount + $shippingAmount, 2);

        $cap = $agent->guardConfig()['order_total_cap'];
        if ($cap !== null && $finalTotal > (float) $cap) {
            $this->handoff(new ToolCall($call->id, 'handoff_to_human', ['reason' => 'order over cap']), $agent, $c);

            return ['ok' => false, 'error' => 'Order exceeds the auto-approval limit; a teammate will confirm it.'];
        }

        $order = Order::create([
            'contact_id' => $c->contact_id,
            'number' => 'AI-'.strtoupper(Str::random(8)),
            'subtotal' => $subtotal,
            'discount_type' => $discountType,
            'discount_amount' => round($discountAmount, 2),
            'shipping_amount' => round($shippingAmount, 2),
            'total' => $finalTotal,
            'currency' => $currency,
            'status' => 'pending',
            'source' => 'chat',
            'line_items' => $lines,
        ]);

        // Mirror to Shopify (async) when a store is connected — never blocks the reply.
        if (app(ShopifyClient::class)->connected()) {
            $order->update(['external_status' => 'pending']);
            PlaceShopifyOrder::dispatch($order->workspace_id, $order->id);
        }

        $summary = collect($lines)->map(fn ($l) => "{$l['qty']}× {$l['title']}")->implode(', ');
        $discountNote = $discountAmount > 0 ? ' (−'.round($discountAmount, 2)." {$currency})" : '';
        Message::create([
            'conversation_id' => $c->id,
            'direction' => 'out',
            'author' => 'system',
            'body' => "Order {$order->number} created — {$summary} · {$finalTotal} {$currency}{$discountNote}",
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->log($agent, $c, 'create_order', [
            'order_id' => $order->id,
            'subtotal' => $subtotal,
            'discount_type' => $discountType,
            'discount_amount' => round($discountAmount, 2),
            'total' => $finalTotal,
        ]);

        return ['ok' => true, 'order_id' => $order->id, 'number' => $order->number, 'subtotal' => $subtotal, 'discount_amount' => round($discountAmount, 2), 'total' => $finalTotal, 'currency' => $currency];
    }

    /**
     * Hand out the next pre-approved discount layer for this conversation. The model
     * never names an amount — escalation index and caps are enforced here.
     *
     * @return array<string, mixed>
     */
    private function offerDiscount(ToolCall $call, AiAgent $agent, Conversation $c): array
    {
        $cfg = $agent->guardConfig()['discount'];
        if (! ($cfg['enabled'] ?? false)) {
            return ['ok' => false, 'error' => 'Discounts are not enabled.'];
        }

        $layers = array_values((array) ($cfg['layers'] ?? []));
        if ($layers === []) {
            return ['ok' => false, 'error' => 'No discount layers are configured.'];
        }

        // Escalation: the Nth offer in this conversation reveals the Nth layer.
        $index = AiAction::where('conversation_id', $c->id)->where('type', 'offer_discount')->where('status', 'ok')->count();
        if ($index >= count($layers)) {
            return ['ok' => false, 'exhausted' => true, 'error' => 'All discount layers have already been offered.'];
        }

        // once_per_contact: don't start a fresh discount ladder for someone who was
        // already offered one in another conversation.
        if (($cfg['once_per_contact'] ?? true) && $index === 0 && $c->contact_id) {
            $otherConvIds = Conversation::where('contact_id', $c->contact_id)->where('id', '!=', $c->id)->pluck('id');
            if ($otherConvIds->isNotEmpty()
                && AiAction::whereIn('conversation_id', $otherConvIds)->where('type', 'offer_discount')->where('status', 'ok')->exists()) {
                return ['ok' => false, 'error' => 'This customer has already received a discount offer.'];
            }
        }

        $layer = $layers[$index];
        $maxPercent = (float) ($cfg['max_percent'] ?? 15);
        $type = $layer['type'] ?? 'cart_percent';
        $ttl = (int) ($cfg['offer_ttl_minutes'] ?? 60);
        $expiresAt = now()->addMinutes($ttl);

        [$value, $description] = match ($type) {
            'free_shipping' => [(float) ($cfg['shipping_fee'] ?? 0), 'free shipping on this order'],
            'service_percent' => [
                $v = min((float) ($cfg['service_percent'] ?? 0), $maxPercent),
                $this->pct($v).'% off services',
            ],
            default => [
                $v = min((float) ($layer['value'] ?? 0), $maxPercent),
                $this->pct($v).'% off your order',
            ],
        };

        $payload = ['layer' => $index, 'type' => $type, 'value' => $value, 'expires_at' => $expiresAt->toIso8601String(), 'reason' => $call->arguments['reason'] ?? null];
        $this->log($agent, $c, 'offer_discount', $payload);

        return [
            'ok' => true,
            'type' => $type,
            'value' => $value,
            'description' => $description,
            'expires_at' => $expiresAt->toIso8601String(),
            'expires_in_minutes' => $ttl,
            'authority_ok' => true, // the prompt may now use the management-approval frame
        ];
    }

    /**
     * Resolve the latest valid offer for this conversation into a concrete
     * discount against the cart. Returns [discount_type, discount_amount, shipping_amount].
     *
     * @param  list<array<string, mixed>>  $lines
     * @return array{0:string|null,1:float,2:float}
     */
    private function applyOffer(Conversation $c, AiAgent $agent, array $lines, float $subtotal): array
    {
        $cfg = $agent->guardConfig()['discount'];
        if (! ($cfg['enabled'] ?? false) || $subtotal < (float) ($cfg['min_order_value'] ?? 0)) {
            return [null, 0.0, 0.0];
        }

        $offer = AiAction::where('conversation_id', $c->id)->where('type', 'offer_discount')->where('status', 'ok')->latest('id')->first();
        if (! $offer) {
            return [null, 0.0, 0.0];
        }
        $payload = $offer->payload ?? [];
        if (empty($payload['expires_at']) || now()->greaterThan(Carbon::parse($payload['expires_at']))) {
            return [null, 0.0, 0.0]; // expired offers are never honoured
        }

        $maxPercent = (float) ($cfg['max_percent'] ?? 15);
        $value = min((float) ($payload['value'] ?? 0), $maxPercent);

        return match ($payload['type'] ?? null) {
            'free_shipping' => ['free_shipping', 0.0, 0.0], // no shipping is charged on chat orders today
            'cart_percent' => ['cart_percent', round($subtotal * $value / 100, 2), 0.0],
            'service_percent' => ['service_percent', round($this->serviceSubtotal($lines) * $value / 100, 2), 0.0],
            default => [null, 0.0, 0.0],
        };
    }

    /**
     * Sum of the service-type line items (for the pre-approved service %).
     *
     * @param  list<array<string, mixed>>  $lines
     */
    private function serviceSubtotal(array $lines): float
    {
        $sum = 0.0;
        foreach ($lines as $l) {
            if (($l['type'] ?? 'product') === 'service') {
                $sum += (float) $l['price'] * (int) $l['qty'];
            }
        }

        return $sum;
    }

    /** Format a percentage without trailing zeros (10.0 -> "10", 7.5 -> "7.5"). */
    private function pct(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
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
