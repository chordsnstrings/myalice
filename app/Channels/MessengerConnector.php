<?php

namespace App\Channels;

use App\Models\Channel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Facebook Messenger connector (M1). Sends via the Graph API Send API using a
 * page access token; normalizes the `entry[].messaging[]` inbound shape. Prefers
 * credentials onboarded via the admin panel, falling back to env; stub mode when
 * neither is present.
 */
class MessengerConnector implements ChannelConnector
{
    protected string $type = 'messenger';

    public function type(): string
    {
        return $this->type;
    }

    protected function pageToken(): ?string
    {
        $stored = Channel::where('type', $this->type)->first()?->credentials['page_token'] ?? null;

        return $stored ?? config("services.{$this->type}.page_token");
    }

    public function isConfigured(): bool
    {
        return filled($this->pageToken());
    }

    public function send(string $to, array $payload): string
    {
        $text = (string) data_get($payload, 'text.body', data_get($payload, 'text', ''));
        $token = $this->pageToken();

        if (blank($token)) {
            Log::info("[{$this->type} stub] would send", ['to' => $to, 'text' => $text]);

            return 'stub_'.Str::uuid();
        }

        $token = (string) $token;

        $response = Http::post("https://graph.facebook.com/v21.0/me/messages?access_token={$token}", [
            'recipient' => ['id' => $to],
            'messaging_type' => 'RESPONSE',
            'message' => ['text' => $text],
        ])->throw();

        return $response->json('message_id', 'unknown');
    }

    public function normalizeInbound(array $payload): array
    {
        $messages = [];

        foreach (data_get($payload, 'entry', []) as $entry) {
            foreach (data_get($entry, 'messaging', []) as $event) {
                $text = data_get($event, 'message.text');
                if ($text === null) {
                    continue; // skip delivery/read receipts, postbacks handled elsewhere
                }

                $messages[] = [
                    'external_id' => data_get($event, 'message.mid'),
                    'from' => (string) data_get($event, 'sender.id'),
                    'type' => 'text',
                    'body' => (string) $text,
                    'sent_at' => now()->toIso8601String(),
                ];
            }
        }

        return $messages;
    }

    public function normalizeStatuses(array $payload): array
    {
        $statuses = [];

        foreach (data_get($payload, 'entry', []) as $entry) {
            foreach (data_get($entry, 'messaging', []) as $event) {
                // Delivery receipts carry the delivered message ids.
                foreach ((array) data_get($event, 'delivery.mids', []) as $mid) {
                    $statuses[] = ['external_id' => (string) $mid, 'status' => 'delivered', 'at' => now()->toIso8601String()];
                }
                // Read events are watermark-based (no per-message id) — handled by
                // the reconciler using the watermark when present.
                $watermark = data_get($event, 'read.watermark');
                if ($watermark !== null) {
                    $statuses[] = ['external_id' => '', 'status' => 'read', 'at' => (string) $watermark];
                }
            }
        }

        return $statuses;
    }
}
