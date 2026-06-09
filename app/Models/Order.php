<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int|null $contact_id
 * @property string $number
 * @property numeric-string $total
 * @property string $currency
 * @property string $status
 * @property string $source
 * @property array<int, mixed>|null $line_items
 * @property-read Contact|null $contact
 */
class Order extends Model
{
    use BelongsToWorkspace;

    /** @var list<string> */
    protected $fillable = ['workspace_id', 'contact_id', 'number', 'total', 'currency', 'status', 'source', 'line_items'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['total' => 'decimal:2', 'line_items' => 'array'];
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
