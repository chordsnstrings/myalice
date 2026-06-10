<?php

namespace App\Services;

use App\Models\AiAction;
use App\Models\Conversation;
use App\Models\CsatRating;
use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use App\Support\AnalyticsFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Computes team-performance metrics (M17 / B10) from the live transactional
 * tables, cached in Memcached per (workspace, filters). All queries are
 * workspace-scoped by the global scope; filters add date/channel/agent.
 */
class AnalyticsService
{
    private const TTL = 300; // 5 minutes

    /**
     * @template TValue
     *
     * @param  \Closure(): TValue  $fn
     * @return TValue
     */
    private function remember(AnalyticsFilters $f, string $suffix, \Closure $fn): mixed
    {
        return Cache::remember($f->cacheKey($suffix), self::TTL, $fn);
    }

    /** @return Builder<Conversation> */
    private function conversations(AnalyticsFilters $f): Builder
    {
        return Conversation::query()
            ->whereBetween('created_at', [$f->from, $f->to])
            ->when($f->channel, fn ($q) => $q->where('channel', $f->channel))
            ->when($f->agentId, fn ($q) => $q->where('assignee_id', $f->agentId));
    }

    /** @return Builder<CsatRating> */
    private function ratings(AnalyticsFilters $f): Builder
    {
        return CsatRating::query()
            ->whereBetween('rated_at', [$f->from, $f->to])
            ->when($f->channel, fn ($q) => $q->where('channel', $f->channel))
            ->when($f->agentId, fn ($q) => $q->where('agent_id', $f->agentId));
    }

    /** @return Builder<Order> */
    private function chatOrders(AnalyticsFilters $f): Builder
    {
        return Order::query()
            ->where('source', 'chat')
            ->whereBetween('created_at', [$f->from, $f->to]);
    }

    /** @return Builder<AiAction> */
    private function aiActions(AnalyticsFilters $f): Builder
    {
        return AiAction::query()->whereBetween('created_at', [$f->from, $f->to]);
    }

    /** @return array{conversations:int,avg_response:float,resolution_rate:float,csat:float} */
    private function metricValues(AnalyticsFilters $f): array
    {
        $convs = $this->conversations($f)->get(['created_at', 'first_response_at', 'resolved_at']);
        $total = $convs->count();
        $resolved = $convs->whereNotNull('resolved_at')->count();

        $responded = $convs->whereNotNull('first_response_at');
        $avg = $responded->isEmpty() ? 0.0 : $responded->avg(
            fn (Conversation $c) => abs($c->first_response_at->diffInSeconds($c->created_at))
        );

        return [
            'conversations' => $total,
            'avg_response' => (float) $avg,
            'resolution_rate' => $total > 0 ? round($resolved / $total * 100, 1) : 0.0,
            'csat' => round((float) $this->ratings($f)->avg('rating'), 2),
        ];
    }

    /** @return array<int, array{label:string,value:string,delta:float,spark:array<int,float>}> */
    public function kpis(AnalyticsFilters $f): array
    {
        return $this->remember($f, 'kpis', function () use ($f) {
            $now = $this->metricValues($f);
            $prev = $this->metricValues($f->previous());

            $delta = fn (float $a, float $b, bool $lowerIsBetter = false): float => $b == 0.0
                ? 0.0
                : round((($a - $b) / $b) * 100 * ($lowerIsBetter ? -1 : 1), 1);

            return [
                ['label' => 'Conversations', 'value' => number_format($now['conversations']),
                    'delta' => $delta((float) $now['conversations'], (float) $prev['conversations']),
                    'spark' => $this->spark($f, 'conversations')],
                ['label' => 'Avg response', 'value' => $this->humanDuration($now['avg_response']),
                    'delta' => $delta($now['avg_response'], $prev['avg_response'], lowerIsBetter: true),
                    'spark' => $this->spark($f, 'response')],
                ['label' => 'Resolution rate', 'value' => $now['resolution_rate'].'%',
                    'delta' => $delta($now['resolution_rate'], $prev['resolution_rate']),
                    'spark' => $this->spark($f, 'resolution')],
                ['label' => 'CSAT', 'value' => $now['csat'] > 0 ? (string) $now['csat'] : '—',
                    'delta' => $delta($now['csat'], $prev['csat']),
                    'spark' => $this->spark($f, 'csat')],
            ];
        });
    }

