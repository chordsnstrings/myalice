<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Append-only log of AI activity, powering conversion tracking (M17).
 *
 * @property int $id
 * @property int $workspace_id
 * @property int|null $conversation_id
 * @property int|null $ai_agent_id
 * @property string $type
 * @property array<string, mixed>|null $payload
 * @property string $status
 * @property int|null $tokens_in
 * @property int|null $tokens_out
 * @property Carbon $created_at
 */
class AiAction extends Model
{
    use BelongsToWorkspace;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id', 'conversation_id', 'ai_agent_id', 'type',
        'payload', 'status', 'tokens_in', 'tokens_out', 'created_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['payload' => 'array', 'created_at' => 'datetime'];
    }
}
