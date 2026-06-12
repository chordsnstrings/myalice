<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $contact_id
 * @property string $channel
 * @property int|null $channel_id
 * @property string $status
 * @property int|null $assignee_id
 * @property int $unread
 * @property bool $window_open
 * @property bool $sla_breaching
 * @property string|null $last_message
 * @property Carbon|null $last_message_at
 * @property Carbon|null $first_response_at
 * @property Carbon|null $assigned_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $awaiting_csat_at
 * @property Carbon|null $reengaged_at
 * @property Carbon|null $ai_resumed_at
 * @property-read Contact $contact
 */
class Conversation extends Model
{
    use BelongsToWorkspace;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id', 'contact_id', 'channel', 'channel_id', 'status', 'assignee_id', 'unread',
        'window_open', 'sla_breaching', 'last_message', 'last_message_at',
        'first_response_at', 'assigned_at', 'resolved_at', 'awaiting_csat_at', 'ai_status', 'reengaged_at', 'ai_resumed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'window_open' => 'boolean',
            'sla_breaching' => 'boolean',
            'last_message_at' => 'datetime',
            'first_response_at' => 'datetime',
            'assigned_at' => 'datetime',
            'resolved_at' => 'datetime',
            'awaiting_csat_at' => 'datetime',
            'reengaged_at' => 'datetime',
            'ai_resumed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** @return HasMany<CsatRating, $this> */
    public function csatRatings(): HasMany
    {
        return $this->hasMany(CsatRating::class);
    }

    /**
     * Topic tags applied to this conversation ("what it's about").
     *
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'conversation_tag');
    }
}
