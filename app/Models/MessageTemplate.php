<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $workspace_id
 * @property string $name
 * @property string $category
 * @property string $language
 * @property string $body
 * @property string $approval_status
 * @property string $quality
 * @property string|null $rejection_reason
 */
class MessageTemplate extends Model
{
    use BelongsToWorkspace;

    /** @var list<string> */
    protected $fillable = ['workspace_id', 'name', 'category', 'language', 'body', 'approval_status', 'quality', 'rejection_reason'];
}
