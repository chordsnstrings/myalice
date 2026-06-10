<?php

namespace App\Services;

use App\Models\Audience;
use App\Models\BroadcastRecipient;
use App\Models\ContactChannel;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolves a broadcast's eligible recipients from an audience's filters, applying
 * per-channel policy (WhatsApp = opted-in marketing; Messenger/Instagram = inside
 * the 24h session window) plus optional frequency capping.
 */
class AudienceBuilder
{
    private const SESSION_CHANNELS = ['messenger', 'instagram'];

    /**
     * Eligible channel identities for a channel + audience.
     *
     * @return Builder<ContactChannel>
     */
    public function query(string $channel, ?Audience $audience): Builder
    {
        $query = ContactChannel::query()->where('channel', $channel);

        // Channel policy: session channels need an open window; WhatsApp needs
        // demonstrable marketing opt-in (and never opted out).
        if (in_array($channel, self::SESSION_CHANNELS, true)) {
            $query->inWindow();
        } else {
            $query->subscribed();
        }

        $filters = [];
        if ($audience !== null) {
            $filters = (array) ($audience->filters ?? []);
        }
        $tags = (array) ($filters['tags'] ?? []);
        $lifecycle = (array) ($filters['lifecycle'] ?? []);

        if ($tags !== [] || $lifecycle !== []) {
            $query->whereHas('contact', function (Builder $c) use ($tags, $lifecycle) {
                if ($lifecycle !== []) {
                    $c->whereIn('lifecycle_stage', $lifecycle);
                }
                if ($tags !== []) {
                    $c->where(function (Builder $t) use ($tags) {
                        foreach ($tags as $tag) {
                            $t->orWhereJsonContains('tags', $tag);
                        }
                    });
                }
            });
        }

        // Frequency cap: exclude contacts messaged by a recent broadcast.
        $hours = (int) config('broadcasts.frequency_cap_hours', 0);
        if ($hours > 0) {
            $recent = BroadcastRecipient::where('sent_at', '>=', now()->subHours($hours))
                ->whereIn('status', ['sent', 'delivered', 'read', 'replied'])
                ->distinct()->pluck('contact_id');
            if ($recent->isNotEmpty()) {
                $query->whereNotIn('contact_id', $recent);
            }
        }

        return $query;
    }

    public function count(string $channel, ?Audience $audience): int
    {
        return $this->query($channel, $audience)->distinct('contact_id')->count('contact_id');
    }
}
