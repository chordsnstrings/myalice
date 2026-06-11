<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real Shopify integration: encrypted store credentials, external ids linking
 * local products (variant) + orders to Shopify, and a sync state on orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_connections', function (Blueprint $table) {
            $table->text('credentials')->nullable()->after('store_url'); // encrypted:array {access_token, api_version}
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('external_id')->nullable()->after('source');          // Shopify variant id
            $table->string('external_product_id')->nullable()->after('external_id'); // Shopify product id
            $table->index(['workspace_id', 'external_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('external_id')->nullable()->after('number');     // Shopify order id
            $table->string('external_status')->nullable()->after('external_id'); // pending|placed|failed
        });
    }

    public function down(): void
    {
        Schema::table('store_connections', fn (Blueprint $t) => $t->dropColumn('credentials'));
        Schema::table('products', function (Blueprint $t) {
            $t->dropIndex(['workspace_id', 'external_id']);
            $t->dropColumn(['external_id', 'external_product_id']);
        });
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['external_id', 'external_status']));
    }
};