    /** @return array<int, float> */
    private function spark(AnalyticsFilters $f, string $metric): array
    {
        return array_map(fn ($p) => (float) $p['value'], array_slice($this->dailySeries($f, $metric), -7));
    }

    /**
     * Per-day series for trend charts (computed live; bucketed in PHP).
     *
     * @return array<int, array{day:string,value:float}>
     */
    public function dailySeries(AnalyticsFilters $f, string $metric): array
    {
        return $this->remember($f, "series:$metric", function () use ($f, $metric) {
            $days = collect();
            for ($d = $f->from->clone(); $d->lte($f->to); $d->addDay()) {
                $days->put($d->format('Y-m-d'), 0.0);
            }

            if ($metric === 'revenue') {
                $orders = $this->chatOrders($f)->get(['total', 'created_at']);
                foreach ($orders as $o) {
                    $key = $o->created_at->format('Y-m-d');
                    if ($days->has($key)) {
                        $days[$key] += (float) $o->total;
                    }
                }
            } elseif ($metric === 'csat') {
                $byDay = $this->ratings($f)->get(['rating', 'rated_at'])->groupBy(fn ($r) => $r->rated_at->format('Y-m-d'));
                foreach ($byDay as $key => $rows) {
                    if ($days->has($key)) {
                        $days[$key] = round($rows->avg('rating'), 2);
                    }
                }
            } else { // conversations | resolution | response
                $convs = $this->conversations($f)->get(['created_at', 'first_response_at', 'resolved_at']);
                foreach ($convs as $c) {
                    $key = $c->created_at->format('Y-m-d');
                    if (! $days->has($key)) {
                        continue;
                    }
                    if ($metric === 'resolution') {
                        $days[$key] += $c->resolved_at ? 1 : 0;
                    } elseif ($metric === 'response') {
                        // store sum; averaged below
                        $days[$key] += $c->first_response_at ? abs($c->first_response_at->diffInSeconds($c->created_at)) : 0;
                    } else {
                        $days[$key] += 1;
                    }
                }
            }

            return $days->map(fn ($value, $day) => ['day' => $day, 'value' => $value])->values()->all();
        });
    }

    /** @return array<int, array<string, mixed>> */
    public function agentLeaderboard(AnalyticsFilters $f): array
    {
        return $this->remember($f, 'leaderboard', function () use ($f) {
            $convs = $this->conversations($f)->whereNotNull('assignee_id')
                ->get(['assignee_id', 'created_at', 'first_response_at', 'resolved_at', 'contact_id']);

            $ratings = $this->ratings($f)->get(['agent_id', 'rating'])->groupBy('agent_id');
            $orderRevenue = $this->revenueByAgent($f);

            $agents = User::whereIn('id', $convs->pluck('assignee_id')->unique()->filter())->get(['id', 'name']);

            return $agents->map(function (User $a) use ($convs, $ratings, $orderRevenue) {
                $mine = $convs->where('assignee_id', $a->id);
                $responded = $mine->whereNotNull('first_response_at');
                $resolved = $mine->whereNotNull('resolved_at')->count();
                $r = $ratings->get($a->id);

                return [
                    'id' => $a->id,
                    'name' => $a->name,
                    'handled' => $mine->count(),
                    'avg_response' => $this->humanDuration(
                        $responded->isEmpty() ? 0 : $responded->avg(fn ($c) => abs($c->first_response_at->diffInSeconds($c->created_at)))
                    ),
                    'resolution_rate' => $mine->count() > 0 ? round($resolved / $mine->count() * 100, 1) : 0.0,
                    'csat' => $r ? round($r->avg('rating'), 2) : null,
                    'revenue' => round($orderRevenue[$a->id] ?? 0, 2),
                ];
            })->sortByDesc('handled')->values()->all();
        });
    }

    /** @return array<int, float> agentId => revenue (approximate: order via contact's assigned conversation) */
    private function revenueByAgent(AnalyticsFilters $f): array
    {
        $contactToAgent = $this->conversations($f)->whereNotNull('assignee_id')
            ->get(['contact_id', 'assignee_id'])->pluck('assignee_id', 'contact_id');

        $totals = [];
        foreach ($this->chatOrders($f)->get(['contact_id', 'total']) as $o) {
            $agent = $contactToAgent[$o->contact_id] ?? null;
            if ($agent) {
                $totals[$agent] = ($totals[$agent] ?? 0) + (float) $o->total;
            }
        }

        return $totals;
    }

