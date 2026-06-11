<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Workspace;
use App\Services\ShopifyClient;
use App\Support\Tenancy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Places a local order on Shopify (async, off the AI run budget). Maps line items
 * to Shopify variant ids; a failure leaves the local order for a human to fulfil.
 */
class PlaceShopifyOrder implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $workspaceId, public int $orderId) {}

    public function handle(ShopifyClient $client): void
    {
        $workspace = Workspace::find($this->workspaceId);
        if (! $workspace) {
            return;
        }

        Tenancy::set($workspace);

        try {
            $order = Order::with('contact')->find($this->orderId);
            if (! $order || $order->external_id !== null || ! $client->connected()) {
                return;
            }

            $lineItems = [];
            foreach ((array) $order->line_items as $line) {
                if (! empty($line['external_id'])) {
                    $lineItems[] = ['variant_id' => $line['external_id'], 'quantity' => (int) ($line['qty'] ?? 1)];
                }
            }

            if ($lineItems === []) {
                $order->update(['external_status' => 'failed']); // nothing maps to a Shopify variant

                return;
            }

            try {
                $shopifyId = $client->createOrder($lineItems, [
                    'phone' => $order->contact?->phone,
                    'email' => $order->contact?->email,
                    'name' => $order->contact?->name,
                ]);
                $order->update(['external_id' => $shopifyId, 'external_status' => 'placed']);
            } catch (Throwable $e) {
                $order->update(['external_status' => 'failed']);
            }
        } finally {
            Tenancy::clear();
        }
    }
}
