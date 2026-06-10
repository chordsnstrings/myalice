<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supports the AI agent's layered discounts and 23h re-engagement (M13):
 * products gain a type (product|service), orders record the discount breakdown,
 * and conversations get a once-only re-engagement marker.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('type')->default('product')->after('source'); // product | service
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('subtotal', 10, 2)->default(0)->after('number');
            $table->string('discount_type')->nullable()->after('subtotal'); // free_shipping | cart_percent | service_percent
            $table->decimal('discount_amount', 10, 2)->default(0)->after('discount_type');
            $table->decimal('shipping_amount', 10, 2)->default(0)->after('discount_amount');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('reengaged_at')->nullable()->after('awaiting_csat_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('type'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['subtotal', 'discount_type', 'discount_amount', 'shipping_amount']));
        Schema::table('conversations', fn (Blueprint $table) => $table->dropColumn('reengaged_at'));
    }
};
