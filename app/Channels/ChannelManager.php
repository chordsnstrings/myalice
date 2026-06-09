<?php

namespace App\Channels;

use InvalidArgumentException;

/**
 * Resolves the connector for a channel type. New channels register here;
 * callers stay channel-agnostic (M1).
 */
class ChannelManager
{
    /** @var array<string, class-string<ChannelConnector>> */
    protected array $connectors = [
        'whatsapp' => WhatsAppConnector::class,
        'messenger' => MessengerConnector::class,
        'instagram' => InstagramConnector::class,
    ];

    public function for(string $type): ChannelConnector
    {
        $class = $this->connectors[$type]
            ?? throw new InvalidArgumentException("No connector registered for channel [{$type}].");

        return app($class);
    }

    public function supports(string $type): bool
    {
        return isset($this->connectors[$type]);
    }
}
