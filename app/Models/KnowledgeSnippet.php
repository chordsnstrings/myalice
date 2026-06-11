<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A plain-text chunk of knowledge, retrieved and injected into the system prompt.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int $knowledge_source_id
 * @property string|null $title
 * @property string $content
 * @property int $char_count
 */
class KnowledgeSnippet extends Model
{
    use BelongsToWorkspace;

    /** @var list<string> */
    protected $fillable = ['workspace_id', 'knowledge_source_id', 'title', 'content', 'char_count'];

    /** @return BelongsTo<KnowledgeSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'knowledge_source_id');
    }
}
