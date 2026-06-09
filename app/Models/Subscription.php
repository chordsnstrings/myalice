<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workspace_id
 * @property string $plan
 * @property string $billing_cycle
 * @property int $seats
 * @property string $status
 * @property Carbon|null $renews_at
 */
class Subscription extends Model
{
    use BelongsToWorkspace;

    /** @var list<string> */
    protected $fillable = ['workspace_id', 'plan', 'billing_cycle', 'seats', 'status', 'renews_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['renews_at' => 'datetime'];
    }
}
