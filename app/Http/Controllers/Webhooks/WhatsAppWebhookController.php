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
        $messageId = data_get($payload, 'entry.0.changes.0.value.messages.0.id');
        if ($messageId !== null) {
            return (string) $messageId;
        }

        // Status receipts: key on the status id + state so each transition is
        // deduped independently (not collapsed onto the WABA entry id).
        $statusId = data_get($payload, 'entry.0.changes.0.value.statuses.0.id');
        if ($statusId !== null) {
            return 'status:'.$statusId.':'.data_get($payload, 'entry.0.changes.0.value.statuses.0.status');
        }

        return md5($request->getContent());
    }
}
