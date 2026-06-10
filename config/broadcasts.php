<?php

return [
    // Per-message price by template category (USD). WhatsApp prices marketing,
    // utility and authentication differently and varies by country — this is the
    // default ("rest of world") rate; country overrides can be layered on later.
    // Messenger/Instagram session sends are free.
    'pricing' => [
        'default' => [
            'marketing' => (float) env('WA_PRICE_MARKETING', 0.0125),
            'utility' => (float) env('WA_PRICE_UTILITY', 0.004),
            'authentication' => (float) env('WA_PRICE_AUTH', 0.004),
        ],
    ],

    // How many recipients each chunk job handles (keeps each job well under the
    // SiteGround 50s worker cap, and paces sends across cron ticks).
    'chunk_size' => (int) env('BROADCAST_CHUNK_SIZE', 50),

    // Optional throttle between sends within a chunk (microseconds). 0 = off.
    'throttle_us' => (int) env('BROADCAST_THROTTLE_US', 0),

    // Frequency cap: don't include a contact who already received a marketing
    // broadcast within this many hours. 0 = disabled.
    'frequency_cap_hours' => (int) env('BROADCAST_FREQUENCY_CAP_HOURS', 0),
];
