<?php

use App\Jobs\PlaceShopifyOrder;
use App\Models\Contact;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreConnection;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ShopifyCatalogSync;
use App\Services\ShopifyClient;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;

afterEach(fn () => Tenancy::clear());

function shopifyStore(int $wsId): StoreConnection
{
    return StoreConnection::create([
        'workspace_id' => $wsId, 'platform' => 'shopify', 'store_url' => 'acme.myshopify.com',
        'credentials' => ['access_token' => 'shpat_x', 'api_version' => '2025-01'], 'status' => 'connected',
    ]);
}

function productsResponse(): array
{
    return ['products' => [[
        'id' => 111, 'title' => 'Blue Mug', 'image' => ['src' => 'http://img/x.png'],
        'variants' => [['id' => 999, 'price' => '12.50', 'inventory_quantity' => 7]],
    ]]];
}

it('syncs the Shopify catalog into local products by variant id', function () {
    $ws = Workspace::create(['name' => 'SH']);
    Tenancy::set($ws);
    shopifyStore($ws->id);
    Http::fake(['acme.myshopify.com/*' => Http::response(productsResponse(), 200)]);

    $count = (new ShopifyCatalogSync(new ShopifyClient))->sync();

    expect($count)->toBe(1);
    $p = Product::first();
    expect($p->external_id)->toBe('999');
    expect((float) $p->price)->toBe(12.5);
    expect($p->stock)->toBe(7);
    expect($p->source)->toBe('shopify');
    Tenancy::clear();
});

it('places a Shopify order from a local order, storing the external id', function () {
    $ws = Workspace::create(['name' => 'SH2']);
    Tenancy::set($ws);
    shopifyStore($ws->id);
    $contact = Contact::create(['name' => 'Sam', 'phone' => '+1', 'channel' => 'whatsapp']);
    $order = Order::create([
        'contact_id' => $contact->id, 'number' => 'AI-1', 'subtotal' => 25, 'total' => 25, 'currency' => 'USD',
        'status' => 'pending', 'source' => 'chat', 'external_status' => 'pending',
        'line_items' => [['title' => 'Blue Mug', 'qty' => 2, 'price' => 12.5, 'type' => 'product', 'external_id' => '999']],
    ]);
    Http::fake(['acme.myshopify.com/*' => Http::response(['order' => ['id' => 5005]], 201)]);

    (new PlaceShopifyOrder($ws->id, $order->id))->handle(new ShopifyClient);

    expect($order->fresh()->external_id)->toBe('5005');
    expect($order->fresh()->external_status)->toBe('placed');
    Tenancy::clear();
});

it('marks the order failed when no line maps to a Shopify variant', function () {
    $ws = Workspace::create(['name' => 'SH3']);
    Tenancy::set($ws);
    shopifyStore($ws->id);
    $order = Order::create([
        'number' => 'AI-2', 'subtotal' => 10, 'total' => 10, 'currency' => 'USD', 'status' => 'pending',
        'source' => 'chat', 'line_items' => [['title' => 'Local only', 'qty' => 1, 'price' => 10, 'type' => 'product', 'external_id' => null]],
    ]);

    (new PlaceShopifyOrder($ws->id, $order->id))->handle(new ShopifyClient);

    expect($order->fresh()->external_status)->toBe('failed');
    expect($order->fresh()->external_id)->toBeNull();
    Tenancy::clear();
});

it('connects a store over HTTP after verifying the token', function () {
    $ws = Workspace::create(['name' => 'SH4', 'plan' => 'business']);
    $owner = User::create(['workspace_id' => $ws->id, 'name' => 'O', 'email' => 'o@sh.test', 'password' => bcrypt('x'), 'workspace_role' => 'owner']);
    Http::fake(['acme.myshopify.com/*' => Http::response(productsResponse(), 200)]);

    $this->actingAs($owner)->post('/store/connect', ['store_url' => 'acme.myshopify.com', 'access_token' => 'shpat_ok'])
        ->assertRedirect()->assertSessionHasNoErrors();

    Tenancy::set($ws);
    expect(StoreConnection::where('platform', 'shopify')->value('status'))->toBe('connected');
    expect(Product::where('external_id', '999')->exists())->toBeTrue();
    Tenancy::clear();
});

it('rejects a store connection with bad credentials', function () {
    $ws = Workspace::create(['name' => 'SH5', 'plan' => 'business']);
    $owner = User::create(['workspace_id' => $ws->id, 'name' => 'O', 'email' => 'o@sh5.test', 'password' => bcrypt('x'), 'workspace_role' => 'owner']);
    Http::fake(['acme.myshopify.com/*' => Http::response(['errors' => 'unauthorized'], 401)]);

    $this->actingAs($owner)->post('/store/connect', ['store_url' => 'acme.myshopify.com', 'access_token' => 'bad'])
        ->assertSessionHasErrors('access_token');
});

it('updates local stock from a Shopify product webhook', function () {
    config()->set('services.shopify.secret', '');
    $ws = Workspace::create(['name' => 'SH6']);
    Tenancy::set($ws);
    shopifyStore($ws->id);
    Product::create(['workspace_id' => $ws->id, 'title' => 'Mug', 'price' => 12.5, 'currency' => 'USD', 'stock' => 7, 'external_id' => '999']);
    Tenancy::clear();

    $this->postJson('/api/webhooks/shopify/products', ['variants' => [['id' => 999, 'inventory_quantity' => 2, 'price' => '11.00']]], ['X-Shopify-Shop-Domain' => 'acme.myshopify.com'])
        ->assertOk();

    Tenancy::set($ws);
    expect(Product::where('external_id', '999')->value('stock'))->toBe(2);
    Tenancy::clear();
});

it('gates store management to managers', function () {
    $ws = Workspace::create(['name' => 'SH7', 'plan' => 'business']);
    $agent = User::create(['workspace_id' => $ws->id, 'name' => 'A', 'email' => 'a@sh7.test', 'password' => bcrypt('x'), 'workspace_role' => 'agent']);

    $this->actingAs($agent)->post('/store/connect', ['store_url' => 'x.myshopify.com', 'access_token' => 'y'])->assertForbidden();
});
