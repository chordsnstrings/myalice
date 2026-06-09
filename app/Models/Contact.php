<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

/**
 * Commerce-aware customer record (M8). Tenant-scoped: every query is filtered
 * to the active workspace by the BelongsToWorkspace global scope.
 *
 * @property int $id
 * @property int $workspace_id
 * @property string $name
 * @property string|null $phone
 * @property string|null $email
 * @property string $channel
 * @property string $lifecycle_stage
 * @property array<int, string>|null $tags
 */
class Contact extends Model
{
    use BelongsToWorkspace;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'name',
        'phone',
        'email',
        'channel',
        'lifecycle_stage',
        'tags',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }
}
