<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $day
 * @property bool $enabled
 * @property string $opens_at
 * @property string $closes_at
 */
class BusinessHour extends Model
{
    use BelongsToWorkspace;

    protected $table = 'business_hours';

    /** @var list<string> */
    protected $fillable = ['workspace_id', 'day', 'enabled', 'opens_at', 'closes_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
