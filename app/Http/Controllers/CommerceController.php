<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\StoreConnection;
use App\Services\ShopifyCatalogSync;
use App\Services\ShopifyClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CommerceController extends Controller
{
    /** Orders (B8.2). */
    public function orders(): Response
    {
        $orders = Order::with('contact')->latest()->get()->map(fn (Order $o) => [
            'id' => $o->id,
            'number' => $o->number,
            'customer' => optional($o->contact)->name ?? 'Guest',
            'total' => (float) $o->total,
            'currency' => $o->currency,
            'status' => $o->status,
            'source' => $o->source,
            'created_at' => $o->created_at?->toIso8601String(),
        ]);

        return Inertia::render('Commerce/Orders', [
            'orders' => $orders,
            'store' => $this->store(),
        ]);
    }

    /** Product catalog (B8.1). */
    public function products(): Response
    {
        $products = Product::orderBy('title')->get()->map(fn (Product $p) => [
            'id' => $p->id,
            'title' => $p->title,
            'price' => (float) $p->price,
            'currency' => $p->currency,
            'stock' => $p->stock,
            'source' => $p->source,
            'type' => $p->type,
        ]);

        return Inertia::render('Commerce/Products', [
            'products' => $products,
            'store' => $this->store(),
            'shopify_connected' => (new ShopifyClient)->connected(),
        ]);
    }

    /** Connect a Shopify store — verifies the token with a live products call. */
    public function connectStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'store_url' => ['required', 'string', 'max:255'],
            'access_token' => ['required', 'string'],
        ]);

        $store = StoreConnection::updateOrCreate(
            ['platform' => 'shopify'],
            ['store_url' => $data['store_url'], 'credentials' => ['access_token' => $data['access_token'], 'api_version' => '2025-01'], 'status' => 'pending'],
        );

        try {
            (new ShopifyClient)->products(); // throws on bad credentials
        } catch (Throwable $e) {
            $store->update(['status' => 'error']);
            throw ValidationException::withMessages(['access_token' => "Couldn't reach Shopify with those details."]);
        }

        $count = (new ShopifyCatalogSync(new ShopifyClient))->sync();
        $store->update(['status' => 'connected', 'last_synced_at' => now()]);

        return back()->with('success', "Shopify connected — synced {$count} product(s).");
    }

    /** Re-sync the Shopify catalog now. */
    public function syncStore(): RedirectResponse
    {
        $count = (new ShopifyCatalogSync(new ShopifyClient))->sync();
        StoreConnection::where('platform', 'shopify')->update(['last_synced_at' => now()]);

        return back()->with('success', "Synced {$count} product(s).");
    }

    /** Mark a catalog item as a product or a service (drives the AI's service discount). */
    public function updateProductType(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate(['type' => ['required', 'in:product,service']]);
        $product->update(['type' => $data['type']]);

        return back()->with('success', 'Catalog item updated.');
    }

    /** @return array{platform: string, store_url: string, last_synced_at: string|null}|null */
    private function store(): ?array
    {
        $store = StoreConnection::first();

        return $store ? [
            'platform' => $store->platform,
            'store_url' => $store->store_url,
            'last_synced_at' => optional($store->last_synced_at)->diffForHumans(),
        ] : null;
    }
}
