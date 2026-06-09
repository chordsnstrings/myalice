<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workspace_id
 * @property string $name
 * @property int|null $message_template_id
 * @property int|null $audience_id
 * @property string $status
 * @property Carbon|null $schedule_at
 * @property numeric-string $credit_cost
 * @property int $recipients
 * @property int $delivered
 * @property int $read
 * @property int $replied
 * @property-read MessageTemplate|null $template
 * @property-read Audience|null $audience
 */
class Broadcast extends Model
{
    use BelongsToWorkspace;

    /** @var list<string> */
    protected $fillable = ['workspace_id', 'name', 'message_template_id', 'audience_id', 'status', 'schedule_at', 'credit_cost', 'recipients', 'delivered', 'read', 'replied'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['schedule_at' => 'datetime', 'credit_cost' => 'decimal:2'];
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
}
