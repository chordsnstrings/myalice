<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Append-only audit record of an opt-in or opt-out, kept as compliance proof
 * (Meta requires demonstrable opt-in). Never updated.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int|null $contact_id
 * @property string $channel
 * @property string $type
 * @property string|null $source
 * @property string|null $raw_text
 * @property Carbon|null $created_at
 */
class ConsentEvent extends Model
{
    use BelongsToWorkspace;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['workspace_id', 'contact_id', 'channel', 'type', 'source', 'raw_text', 'created_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
