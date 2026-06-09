<?php

namespace App\Http\Controllers\Webhooks;

use Illuminate\Http\Request;

/**
 * Inbound Facebook Messenger webhook (M1). Payload uses the Meta
 * `entry[].messaging[]` shape; the page id identifies the tenant channel.
 */
class MessengerWebhookController extends MetaWebhookController
{
    protected function type(): string
    {
        return 'messenger';
    }

    protected function recipientId(array $payload): ?string
    {
        $id = data_get($payload, 'entry.0.id');

        return $id !== null ? (string) $id : null;
    }

    protected function eventId(Request $request, array $payload): string
    {
        return data_get($payload, 'entry.0.messaging.0.message.mid')
            ?? md5($request->getContent());
    }
}
