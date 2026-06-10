<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

/**
 * A reusable message template. For WhatsApp these mirror Meta HSM templates
 * (name + language + category + structured components) and must be approved
 * before they can be sent outside the 24h window or in a broadcast.
 *
 * @property int $id
 * @property int $workspace_id
 * @property string $name
 * @property string|null $meta_template_id
 * @property string $category
 * @property string $language
 * @property string $body
 * @property array<int, mixed>|null $components
 * @property int $variable_count
 * @property array<int, mixed>|null $variable_samples
 * @property string|null $header_format
 * @property string|null $header_media_url
 * @property string $approval_status
 * @property string $quality
 * @property string|null $rejection_reason
 */
class MessageTemplate extends Model
{
    use BelongsToWorkspace;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id', 'name', 'meta_template_id', 'category', 'language', 'body',
        'components', 'variable_count', 'variable_samples', 'header_format', 'header_media_url',
        'approval_status', 'quality', 'rejection_reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'components' => 'array',
            'variable_samples' => 'array',
            'variable_count' => 'integer',
        ];
    }

    /** Can this template be sent right now (broadcast / outside the 24h window)? */
    public function isSendable(): bool
    {
        return $this->approval_status === 'approved';
    }

    /** Count distinct {{n}} placeholders in a body string. */
    public static function countVariables(string $body): int
    {
        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $body, $m);

        return $m[1] === [] ? 0 : count(array_unique($m[1]));
    }

    /**
     * Render the body with positional parameters substituted ({{1}}, {{2}}, …).
     *
     * @param  array<int, string>  $params  zero-indexed list mapped to {{1}}, {{2}}, …
     */
    public function render(array $params): string
    {
        return (string) preg_replace_callback('/\{\{\s*(\d+)\s*\}\}/', function ($match) use ($params) {
            $i = (int) $match[1] - 1;

            return $params[$i] ?? $match[0];
        }, $this->body);
    }
}
