<?php

use App\Ai\ToolCall;
use App\Ai\ToolExecutor;
use App\Models\AiAgent;
use App\Models\AiProvider;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\Product;
use App\Models\Workspace;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::clear());

it('isolates providers and agents per workspace', function () {
    $a = Workspace::create(['name' => 'A', 'plan' => 'business']);
    $b = Workspace::create(['name' => 'B', 'plan' => 'business']);

    Tenancy::set($a);
    AiProvider::create(['workspace_id' => $a->id, 'type' => 'openai', 'name' => 'A-OpenAI', 'credentials' => ['api_key' => 'x', 'model' => 'm'], 'status' => 'connected', 'is_default' => true, 'fallback_order' => 0]);
    AiAgent::create(['workspace_id' => $a->id, 'name' => 'A-agent', 'enabled' => true, 'mode' => 'auto', 'goal' => 'sale', 'channel_scope' => 'all', 'tone' => 'friendly', 'methodology' => 'consultative_spin']);

    Tenancy::set($b);
    expect(AiProvider::count())->toBe(0);
    expect(AiAgent::resolveFor('all'))->toBeNull();
});

it('a tool call cannot order another tenant product', function () {
    $a = Workspace::create(['name' => 'A', 'plan' => 'business']);
    $b = Workspace::create(['name' => 'B', 'plan' => 'business']);

    Tenancy::set($a);
    $foreignProduct = Product::create(['workspace_id' => $a->id, 'title' => 'A Mug', 'price' => 10, 'currency' => 'USD', 'stock' => 5]);

    Tenancy::set($b);
    $agent = AiAgent::create(['workspace_id' => $b->id, 'name' => 'B-agent', 'enabled' => true, 'mode' => 'autopilot', 'goal' => 'sale', 'channel_scope' => 'all', 'tone' => 'friendly', 'methodology' => 'consultative_spin']);
    $contact = Contact::create(['name' => 'C', 'phone' => '+9', 'channel' => 'web', 'lifecycle_stage' => 'lead']);
    $conv = Conversation::create(['contact_id' => $contact->id, 'channel' => 'web', 'status' => 'open', 'window_open' => true]);

    $result = app(ToolExecutor::class)->execute(
        new ToolCall('1', 'create_order', ['items' => [['product_id' => $foreignProduct->id, 'qty' => 1]]]),
        $agent, $conv,
    );

    expect($result['ok'])->toBeFalse();
    expect(Order::count())->toBe(0);
});
