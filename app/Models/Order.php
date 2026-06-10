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
 * @property numeric-string $subtotal
 * @property string|null $discount_type
 * @property numeric-string $discount_amount
 * @property numeric-string $shipping_amount
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
    protected $fillable = ['workspace_id', 'contact_id', 'number', 'subtotal', 'discount_type', 'discount_amount', 'shipping_amount', 'total', 'currency', 'status', 'source', 'line_items'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'discount_amount' => 'decimal:2', 'shipping_amount' => 'decimal:2', 'total' => 'decimal:2', 'line_items' => 'array'];
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
