<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreConnection;
use App\Models\Workspace;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inbound Shopify webhooks (HMAC-verified): keep local stock + order status in
 * sync. The shop domain header resolves the tenant store.
 */
class ShopifyWebhookController extends Controller
{
    /** products/update + inventory: refresh local stock/price by variant id. */
    public function product(Request $request): JsonResponse
    {
        $store = $this->verifiedStore($request);
        if (! $store) {
            return response()->json(['status' => 'invalid'], 403);
        }

        Tenancy::set(Workspace::find($store->workspace_id));
        try {
            foreach ((array) $request->json('variants', []) as $variant) {
                Product::where('external_id', (string) ($variant['id'] ?? ''))->update([
                    'stock' => (int) ($variant['inventory_quantity'] ?? 0),
                    'price' => (float) ($variant['price'] ?? 0),
                ]);
            }
        } finally {
            Tenancy::clear();
        }

        return response()->json(['status' => 'ok']);
    }

    /** orders/updated: reflect Shopify fulfilment/cancellation on the local order. */
    public function order(Request $request): JsonResponse
    {
        $store = $this->verifiedStore($request);
        if (! $store) {
            return response()->json(['status' => 'invalid'], 403);
        }

        Tenancy::set(Workspace::find($store->workspace_id));
        try {
            $id = (string) $request->json('id', '');
            $financial = (string) $request->json('financial_status', '');
            if ($id !== '') {
                Order::where('external_id', $id)->update([
                    'status' => $financial === 'paid' ? 'paid' : Order::where('external_id', $id)->value('status') ?? 'pending',
                ]);
            }
        } finally {
            Tenancy::clear();
        }

        return response()->json(['status' => 'ok']);
    }

    /** Resolve the store by shop domain and verify the HMAC (when a secret is set). */
    private function verifiedStore(Request $request): ?StoreConnection
    {
        $domain = (string) $request->header('X-Shopify-Shop-Domain', '');
        $store = StoreConnection::withoutGlobalScopes()->where('platform', 'shopify')->where('store_url', $domain)->first();
        if (! $store) {
            return null;
        }

        $secret = config('services.shopify.secret');
        if (! empty($secret)) {
            $hmac = (string) $request->header('X-Shopify-Hmac-Sha256', '');
            $expected = base64_encode(hash_hmac('sha256', $request->getContent(), (string) $secret, true));
            if ($hmac === '' || ! hash_equals($expected, $hmac)) {
                return null;
            }
        }

        return $store;
    }
}
