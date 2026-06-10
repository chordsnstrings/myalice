<?php

namespace App\Channels;

/**
 * Channel-agnostic contract (M1). Each messaging provider implements this so the
 * rest of the system never special-cases a channel. Inbound is normalized into
 * the canonical Message schema; outbound is rendered to the channel's format.
 */
interface ChannelConnector
{
    /** Provider key, e.g. "whatsapp". */
    public function type(): string;

    /** Whether the connector has the credentials it needs to send (else stubbed). */
    public function isConfigured(): bool;

    /**
     * Send an outbound message. Returns a provider message id (or a synthetic one
     * when stubbed) so delivery status can be reconciled.
     *
     * @param  array<string, mixed>  $payload
     */
    public function send(string $to, array $payload): string;

    /**
     * Normalize a raw inbound webhook payload into canonical message data.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>> zero or more normalized messages
     */
    public function normalizeInbound(array $payload): array;

    /**
     * Normalize delivery/read/failed receipts from a webhook payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array{external_id: string, status: string, error_code?: int|string|null, at?: string}>
     */
    public function normalizeStatuses(array $payload): array;
}
