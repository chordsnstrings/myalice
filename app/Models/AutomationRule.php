<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $workspace_id
 * @property string $name
 * @property string $trigger_type
 * @property array<string, mixed>|null $conditions
 * @property array<string, mixed>|null $timing
 * @property int|null $message_template_id
 * @property string $status
 * @property int $sent
 * @property numeric-string $recovered_revenue
 * @property-read MessageTemplate|null $template
 */
class AutomationRule extends Model
{
    use BelongsToWorkspace;

    /** @var list<string> */
    protected $fillable = ['workspace_id', 'name', 'trigger_type', 'conditions', 'timing', 'message_template_id', 'status', 'sent', 'recovered_revenue'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['conditions' => 'array', 'timing' => 'array', 'recovered_revenue' => 'decimal:2'];
    }

    /** @return BelongsTo<MessageTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'message_template_id');
    }
}
