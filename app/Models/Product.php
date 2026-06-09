<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $workspace_id
 * @property string $title
 * @property numeric-string $price
 * @property string $currency
 * @property int $stock
 * @property string|null $image
 * @property string $source
 */
class Product extends Model
{
    use BelongsToWorkspace;

    /** @var list<string> */
    protected $fillable = ['workspace_id', 'title', 'price', 'currency', 'stock', 'image', 'source'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }
}
