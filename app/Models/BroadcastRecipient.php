<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One contact's slot in a broadcast — the unit of send + delivery tracking.
 * Exactly-once per (broadcast, contact); the send pipeline only ever advances
 * a `queued` row, so chunks are idempotent and resumable.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int $broadcast_id
 * @property int $contact_id
 * @property string $channel
 * @property string $external_id
 * @property string $status
 * @property string|null $skip_reason
 * @property string|null $provider_message_id
 * @property string|null $error_code
 * @property numeric-string $cost
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $read_at
 * @property Carbon|null $replied_at
 */
class BroadcastRecipient extends Model
{
    use BelongsToWorkspace;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id', 'broadcast_id', 'contact_id', 'channel', 'external_id',
        'status', 'skip_reason', 'provider_message_id', 'error_code', 'cost',
        'sent_at', 'delivered_at', 'read_at', 'replied_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cost' => 'decimal:4',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'replied_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Broadcast, $this> */
    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
