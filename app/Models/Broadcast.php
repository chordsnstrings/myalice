<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workspace_id
 * @property string $name
 * @property string $channel
 * @property int|null $sending_channel_id
 * @property int|null $message_template_id
 * @property array<string, string>|null $variable_map
 * @property int|null $audience_id
 * @property string $status
 * @property Carbon|null $schedule_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property numeric-string $credit_cost
 * @property numeric-string $reserved_cost
 * @property numeric-string $spent_cost
 * @property int $recipients
 * @property int $delivered
 * @property int $read
 * @property int $replied
 * @property int $failed
 * @property int|null $approved_by
 * @property-read MessageTemplate|null $template
 * @property-read Audience|null $audience
 */
class Broadcast extends Model
{
    use BelongsToWorkspace;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id', 'name', 'channel', 'sending_channel_id', 'message_template_id', 'variable_map',
        'audience_id', 'status', 'schedule_at', 'started_at', 'completed_at',
        'credit_cost', 'reserved_cost', 'spent_cost', 'recipients', 'delivered', 'read', 'replied', 'failed', 'approved_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'schedule_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'variable_map' => 'array',
            'credit_cost' => 'decimal:2',
            'reserved_cost' => 'decimal:2',
            'spent_cost' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<MessageTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'message_template_id');
    }

    /** @return BelongsTo<Audience, $this> */
    public function audience(): BelongsTo
    {
        return $this->belongsTo(Audience::class);
    }

    /** @return HasMany<BroadcastRecipient, $this> */
    public function recipientRows(): HasMany
    {
        return $this->hasMany(BroadcastRecipient::class);
    }
}
