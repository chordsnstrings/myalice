<?php

namespace App\Http\Controllers\Webhooks;

use Illuminate\Http\Request;

/**
 * Inbound Instagram DM webhook (M1). Same Meta `entry[].messaging[]` shape as
 * Messenger; the IG-linked account id identifies the tenant channel.
 */
class InstagramWebhookController extends MetaWebhookController
{
    protected function type(): string
    {
        return 'instagram';
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
