<?php

namespace App\Channels;

use App\Models\Channel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * WhatsApp Cloud API connector (M2). Built on Meta's Graph API via the Laravel
 * HTTP client — a thin client, no heavy SDK. Prefers credentials onboarded via
 * the admin panel (encrypted on the Channel), falling back to env. When neither
 * is present it runs in stub mode (BLOCKERS.md → BLK-4).
 */
class WhatsAppConnector implements ChannelConnector
{
    public function type(): string
    {
        return 'whatsapp';
    }

    /** @return array<string, mixed> */
    protected function creds(): array
    {
        return optional(Channel::where('type', $this->type())->first())->credentials ?? [];
    }

    public function isConfigured(): bool
    {
        $c = $this->creds();

        return filled($c['access_token'] ?? config('services.whatsapp.token'))
            && filled($c['phone_number_id'] ?? config('services.whatsapp.phone_number_id'));
    }

    public function send(string $to, array $payload): string
    {
        $c = $this->creds();
        $token = $c['access_token'] ?? config('services.whatsapp.token');
        $phoneId = $c['phone_number_id'] ?? config('services.whatsapp.phone_number_id');

        if (blank($token) || blank($phoneId)) {
            Log::info('[WhatsApp stub] would send', ['to' => $to, 'payload' => $payload]);

            return 'stub_'.Str::uuid();
        }

        $response = Http::withToken((string) $token)
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

    public function normalizeStatuses(array $payload): array
    {
        $statuses = [];

        foreach (data_get($payload, 'entry', []) as $entry) {
            foreach (data_get($entry, 'changes', []) as $change) {
                foreach (data_get($change, 'value.statuses', []) as $status) {
                    $id = data_get($status, 'id');
                    if ($id === null) {
                        continue;
                    }
                    $statuses[] = [
                        'external_id' => (string) $id,
                        'status' => (string) data_get($status, 'status', 'sent'), // sent|delivered|read|failed
                        'error_code' => data_get($status, 'errors.0.code'),
                        'at' => now()->toIso8601String(),
                    ];
                }
            }
        }

        return $statuses;
    }
}
