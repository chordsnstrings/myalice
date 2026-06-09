<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $workspace_id
 * @property string $type
 * @property string $name
 * @property string|null $external_id
 * @property array<string, mixed>|null $credentials
 * @property string $status
 */
class Channel extends Model
{
    use BelongsToWorkspace;

    /** @var list<string> */
    protected $fillable = ['workspace_id', 'type', 'name', 'external_id', 'credentials', 'status'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['credentials' => 'encrypted:array'];
    }
}
