<?php

use App\Ai\ToolCall;
use App\Ai\ToolExecutor;
use App\Models\AiAction;
use App\Models\AiAgent;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\Workspace;
use App\Support\Tenancy;

beforeEach(function () {
    $this->ws = Workspace::create(['name' => 'Tools WS', 'plan' => 'business']);
    Tenancy::set($this->ws);

    $this->agent = AiAgent::create([
        'workspace_id' => $this->ws->id, 'name' => 'A', 'enabled' => true, 'mode' => 'autopilot',
        'goal' => 'sale', 'channel_scope' => 'all', 'tone' => 'friendly', 'methodology' => 'consultative_spin',
    ]);
    $this->contact = Contact::create(['name' => 'Cust', 'phone' => '+300', 'channel' => 'web', 'lifecycle_stage' => 'lead']);
    $this->conv = Conversation::create(['contact_id' => $this->contact->id, 'channel' => 'web', 'status' => 'open', 'window_open' => true]);
    $this->exec = app(ToolExecutor::class);
});

afterEach(fn () => Tenancy::clear());

it('create_lead tags the contact and never downgrades a customer', function () {
    $this->contact->update(['lifecycle_stage' => 'customer']);

    $this->exec->execute(new ToolCall('1', 'create_lead', ['interest' => 'mugs']), $this->agent, $this->conv);

    $fresh = $this->contact->fresh();
    expect($fresh->lifecycle_stage)->toBe('customer'); // forward-only
    expect($fresh->tags)->toContain('ai-lead');
    expect(AiAction::where('type', 'create_lead')->exists())->toBeTrue();
});

it('create_order prices from the DB, checks stock, and writes a system message', function () {
    $p = Product::create(['workspace_id' => $this->ws->id, 'title' => 'Blue Mug', 'price' => 12.50, 'currency' => 'USD', 'stock' => 5]);

    $result = $this->exec->execute(
        new ToolCall('1', 'create_order', ['items' => [['product_id' => $p->id, 'qty' => 2]]]),
        $this->agent, $this->conv,
    );

    expect($result['ok'])->toBeTrue();
    $order = Order::first();
    expect((float) $order->total)->toBe(25.0); // 2 × 12.50 from DB, not model
    expect($order->source)->toBe('chat');
    expect(Message::where('author', 'system')->where('conversation_id', $this->conv->id)->exists())->toBeTrue();
});

it('create_order rejects quantities beyond available stock', function () {
    $p = Product::create(['workspace_id' => $this->ws->id, 'title' => 'Rare', 'price' => 5, 'currency' => 'USD', 'stock' => 1]);

    $result = $this->exec->execute(
        new ToolCall('1', 'create_order', ['items' => [['product_id' => $p->id, 'qty' => 3]]]),
        $this->agent, $this->conv,
    );

    expect($result['ok'])->toBeFalse();
    expect(Order::count())->toBe(0);
});

it('create_order over the cap hands off instead of ordering', function () {
    $this->agent->update(['guardrails' => ['order_total_cap' => 50]]);
    $p = Product::create(['workspace_id' => $this->ws->id, 'title' => 'Pricey', 'price' => 100, 'currency' => 'USD', 'stock' => 5]);

    $result = $this->exec->execute(
        new ToolCall('1', 'create_order', ['items' => [['product_id' => $p->id, 'qty' => 1]]]),
        $this->agent, $this->conv,
    );

    expect($result['ok'])->toBeFalse();
    expect(Order::count())->toBe(0);
    expect($this->conv->fresh()->ai_status)->toBe('handed_off');
});

it('send_payment_link logs a failed action when payments are not enabled', function () {
    $order = Order::create(['workspace_id' => $this->ws->id, 'contact_id' => $this->contact->id, 'number' => 'AI-X', 'total' => 10, 'currency' => 'USD', 'status' => 'pending', 'source' => 'chat']);

    $result = $this->exec->execute(new ToolCall('1', 'send_payment_link', ['order_id' => $order->id]), $this->agent, $this->conv);

    expect($result['ok'])->toBeFalse();
    expect(AiAction::where('type', 'send_payment_link')->where('status', 'failed')->exists())->toBeTrue();
});

it('handoff_to_human marks the conversation handed off', function () {
    $this->exec->execute(new ToolCall('1', 'handoff_to_human', ['reason' => 'refund request']), $this->agent, $this->conv);

    expect($this->conv->fresh()->ai_status)->toBe('handed_off');
    expect(AiAction::where('type', 'handoff')->exists())->toBeTrue();
});

function enableDiscount(AiAgent $agent, array $overrides = []): void
{
    $agent->update(['guardrails' => ['discount' => array_merge([
        'enabled' => true,
        'layers' => [['type' => 'free_shipping'], ['type' => 'cart_percent', 'value' => 5], ['type' => 'cart_percent', 'value' => 50]],
        'max_percent' => 15,
        'offer_ttl_minutes' => 60,
        'once_per_contact' => true,
    ], $overrides)]]);
}

