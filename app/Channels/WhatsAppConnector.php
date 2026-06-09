<?php

namespace App\Channels;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * WhatsApp Cloud API connector (M2). Built on Meta's Graph API via the Laravel
 * HTTP client — a thin client, no heavy SDK. When credentials are absent it runs
 * in stub mode (logs + returns a synthetic id) so the rest of the build proceeds
 * (BLOCKERS.md → BLK-4).
 */
class WhatsAppConnector implements ChannelConnector
{
    public function type(): string
    {
        return 'whatsapp';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.whatsapp.token')) && filled(config('services.whatsapp.phone_number_id'));
    }

    public function send(string $to, array $payload): string
    {
        if (! $this->isConfigured()) {
            Log::info('[WhatsApp stub] would send', ['to' => $to, 'payload' => $payload]);

            return 'stub_'.Str::uuid();
        }

        $phoneId = config('services.whatsapp.phone_number_id');

        $response = Http::withToken((string) config('services.whatsapp.token'))
            ->post("https://graph.facebook.com/v21.0/{$phoneId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                ...$payload,
            ])
            ->throw();

        return $response->json('messages.0.id', 'unknown');
    }

    public function normalizeInbound(array $payload): array
    {
        $messages = [];

        foreach (data_get($payload, 'entry', []) as $entry) {
            foreach (data_get($entry, 'changes', []) as $change) {
                foreach (data_get($change, 'value.messages', []) as $message) {
                    $messages[] = [
                        'external_id' => data_get($message, 'id'),
                        'from' => data_get($message, 'from'),
                        'type' => data_get($message, 'type', 'text'),
                        'body' => data_get($message, 'text.body', ''),
                        'sent_at' => now()->toIso8601String(),
                    ];
                }
            }
        }

        return $messages;
    }
}
