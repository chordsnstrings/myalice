<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A source of agent knowledge — a crawled website page, a Facebook Page's info,
 * or admin-pasted text — fetched into snippets and injected into the prompt.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int|null $ai_agent_id
 * @property string $type
 * @property string|null $url
 * @property string $title
 * @property string $status
 * @property Carbon|null $last_fetched_at
 * @property string|null $error
 */
class KnowledgeSource extends Model
{
    use BelongsToWorkspace;

    /** @var list<string> */
    protected $fillable = ['workspace_id', 'ai_agent_id', 'type', 'url', 'title', 'status', 'last_fetched_at', 'error'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['last_fetched_at' => 'datetime'];
    }

    /** @return HasMany<KnowledgeSnippet, $this> */
    public function snippets(): HasMany
    {
        return $this->hasMany(KnowledgeSnippet::class);
    }
}