it('offer_discount escalates layer by layer and caps the percentage', function () {
    enableDiscount($this->agent);

    $first = $this->exec->execute(new ToolCall('1', 'offer_discount', ['reason' => 'price objection']), $this->agent, $this->conv);
    expect($first['type'])->toBe('free_shipping');
    expect($first['authority_ok'])->toBeTrue();

    $second = $this->exec->execute(new ToolCall('2', 'offer_discount', ['reason' => 'still unsure']), $this->agent, $this->conv);
    expect($second['type'])->toBe('cart_percent');
    expect($second['value'])->toBe(5.0);

    $third = $this->exec->execute(new ToolCall('3', 'offer_discount', ['reason' => 'pushing']), $this->agent, $this->conv);
    expect($third['value'])->toBe(15.0); // 50 capped at max_percent 15

    $fourth = $this->exec->execute(new ToolCall('4', 'offer_discount', ['reason' => 'more']), $this->agent, $this->conv);
    expect($fourth['ok'])->toBeFalse();
    expect($fourth['exhausted'])->toBeTrue();
});

it('once_per_contact blocks a fresh discount ladder in another conversation', function () {
    enableDiscount($this->agent);
    $this->exec->execute(new ToolCall('1', 'offer_discount', ['reason' => 'x']), $this->agent, $this->conv);

    $conv2 = Conversation::create(['contact_id' => $this->contact->id, 'channel' => 'web', 'status' => 'open', 'window_open' => true]);
    $result = $this->exec->execute(new ToolCall('2', 'offer_discount', ['reason' => 'y']), $this->agent, $conv2);

    expect($result['ok'])->toBeFalse();
});

it('create_order applies a previously offered cart discount, server-computed', function () {
    enableDiscount($this->agent, ['layers' => [['type' => 'cart_percent', 'value' => 10]]]);
    $p = Product::create(['workspace_id' => $this->ws->id, 'title' => 'Mug', 'price' => 100, 'currency' => 'USD', 'stock' => 5]);
    $this->exec->execute(new ToolCall('1', 'offer_discount', ['reason' => 'price']), $this->agent, $this->conv);

    $result = $this->exec->execute(
        new ToolCall('2', 'create_order', ['items' => [['product_id' => $p->id, 'qty' => 2]], 'apply_offer' => true]),
        $this->agent, $this->conv,
    );

    expect($result['ok'])->toBeTrue();
    $order = Order::first();
    expect((float) $order->subtotal)->toBe(200.0);
    expect((float) $order->discount_amount)->toBe(20.0); // 10% of 200
    expect((float) $order->total)->toBe(180.0);
    expect($order->discount_type)->toBe('cart_percent');
});

it('ignores an expired discount offer at order time', function () {
    enableDiscount($this->agent, ['layers' => [['type' => 'cart_percent', 'value' => 10]]]);
    $p = Product::create(['workspace_id' => $this->ws->id, 'title' => 'Mug', 'price' => 100, 'currency' => 'USD', 'stock' => 5]);
    AiAction::create([
        'workspace_id' => $this->ws->id, 'conversation_id' => $this->conv->id, 'ai_agent_id' => $this->agent->id,
        'type' => 'offer_discount', 'status' => 'ok',
        'payload' => ['layer' => 0, 'type' => 'cart_percent', 'value' => 10, 'expires_at' => now()->subMinute()->toIso8601String()],
        'created_at' => now()->subHour(),
    ]);

    $result = $this->exec->execute(
        new ToolCall('1', 'create_order', ['items' => [['product_id' => $p->id, 'qty' => 1]], 'apply_offer' => true]),
        $this->agent, $this->conv,
    );

    expect((float) Order::first()->discount_amount)->toBe(0.0);
    expect((float) $result['total'])->toBe(100.0);
});

it('applies the service percentage only to service line items', function () {
    enableDiscount($this->agent, ['layers' => [['type' => 'service_percent']], 'service_percent' => 10]);
    $good = Product::create(['workspace_id' => $this->ws->id, 'title' => 'Mug', 'price' => 100, 'currency' => 'USD', 'stock' => 5, 'type' => 'product']);
    $svc = Product::create(['workspace_id' => $this->ws->id, 'title' => 'Setup', 'price' => 200, 'currency' => 'USD', 'stock' => 5, 'type' => 'service']);
    $this->exec->execute(new ToolCall('1', 'offer_discount', ['reason' => 'price']), $this->agent, $this->conv);

    $this->exec->execute(
        new ToolCall('2', 'create_order', ['items' => [
            ['product_id' => $good->id, 'qty' => 1],
            ['product_id' => $svc->id, 'qty' => 1],
        ], 'apply_offer' => true]),
        $this->agent, $this->conv,
    );

    $order = Order::first();
    expect((float) $order->subtotal)->toBe(300.0);
    expect((float) $order->discount_amount)->toBe(20.0); // 10% of the 200 service only
    expect((float) $order->total)->toBe(280.0);
});

it('skips the discount below the minimum order value', function () {
    enableDiscount($this->agent, ['layers' => [['type' => 'cart_percent', 'value' => 10]], 'min_order_value' => 500]);
    $p = Product::create(['workspace_id' => $this->ws->id, 'title' => 'Mug', 'price' => 100, 'currency' => 'USD', 'stock' => 5]);
    $this->exec->execute(new ToolCall('1', 'offer_discount', ['reason' => 'price']), $this->agent, $this->conv);

    $this->exec->execute(
        new ToolCall('2', 'create_order', ['items' => [['product_id' => $p->id, 'qty' => 1]], 'apply_offer' => true]),
        $this->agent, $this->conv,
    );

    expect((float) Order::first()->discount_amount)->toBe(0.0);
});
