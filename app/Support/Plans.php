<?php

namespace App\Support;

/**
 * Plan → feature gating per §10 of the functional spec. Higher tiers inherit the
 * features of lower tiers. The single source of truth for both the nav lock and
 * the server-side gate.
 */
class Plans
{
    /** Features unlocked at each tier (cumulative). */
    private const TIERS = [
        'premium' => ['broadcasts', 'chatbots', 'hours', 'quick_replies', 'store', 'widget', 'qr', 'routing'],
        'business' => ['automation', 'catalog', 'nlp', 'custom_chatbot', 'api', 'ai_agents'],
        'enterprise' => ['llm', 'white_label'],
    ];

    private const ORDER = ['premium', 'business', 'enterprise'];

    /**
     * All features available to a plan (its tier + everything below it).
     *
     * @return list<string>
     */
    public static function features(string $plan): array
    {
        $features = [];
        foreach (self::ORDER as $tier) {
            $features = [...$features, ...self::TIERS[$tier]];
            if ($tier === $plan) {
                break;
            }
        }

        return $features;
    }

    public static function includes(string $plan, string $feature): bool
    {
        return in_array($feature, self::features($plan), true);
    }
}
