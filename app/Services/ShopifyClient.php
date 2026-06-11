<?php

namespace App\Services;

use App\Models\StoreConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Thin Shopify Admin API client for the active workspace's connected store.
 * No SDK; stub mode when the store isn't connected so the flow stays testable.
 */
class ShopifyClient
{
    private ?StoreConnection $store;

    public function __construct()
    {
        $this->store = StoreConnection::where('platform', 'shopify')->first();
    }

    public function connected(): bool
    {
        return $this->store !== null
            && filled($this->store->store_url)
            && filled($this->store->credentials['access_token'] ?? null);
    }

    private function base(): string
    {
        $version = $this->store->credentials['api_version'] ?? '2025-01';

        return 'https://'.rtrim((string) $this->store->store_url, '/')."/admin/api/{$version}";
    }

    /**
     * Active products with their first variant flattened for the local catalog.
     *
     * @return list<array{external_product_id: string, external_id: string, title: string, price: float, currency: string, stock: int, image: ?string}>
     */
    public function products(): array
    {
        if (! $this->connected()) {
            return [];
        }

        $res = Http::withHeaders(['X-Shopify-Access-Token' => (string) $this->store->credentials['access_token']])
            ->timeout((int) config('ai.timeout', 20))
            ->get($this->base().'/products.json', ['limit' => 250, 'status' => 'active'])
            ->throw();

        $out = [];
        foreach ((array) $res->json('products', []) as $p) {
            $variant = $p['variants'][0] ?? null;
            if (! $variant) {
                continue;
            }
            $out[] = [
                'external_product_id' => (string) ($p['id'] ?? ''),
                'external_id' => (string) ($variant['id'] ?? ''),
                'title' => (string) ($p['title'] ?? 'Untitled'),
                'price' => (float) ($variant['price'] ?? 0),
                'currency' => 'USD',
                'stock' => (int) ($variant['inventory_quantity'] ?? 0),
                'image' => $p['image']['src'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Place an order on Shopify. Returns the Shopify order id (or a stub id).
     *
     * @param  list<array{variant_id: string, quantity: int}>  $lineItems
     * @param  array<string, mixed>  $customer
     */
    public function createOrder(array $lineItems, array $customer): string
    {
        if (! $this->connected()) {
            Log::info('[Shopify stub] would create order', ['items' => $lineItems]);

            return 'stub_'.Str::uuid();
        }

        $res = Http::withHeaders(['X-Shopify-Access-Token' => (string) $this->store->credentials['access_token']])
            ->timeout((int) config('ai.timeout', 20))
            ->post($this->base().'/orders.json', [
                'order' => [
                    'line_items' => array_map(fn ($l) => ['variant_id' => $l['variant_id'], 'quantity' => $l['quantity']], $lineItems),
                    'customer' => array_filter([
                        'phone' => $customer['phone'] ?? null,
                        'email' => $customer['email'] ?? null,
                        'first_name' => $customer['name'] ?? null,
                    ]),
                    'financial_status' => 'pending',
                    'tags' => 'myalice-ai',
                ],
            ])
            ->throw();

        return (string) $res->json('order.id', 'unknown');
    }
}
