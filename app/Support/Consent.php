<?php

namespace App\Support;

use App\Models\ConsentEvent;
use App\Models\ContactChannel;

/**
 * Opt-in / opt-out handling for channel identities. Every transition is mirrored
 * to the append-only consent_events log for compliance proof.
 */
class Consent
{
    /** Inbound bodies that mean "stop messaging me". */
    public const OPT_OUT_KEYWORDS = ['stop', 'unsubscribe', 'opt out', 'opt-out', 'cancel'];

    public static function looksLikeOptOut(string $body): bool
    {
        $normalized = trim(mb_strtolower($body));

        foreach (self::OPT_OUT_KEYWORDS as $kw) {
            // Exact word/phrase match so "stop by tomorrow" doesn't opt out.
            if ($normalized === $kw) {
                return true;
            }
        }

        return false;
    }

    public static function recordOptIn(ContactChannel $channel, string $source, ?string $text = null): void
    {
        $channel->forceFill(['opted_in_at' => now(), 'opt_in_source' => $source, 'opt_in_text' => $text, 'opted_out_at' => null])->save();

        self::log($channel, 'opt_in', $source, $text);
    }

    public static function recordOptOut(ContactChannel $channel, string $source, ?string $text = null): void
    {
        $channel->forceFill(['opted_out_at' => now(), 'opt_out_reason' => $source])->save();

        self::log($channel, 'opt_out', $source, $text);
    }

    private static function log(ContactChannel $channel, string $type, string $source, ?string $text): void
    {
        ConsentEvent::create([
            'workspace_id' => $channel->workspace_id,
            'contact_id' => $channel->contact_id,
            'channel' => $channel->channel,
            'type' => $type,
            'source' => $source,
            'raw_text' => $text,
            'created_at' => now(),
        ]);
    }
}