    /** @return array<string, mixed> */
    public function agentDetail(AnalyticsFilters $f, User $agent): array
    {
        $scoped = new AnalyticsFilters($f->from, $f->to, $f->range, $f->channel, $agent->id);

        return $this->remember($scoped, 'agent-detail', function () use ($scoped, $agent) {
            $comments = CsatRating::where('agent_id', $agent->id)
                ->whereBetween('rated_at', [$scoped->from, $scoped->to])
                ->whereNotNull('comment')->latest('rated_at')->limit(10)
                ->get(['rating', 'comment', 'channel', 'rated_at']);

            // "Active hours (proxy)": distinct hours with an outbound agent message.
            $activeHours = Message::where('direction', 'out')->where('author', 'agent')
                ->whereBetween('sent_at', [$scoped->from, $scoped->to])
                ->whereIn('conversation_id', $this->conversations($scoped)->select('id'))
                ->get(['sent_at'])
                ->map(fn ($m) => $m->sent_at->format('Y-m-d H'))->unique()->count();

            return [
                'agent' => ['id' => $agent->id, 'name' => $agent->name],
                'kpis' => $this->kpis($scoped),
                'volume' => $this->dailySeries($scoped, 'conversations'),
                'response_distribution' => $this->responseDistribution($scoped),
                'comments' => $comments->map(fn ($c) => [
                    'rating' => $c->rating, 'comment' => $c->comment, 'channel' => $c->channel,
                    'at' => $c->rated_at->toIso8601String(),
                ])->all(),
                'active_hours' => $activeHours,
            ];
        });
    }

    /** @return array<string, mixed> */
    public function salesConversion(AnalyticsFilters $f): array
    {
        return $this->remember($f, 'sales', function () use ($f) {
            $revenue = (float) $this->chatOrders($f)->sum('total');
            $count = $this->chatOrders($f)->count();
            $conversations = $this->conversations($f)->count();

            return [
                'revenue' => round($revenue, 2),
                'orders' => $count,
                'aov' => $count > 0 ? round($revenue / $count, 2) : 0.0,
                'conversion_rate' => $conversations > 0 ? round($count / $conversations * 100, 1) : 0.0,
                'by_channel' => $this->channelBreakdown($f),
                'by_agent' => collect($this->agentLeaderboard($f))
                    ->map(fn ($a) => ['name' => $a['name'], 'revenue' => $a['revenue'], 'handled' => $a['handled']])
                    ->all(),
                'trend' => $this->dailySeries($f, 'revenue'),
            ];
        });
    }

    /**
     * AI sales-agent performance: action counts, deal-close rate, discount spend
     * and re-engagement recovery across conversations the AI engaged.
     *
     * @return array{engaged:int, replies:int, drafts:int, leads:int, orders:int, handoffs:int, errors:int, conversion_rate:float, offers_made:int, discounted_orders:int, discount_total:float, reengagements_sent:int, reengagement_recovery:float}
     */
    public function aiPerformance(AnalyticsFilters $f): array
    {
        return $this->remember($f, 'ai-performance', function () use ($f) {
            $counts = $this->aiActions($f)
                ->selectRaw('type, count(*) as aggregate')
                ->groupBy('type')
                ->pluck('aggregate', 'type');

            $engaged = (int) $this->aiActions($f)
                ->whereIn('type', ['reply', 'draft'])
                ->distinct()
                ->count('conversation_id');

            $orders = (int) ($counts['create_order'] ?? 0);
            $reengagements = (int) ($counts['reengage'] ?? 0);

            // Discount spend on chat orders in range.
            $discountTotal = (float) $this->chatOrders($f)->sum('discount_amount');
            $discountedOrders = (int) $this->chatOrders($f)->where('discount_amount', '>', 0)->count();

            // Orders whose conversation received a re-engagement nudge → recovery.
            $reengagedConvIds = $this->aiActions($f)->where('type', 'reengage')->distinct()->pluck('conversation_id');
            $orderedConvIds = $this->aiActions($f)->where('type', 'create_order')->distinct()->pluck('conversation_id');
            $recovered = $reengagedConvIds->intersect($orderedConvIds)->count();

            return [
                'engaged' => $engaged,
                'replies' => (int) ($counts['reply'] ?? 0),
                'drafts' => (int) ($counts['draft'] ?? 0),
                'leads' => (int) ($counts['create_lead'] ?? 0),
                'orders' => $orders,
                'handoffs' => (int) ($counts['handoff'] ?? 0),
                'errors' => (int) ($counts['error'] ?? 0),
                'conversion_rate' => $engaged > 0 ? round($orders / $engaged * 100, 1) : 0.0,
                'offers_made' => (int) ($counts['offer_discount'] ?? 0),
                'discounted_orders' => $discountedOrders,
                'discount_total' => round($discountTotal, 2),
                'reengagements_sent' => $reengagements,
                'reengagement_recovery' => $reengagements > 0 ? round($recovered / $reengagements * 100, 1) : 0.0,
            ];
        });
    }

