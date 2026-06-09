<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $contact_id
 * @property string $channel
 * @property string $status
 * @property int|null $assignee_id
 * @property int $unread
 * @property bool $window_open
 * @property bool $sla_breaching
 * @property string|null $last_message
 * @property Carbon|null $last_message_at
 * @property-read Contact $contact
 */
class Conversation extends Model
{
    use BelongsToWorkspace;

    /** @var list<string> */
    protected $fillable = ['workspace_id', 'contact_id', 'channel', 'status', 'assignee_id', 'unread', 'window_open', 'sla_breaching', 'last_message', 'last_message_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['window_open' => 'boolean', 'sla_breaching' => 'boolean', 'last_message_at' => 'datetime'];
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
