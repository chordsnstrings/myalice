<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Daily pre-aggregated metrics (sums + counts) backing trend lines (M17).
 * Filled nightly by `analytics:snapshot`.
 *
 * @property int $id
 * @property int $workspace_id
 * @property Carbon $day
 * @property string|null $channel
 * @property int|null $agent_id
 * @property int $conversations
 * @property int $resolved
 * @property int $first_response_seconds_sum
 * @property int $first_response_count
 * @property int $resolution_seconds_sum
 * @property int $resolution_count
 * @property int $csat_sum
 * @property int $csat_count
 * @property numeric-string $revenue
 * @property int $orders
 */
class MetricSnapshot extends Model
{
    use BelongsToWorkspace;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id', 'day', 'channel', 'agent_id',
        'conversations', 'resolved',
        'first_response_seconds_sum', 'first_response_count',
        'resolution_seconds_sum', 'resolution_count',
        'csat_sum', 'csat_count', 'revenue', 'orders',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['day' => 'date', 'revenue' => 'decimal:2'];
    }
}
