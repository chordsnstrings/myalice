<?php

namespace App\Channels;

/**
 * Instagram DM connector (M1). Instagram messaging uses the same Graph API Send
 * API and `entry[].messaging[]` inbound shape as Messenger (via the linked
 * page), so the behaviour is inherited — only the channel key differs.
 */
class InstagramConnector extends MessengerConnector
{
    protected string $type = 'instagram';
}
