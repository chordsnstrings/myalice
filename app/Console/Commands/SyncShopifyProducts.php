<?php

namespace App\Console\Commands;

use App\Models\StoreConnection;
use App\Models\Workspace;
use App\Services\ShopifyCatalogSync;
use App\Services\ShopifyClient;
use App\Support\Tenancy;
use Illuminate\Console\Command;

/**
 * Sync each workspace's Shopify catalog into local products (every 30 min).
 */
class SyncShopifyProducts extends Command
{
    protected $signature = 'shopify:sync';

    protected $description = 'Sync connected Shopify catalogs into local products';

    public function handle(): int
    {
        $total = 0;

        foreach (Workspace::all() as $workspace) {
            Tenancy::set($workspace);
            $synced = (new ShopifyCatalogSync(new ShopifyClient))->sync();
            if ($synced > 0) {
                StoreConnection::where('platform', 'shopify')->update(['last_synced_at' => now()]);
            }
            $total += $synced;
            Tenancy::clear();
        }

        $this->info("Synced {$total} product(s).");

        return self::SUCCESS;
    }
}
