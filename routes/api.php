<?php

use App\Http\Controllers\Api\ContactApiController;
use App\Http\Controllers\Api\ConversationApiController;
use App\Http\Controllers\Webhooks\InstagramWebhookController;
use App\Http\Controllers\Webhooks\MessengerWebhookController;
use App\Http\Controllers\Webhooks\ShopifyWebhookController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Inbound provider webhooks (M1/M2). Public, signature/verify-token guarded,
| idempotent, and only enqueue work — never process inline (§3).
*/
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle']);

Route::get('/webhooks/messenger', [MessengerWebhookController::class, 'verify']);
Route::post('/webhooks/messenger', [MessengerWebhookController::class, 'handle']);

Route::get('/webhooks/instagram', [InstagramWebhookController::class, 'verify']);
Route::post('/webhooks/instagram', [InstagramWebhookController::class, 'handle']);

// Shopify webhooks (HMAC-verified): keep stock + order status in sync.
Route::post('/webhooks/shopify/products', [ShopifyWebhookController::class, 'product']);
Route::post('/webhooks/shopify/orders', [ShopifyWebhookController::class, 'order']);

/*
| Developer REST API (M19 / B11.7). Sanctum token auth, scoped to the token's
| workspace, rate-limited per workspace.
*/
Route::middleware(['auth:sanctum', 'workspace', 'throttle:api'])->group(function () {
    Route::get('/contacts', [ContactApiController::class, 'index']);
    Route::get('/contacts/{contact}', [ContactApiController::class, 'show']);
    Route::get('/conversations', [ConversationApiController::class, 'index']);
});
