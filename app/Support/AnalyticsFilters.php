<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Resolved analytics filter window. Ranges are computed in the workspace
 * timezone (so "today" matches the manager's day) and stored as UTC bounds
 * for querying. Carries channel/agent facets and a deterministic cache key.
 */
class AnalyticsFilters
{
    public function __construct(
        public Carbon $from,
        public Carbon $to,
        public string $range,
        public ?string $channel = null,
        public ?int $agentId = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $tz = Tenancy::currentOrFail()->timezone ?: 'UTC';
        $range = (string) $request->query('range', '7d');
        $days = match ($range) {
            '30d' => 30,
            '90d' => 90,
            default => 7,
        };

        if ($range === 'custom' && $request->filled('from') && $request->filled('to')) {
            $from = Carbon::parse((string) $request->query('from'), $tz)->startOfDay();
            $to = Carbon::parse((string) $request->query('to'), $tz)->endOfDay();
        } else {
            $to = Carbon::now($tz)->endOfDay();
            $from = Carbon::now($tz)->startOfDay()->subDays($days - 1);
        }

        $channel = $request->query('channel');
        $agent = $request->query('agent');

        return new self(
            from: $from->clone()->utc(),
            to: $to->clone()->utc(),
            range: $range,
            channel: is_string($channel) && $channel !== '' ? $channel : null,
            agentId: is_numeric($agent) ? (int) $agent : null,
        );
    }

    /** Equal-length window immediately before this one (for deltas). */
    public function previous(): self
    {
        $seconds = $this->to->diffInSeconds($this->from);

        return new self(
            from: $this->from->clone()->subSeconds($seconds + 1),
            to: $this->from->clone()->subSecond(),
            range: $this->range,
            channel: $this->channel,
            agentId: $this->agentId,
        );
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    public function cacheKey(string $suffix): string
    {
        return 'analytics:'.Tenancy::id().':'.$suffix.':'.md5(implode('|', [
            $this->from->toDateTimeString(),
            $this->to->toDateTimeString(),
            $this->channel ?? '',
            $this->agentId ?? '',
        ]));
    }

    /** @return array{range: string, channel: string|null, agent: int|null} */
    public function state(): array
    {
        return ['range' => $this->range, 'channel' => $this->channel, 'agent' => $this->agentId];
    }
}
