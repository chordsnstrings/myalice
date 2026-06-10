<?php

namespace App\Services;

/**
 * Per-message pricing for broadcasts. WhatsApp prices by template category (and,
 * in reality, by country); Messenger/Instagram session sends are free. The rate
 * table lives in config/broadcasts.php so it can be tuned without code changes.
 */
class BroadcastPricing
{
    public function rate(string $channel, string $category, string $country = 'default'): float
    {
        if ($channel !== 'whatsapp') {
            return 0.0; // session sends (Messenger/Instagram) are free
        }

        /** @var array<string, array<string, float>> $table */
        $table = config('broadcasts.pricing', []);

        return (float) (
            $table[$country][$category]
            ?? $table['default'][$category]
            ?? $table['default']['marketing']
            ?? 0.0
        );
    }
}
