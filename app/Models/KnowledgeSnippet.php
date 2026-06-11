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
 * @property array<int, float>|null $embedding
 * @property string|null $embedding_model
 */
class KnowledgeSnippet extends Model
{
    use BelongsToWorkspace;

    /** @var list<string> */
    protected $fillable = ['workspace_id', 'knowledge_source_id', 'title', 'content', 'char_count', 'embedding', 'embedding_model'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['embedding' => 'array'];
    }

    /**
     * The stored embedding as a clean float list, or null when not yet embedded.
     *
     * @return list<float>|null
     */
    public function vector(): ?array
    {
        $raw = $this->embedding;

        return is_array($raw) && $raw !== [] ? array_values(array_map('floatval', $raw)) : null;
    }

    /** @return BelongsTo<KnowledgeSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'knowledge_source_id');
    }
}
