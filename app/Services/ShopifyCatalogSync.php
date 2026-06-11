<?php

namespace App\Services;

use App\Models\Product;

/**
 * Upserts the current workspace's Shopify catalog into local Products, keyed by
 * the Shopify variant id. Runs under an already-set tenancy.
 */
class ShopifyCatalogSync
{
    public function __construct(private ShopifyClient $client) {}

    public function sync(): int
    {
        if (! $this->client->connected()) {
            return 0;
        }

        $count = 0;
        foreach ($this->client->products() as $remote) {
            if ($remote['external_id'] === '') {
                continue;
            }
            Product::updateOrCreate(
                ['external_id' => $remote['external_id']],
                [
                    'external_product_id' => $remote['external_product_id'],
                    'title' => $remote['title'],
                    'price' => $remote['price'],
                    'currency' => $remote['currency'],
                    'stock' => $remote['stock'],
                    'image' => $remote['image'],
                    'source' => 'shopify',
                ],
            );
            $count++;
        }

        return $count;
    }
}
