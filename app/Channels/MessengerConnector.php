<?php

namespace App\Channels;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Facebook Messenger connector (M1). Sends via the Graph API Send API using a
 * page access token; normalizes the `entry[].messaging[]` inbound shape. Runs in
 * stub mode when no page token is configured.
 */
class MessengerConnector implements ChannelConnector
{
    protected string $type = 'messenger';

    public function type(): string
    {
        return $this->type;
    }

    public function isConfigured(): bool
    {
        return filled(config("services.{$this->type}.page_token"));
    }

    public function send(string $to, array $payload): string
    {
        $text = (string) data_get($payload, 'text.body', data_get($payload, 'text', ''));

        if (! $this->isConfigured()) {
            Log::info("[{$this->type} stub] would send", ['to' => $to, 'text' => $text]);

            return 'stub_'.Str::uuid();
        }

        $token = (string) config("services.{$this->type}.page_token");

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
}
