<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A contact's identity + consent on a single channel (M-broadcast Phase 0).
 * `external_id` is the channel-native address: phone (WhatsApp), PSID (Messenger),
 * IGSID (Instagram). Consent and the 24h session window live here.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int $contact_id
 * @property string $channel
 * @property string $external_id
 * @property Carbon|null $opted_in_at
 * @property string|null $opt_in_source
 * @property string|null $opt_in_text
 * @property Carbon|null $opted_out_at
 * @property string|null $opt_out_reason
 * @property Carbon|null $last_inbound_at
 * @property Carbon|null $window_expires_at
 */
class ContactChannel extends Model
{
    use BelongsToWorkspace;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id', 'contact_id', 'channel', 'external_id',
        'opted_in_at', 'opt_in_source', 'opt_in_text',
        'opted_out_at', 'opt_out_reason', 'last_inbound_at', 'window_expires_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'opted_in_at' => 'datetime',
            'opted_out_at' => 'datetime',
            'last_inbound_at' => 'datetime',
            'window_expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** Reachable for a marketing/template send: opted in and not opted out. */
    public function isSubscribed(): bool
    {
        return $this->opted_in_at !== null && $this->opted_out_at === null;
    }

    /** Inside the 24h session window (free-form / Messenger-IG eligibility). */
    public function inWindow(): bool
    {
        return $this->window_expires_at !== null && $this->window_expires_at->isFuture();
    }

    /** @param  Builder<ContactChannel>  $query */
    public function scopeSubscribed(Builder $query): void
    {
        $query->whereNotNull('opted_in_at')->whereNull('opted_out_at');
    }

    /** @param  Builder<ContactChannel>  $query */
    public function scopeInWindow(Builder $query): void
    {
        $query->whereNotNull('window_expires_at')->where('window_expires_at', '>', now());
    }
}
