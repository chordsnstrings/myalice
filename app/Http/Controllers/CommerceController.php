<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\StoreConnection;
use Inertia\Inertia;
use Inertia\Response;

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
        ]);

        return Inertia::render('Commerce/Products', [
            'products' => $products,
            'store' => $this->store(),
        ]);
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
