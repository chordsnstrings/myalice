<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

/**
 * A per-workspace LLM provider. `openai_compatible` covers self-hosted Ollama/vLLM
 * and aggregators (DeepSeek, Groq, Together, OpenRouter) via a custom base_url.
 *
 * @property int $id
 * @property int $workspace_id
 * @property string $type
 * @property string $name
 * @property array<string, mixed> $credentials
 * @property string $status
 * @property bool $is_default
 * @property int $fallback_order
 */
class AiProvider extends Model
{
    use BelongsToWorkspace;

    /** @var list<string> */
    protected $fillable = ['workspace_id', 'type', 'name', 'credentials', 'status', 'is_default', 'fallback_order'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['credentials' => 'encrypted:array', 'is_default' => 'boolean'];
    }
}
