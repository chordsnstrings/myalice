<?php

namespace App\Http\Controllers\Webhooks;

use Illuminate\Http\Request;

/**
 * Inbound WhatsApp Cloud API webhook (M1/M2). Behaviour is provided by
 * MetaWebhookController; this class only describes WhatsApp's payload shape.
 */
class WhatsAppWebhookController extends MetaWebhookController
{
    protected function type(): string
    {
        return 'whatsapp';
    }

    protected function recipientId(array $payload): ?string
    {
        $id = data_get($payload, 'entry.0.changes.0.value.metadata.phone_number_id');

        return $id !== null ? (string) $id : null;
    }

    protected function eventId(Request $request, array $payload): string
    {
        return data_get($payload, 'entry.0.changes.0.value.messages.0.id')
            ?? data_get($payload, 'entry.0.id')
            ?? md5($request->getContent());
    }
}
