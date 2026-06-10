<?php

namespace App\Http\Controllers\Webhooks;

use App\Channels\ChannelManager;
use App\Http\Controllers\Controller;
use App\Jobs\ApplyTemplateStatus;
use App\Jobs\ProcessInboundMessage;
use App\Jobs\ReconcileDeliveryStatus;
use App\Models\Channel;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Shared base for Meta channel webhooks (WhatsApp / Messenger / Instagram).
 * Fast and safe: verify-token handshake, X-Hub-Signature-256 payload signature
 * check, idempotent dedupe on the provider event id, then enqueue — never
 * process inline (§3, M1-FR-06).
 */
abstract class MetaWebhookController extends Controller
{
    /** Provider key, e.g. "whatsapp". */
    abstract protected function type(): string;

    /**
     * The receiving entity id used to resolve the tenant Channel
     * (WhatsApp phone_number_id / page id).
     *
     * @param  array<string, mixed>  $payload
     */
    abstract protected function recipientId(array $payload): ?string;

    /**
     * A stable id for this delivery, used for idempotency.
     *
     * @param  array<string, mixed>  $payload
     */
    abstract protected function eventId(Request $request, array $payload): string;

    /**
     * Async template approval/rejection updates (WhatsApp only by default).
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array{meta_template_id?: string, name?: string, language?: string, event: string, reason?: string|null}>
     */
    protected function templateStatusUpdates(array $payload): array
    {
        return [];
    }

    /** Meta's GET verification handshake. */
    public function verify(Request $request): Response
    {
        $token = config("services.{$this->type()}.verify_token");

        if ($request->query('hub_mode') === 'subscribe' && $request->query('hub_verify_token') === $token) {
            return response((string) $request->query('hub_challenge'));
        }

        return response('Forbidden', 403);
    }

    public function handle(Request $request, ChannelManager $channels): JsonResponse
    {
        if (! $this->signatureValid($request)) {
            return response()->json(['status' => 'invalid_signature'], 403);
        }

        $payload = $request->all();
        $eventId = $this->eventId($request, $payload);

        $event = WebhookEvent::firstOrCreate(['provider' => $this->type(), 'event_id' => $eventId]);

        if ($event->processed_at !== null) {
            return response()->json(['status' => 'duplicate']);
        }

        $channel = $this->resolveChannel($this->recipientId($payload));

        if ($channel) {
            $connector = $channels->for($this->type());

            foreach ($connector->normalizeInbound($payload) as $message) {
                ProcessInboundMessage::dispatch($channel->workspace_id, $this->type(), $message);
            }

            // Delivery/read/failed receipts → reconcile message + recipient status.
            $statuses = $connector->normalizeStatuses($payload);
            if ($statuses !== []) {
                ReconcileDeliveryStatus::dispatch($channel->workspace_id, $this->type(), $statuses);
            }

            // Async template approval/rejection updates from Meta.
            $templateUpdates = $this->templateStatusUpdates($payload);
            if ($templateUpdates !== []) {
                ApplyTemplateStatus::dispatch($channel->workspace_id, $templateUpdates);
            }
        } else {
            Log::warning("{$this->type()} webhook for unknown channel", ['recipient' => $this->recipientId($payload)]);
        }

        $event->update(['processed_at' => now()]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Validate X-Hub-Signature-256 against the app secret. Enforced only when an
     * app secret is configured (so local/stub runs aren't blocked).
     */
    protected function signatureValid(Request $request): bool
    {
        $secret = config("services.{$this->type()}.app_secret");

        if (empty($secret)) {
            return true;
        }

        $header = (string) $request->header('X-Hub-Signature-256', '');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), (string) $secret);

        return $header !== '' && hash_equals($expected, $header);
    }

    protected function resolveChannel(?string $recipient): ?Channel
    {
        $query = Channel::withoutGlobalScopes()->where('type', $this->type());

        if ($recipient) {
            $matched = (clone $query)->where('external_id', $recipient)->first();
            if ($matched) {
                return $matched;
            }
        }

        return $query->first();
    }
}
