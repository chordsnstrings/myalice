<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Idempotency ledger for inbound provider webhooks. Not tenant-scoped — it
 * dedupes at the provider boundary before tenant resolution.
 *
 * @property int $id
 * @property string $provider
 * @property string $event_id
 * @property Carbon|null $processed_at
 */
class WebhookEvent extends Model
{
    /** @var list<string> */
    protected $fillable = ['provider', 'event_id', 'processed_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }
}