    /** @return array<string, mixed> */
    public function csatReport(AnalyticsFilters $f): array
    {
        return $this->remember($f, 'csat-report', function () use ($f) {
            $all = $this->ratings($f)->get(['rating', 'agent_id', 'channel', 'comment', 'rated_at']);
            $agents = User::whereIn('id', $all->pluck('agent_id')->unique()->filter())->pluck('name', 'id');

            return [
                'average' => round((float) $all->avg('rating'), 2),
                'responses' => $all->count(),
                'trend' => $this->dailySeries($f, 'csat'),
                'by_agent' => $all->whereNotNull('agent_id')->groupBy('agent_id')->map(fn ($rows, $id) => [
                    'name' => $agents[$id] ?? 'Unknown', 'average' => round($rows->avg('rating'), 2), 'count' => $rows->count(),
                ])->values()->all(),
                'by_channel' => $all->groupBy('channel')->map(fn ($rows, $ch) => [
                    'channel' => $ch, 'average' => round($rows->avg('rating'), 2), 'count' => $rows->count(),
                ])->values()->all(),
                'comments' => $all->whereNotNull('comment')->sortByDesc('rated_at')->take(25)->map(fn ($c) => [
                    'rating' => $c->rating, 'comment' => $c->comment, 'channel' => $c->channel,
                    'agent' => $agents[$c->agent_id] ?? null, 'at' => $c->rated_at->toIso8601String(),
                ])->values()->all(),
            ];
        });
    }

    /** @return array<int, array<string, mixed>> */
    public function channelBreakdown(AnalyticsFilters $f): array
    {
        $convs = $this->conversations($f)->get(['channel', 'created_at', 'first_response_at', 'resolved_at']);
        $ratings = $this->ratings($f)->get(['channel', 'rating'])->groupBy('channel');

        return $convs->groupBy('channel')->map(function ($rows, $ch) use ($ratings) {
            $responded = $rows->whereNotNull('first_response_at');
            $r = $ratings->get($ch);

            return [
                'channel' => $ch,
                'conversations' => $rows->count(),
                'avg_response' => $this->humanDuration($responded->isEmpty() ? 0 : $responded->avg(fn ($c) => abs($c->first_response_at->diffInSeconds($c->created_at)))),
                'resolution_rate' => $rows->count() > 0 ? round($rows->whereNotNull('resolved_at')->count() / $rows->count() * 100, 1) : 0.0,
                'csat' => $r ? round($r->avg('rating'), 2) : null,
            ];
        })->values()->all();
    }

    /** @return array<string, int> response-time buckets */
    public function responseDistribution(AnalyticsFilters $f): array
    {
        $buckets = ['<1m' => 0, '1–5m' => 0, '5–30m' => 0, '30m+' => 0];
        foreach ($this->conversations($f)->whereNotNull('first_response_at')->get(['created_at', 'first_response_at']) as $c) {
            $s = abs($c->first_response_at->diffInSeconds($c->created_at));
            $key = $s < 60 ? '<1m' : ($s < 300 ? '1–5m' : ($s < 1800 ? '5–30m' : '30m+'));
            $buckets[$key]++;
        }

        return $buckets;
    }

    /** @return array<int, array{channel:string,name:string}> */
    public function channels(): array
    {
        return Conversation::query()->distinct()->pluck('channel')
            ->map(fn ($c) => ['channel' => $c, 'name' => ucfirst($c)])->values()->all();
    }

    /** @return array<int, array{id:int,name:string}> */
    public function agents(): array
    {
        return User::whereIn('workspace_role', ['owner', 'manager', 'agent'])
            ->orderBy('name')->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->all();
    }

    private function humanDuration(float $seconds): string
    {
        $seconds = (int) round($seconds);
        if ($seconds <= 0) {
            return '—';
        }
        $m = intdiv($seconds, 60);
        $s = $seconds % 60;

        return $m > 0 ? "{$m}m {$s}s" : "{$s}s";
    }
}
