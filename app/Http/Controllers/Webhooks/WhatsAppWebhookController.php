<?php

namespace App\Http\Controllers\Webhooks;

use App\Channels\ChannelManager;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessInboundMessage;
use App\Models\Channel;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Inbound WhatsApp webhook (M1/M2). Fast and idempotent: verify, dedupe on the
 * provider event id, then enqueue — never process inline (§3).
 */
class WhatsAppWebhookController extends Controller
{
    /** Meta's GET verification handshake. */
    public function verify(Request $request): Response
    {
        $token = config('services.whatsapp.verify_token');

        if ($request->query('hub_mode') === 'subscribe' && $request->query('hub_verify_token') === $token) {
            return response((string) $request->query('hub_challenge'));
        }

        return response('Forbidden', 403);
    }

    public function handle(Request $request, ChannelManager $channels): JsonResponse
    {
        $payload = $request->all();

        // Idempotency: dedupe on the message id(s) in the payload.
        $eventId = data_get($payload, 'entry.0.changes.0.value.messages.0.id')
            ?? data_get($payload, 'entry.0.id')
            ?? md5($request->getContent());

        $event = WebhookEvent::firstOrCreate(
            ['provider' => 'whatsapp', 'event_id' => $eventId],
        );

        if ($event->processed_at !== null) {
            return response()->json(['status' => 'duplicate']);
        }

        // Resolve the tenant from the receiving phone number id → Channel.
        $phoneId = data_get($payload, 'entry.0.changes.0.value.metadata.phone_number_id');
        $channel = $phoneId
            ? Channel::withoutGlobalScopes()->where('type', 'whatsapp')->where('external_id', $phoneId)->first()
            : Channel::withoutGlobalScopes()->where('type', 'whatsapp')->first();

        if ($channel) {
            foreach ($channels->for('whatsapp')->normalizeInbound($payload) as $message) {
                ProcessInboundMessage::dispatch($channel->workspace_id, 'whatsapp', $message);
            }
        } else {
            Log::warning('WhatsApp webhook for unknown channel', ['phone_id' => $phoneId]);
        }

        $event->update(['processed_at' => now()]);

        return response()->json(['status' => 'ok']);
    }
}
