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
    ];

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
        return array_merge(self::DEFAULT_GUARDRAILS, $this->guardrails ?? []);
    }

    /** Resolve the agent for a channel: exact scope match, else the 'all' row. */
    public static function resolveFor(string $channel): ?self
    {
        return static::where('channel_scope', $channel)->first()
            ?? static::where('channel_scope', 'all')->first();
    }
}
