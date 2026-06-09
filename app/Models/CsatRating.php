<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A customer satisfaction rating tied to a resolved conversation (M17 / B10.4).
 * `agent_id` and `channel` are denormalized for fast per-agent / per-channel
 * aggregation without joins.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int $conversation_id
 * @property int|null $agent_id
 * @property string $channel
 * @property int $rating
 * @property string|null $comment
 * @property Carbon $rated_at
 */
class CsatRating extends Model
{
    use BelongsToWorkspace;

    /** @var list<string> */
    protected $fillable = ['workspace_id', 'conversation_id', 'agent_id', 'channel', 'rating', 'comment', 'rated_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['rated_at' => 'datetime', 'rating' => 'integer'];
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
