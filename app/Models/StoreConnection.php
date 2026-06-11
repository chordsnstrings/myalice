<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workspace_id
 * @property string $platform
 * @property string $store_url
 * @property array<string, mixed>|null $credentials
 * @property string $status
 * @property Carbon|null $last_synced_at
 */
class StoreConnection extends Model
{
    use BelongsToWorkspace;

    /** @var list<string> */
    protected $fillable = ['workspace_id', 'platform', 'store_url', 'credentials', 'status', 'last_synced_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['credentials' => 'encrypted:array', 'last_synced_at' => 'datetime'];
    }
}
