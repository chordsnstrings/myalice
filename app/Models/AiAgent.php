<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-workspace AI sales-agent configuration (M13). One 'all' row per workspace,
 * with optional per-channel overrides.
 *
 * @property int $id
 * @property int $workspace_id
 * @property string $name
 * @property bool $enabled
 * @property string $mode
 * @property string $goal
 * @property string $channel_scope
 * @property string $tone
 * @property string $methodology
 * @property string|null $custom_instructions
 * @property string|null $business_profile
 * @property array<string, mixed> $guardrails
 * @property int|null $ai_provider_id
 */
class AiAgent extends Model
{
    use BelongsToWorkspace;

    public const DEFAULT_GUARDRAILS = [
        'max_messages_per_conversation' => 12,
        'handoff_keywords' => ['human', 'agent', 'representative', 'person', 'refund'],
        'order_total_cap' => null,
        'engage_new_conversations' => true,
        'humanize_replies' => false,
        // High-closure techniques the admin opts into (see App\Ai\Prompts).
        'closure_techniques' => [],
        // Pre-approved, layered discounts. The agent reveals layers one at a time
        // and can never exceed these server-enforced caps (App\Ai\ToolExecutor).
        'discount' => [
            'enabled' => false,
            'layers' => [],            // ordered: [{type: free_shipping|cart_percent|service_percent, value?: float}]
            'service_percent' => 0,    // pre-approved % for service-type line items
            'shipping_fee' => 0.0,     // value of a free-shipping concession (for records)
            'max_percent' => 15,       // hard cap on any percentage discount
            'min_order_value' => 0.0,  // floor to qualify for any discount
            'once_per_contact' => true,
            'offer_ttl_minutes' => 60, // offers expire so urgency stays truthful
        ],
        // ~23h in-window automatic re-engagement (App\Console\Commands\SendReengagements).
        'reengage' => [
            'enabled' => false,
            'min_customer_messages' => 1,
        ],
    ];

    /** Closure techniques an admin may enable; surfaced to the UI and the prompt. */
    public const CLOSURE_TECHNIQUES = ['fomo', 'scarcity', 'urgency', 'social_proof', 'anchoring', 'assumptive_close', 'authority'];

    /** Discount layer types an admin may configure. */
    public const DISCOUNT_TYPES = ['free_shipping', 'cart_percent', 'service_percent'];

    /** @var list<string> */
    protected $fillable = [
        'workspace_id', 'name', 'enabled', 'mode', 'goal', 'channel_scope', 'tone',
        'methodology', 'custom_instructions', 'business_profile', 'guardrails', 'ai_provider_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'guardrails' => 'array'];
    }

    /**
     * Guardrails merged over the defaults.
     *
     * @return array<string, mixed>
     */
    public function guardConfig(): array
    {
        $merged = array_merge(self::DEFAULT_GUARDRAILS, $this->guardrails ?? []);

        // Deep-merge the nested config blocks so a partially-stored block keeps
        // the defaults for any keys the admin didn't set.
        foreach (['discount', 'reengage'] as $block) {
            $merged[$block] = array_merge(self::DEFAULT_GUARDRAILS[$block], $this->guardrails[$block] ?? []);
        }

        return $merged;
    }

    /** Resolve the agent for a channel: exact scope match, else the 'all' row. */
    public static function resolveFor(string $channel): ?self
    {
        return static::where('channel_scope', $channel)->first()
            ?? static::where('channel_scope', 'all')->first();
    }
}
