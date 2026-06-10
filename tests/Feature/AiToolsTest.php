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
