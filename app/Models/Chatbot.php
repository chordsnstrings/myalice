<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $workspace_id
 * @property string $name
 * @property string $channel_scope
 * @property string $status
 * @property array<string, mixed>|null $graph
 * @property int $version
 */
class Chatbot extends Model
{
    use BelongsToWorkspace;

    /** @var list<string> */
    protected $fillable = ['workspace_id', 'name', 'channel_scope', 'status', 'graph', 'version'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['graph' => 'array'];
    }
}
