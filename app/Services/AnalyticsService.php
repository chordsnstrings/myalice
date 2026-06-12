<?php

namespace App\Services;

use App\Models\AiAction;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Conversation;
use App\Models\CsatRating;
use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use App\Support\AnalyticsFilters;
use App\Support\Tenancy;
use Carbon\CarbonInterface;
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

    /** @return array<int, float> Always-daily 7-point spark, independent of the report grouping. */
    private function spark(AnalyticsFilters $f, string $metric): array
    {
        return array_map(fn ($p) => (float) $p['value'], array_slice($this->dailySeries($f->withGroup('day'), $metric), -7));
    }

    /** Bucket key for a date under the active grouping (day | week | month). */
    private function bucketKey(?CarbonInterface $d, string $group): string
    {
        if ($d === null) {
            return '';
        }

        return match ($group) {
            'month' => $d->format('Y-m'),
            'week' => $d->copy()->startOfWeek()->format('Y-m-d'),
            default => $d->format('Y-m-d'),
        };
    }

    /**
     * Per-bucket series for trend charts (computed live; bucketed in PHP by the
     * filter's grouping — day, week or month).
     *
     * @return array<int, array{day:string,value:float}>
     */
    public function dailySeries(AnalyticsFilters $f, string $metric): array
    {
        return $this->remember($f, "series:$metric", function () use ($f, $metric) {
            $days = collect();
            for ($d = $f->from->clone(); $d->lte($f->to); $d->addDay()) {
                $key = $this->bucketKey($d, $f->group);
                if (! $days->has($key)) {
                    $days->put($key, 0.0);
                }
            }

            if ($metric === 'revenue') {
                foreach ($this->chatOrders($f)->get(['total', 'created_at']) as $o) {
                    $key = $this->bucketKey($o->created_at, $f->group);
                    if ($days->has($key)) {
                        $days[$key] += (float) $o->total;
                    }
                }
            } elseif ($metric === 'csat') {
                $byBucket = $this->ratings($f)->get(['rating', 'rated_at'])
                    ->groupBy(fn ($r) => $this->bucketKey($r->rated_at, $f->group));
                foreach ($byBucket as $key => $rows) {
                    if ($days->has($key)) {
                        $days[$key] = round($rows->avg('rating'), 2);
                    }
                }
            } else { // conversations | resolution | response
                foreach ($this->conversations($f)->get(['created_at', 'first_response_at', 'resolved_at']) as $c) {
                    $key = $this->bucketKey($c->created_at, $f->group);
                    if (! $days->has($key)) {
                        continue;
                    }
                    if ($metric === 'resolution') {
                        $days[$key] += $c->resolved_at ? 1 : 0;
                    } elseif ($metric === 'response') {
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

            // "Active hours (proxy)" + messages sent: outbound agent messages in range.
            $agentMsgs = Message::where('direction', 'out')->where('author', 'agent')
                ->whereBetween('sent_at', [$scoped->from, $scoped->to])
                ->whereIn('conversation_id', $this->conversations($scoped)->select('id'))
                ->get(['sent_at']);
            $activeHours = $agentMsgs->map(fn ($m) => $m->sent_at->format('Y-m-d H'))->unique()->count();

            // Granular timing + quality stats.
            $convs = $this->conversations($scoped)->get(['created_at', 'first_response_at', 'resolved_at', 'reopened_count']);
            $frt = $convs->whereNotNull('first_response_at')
                ->map(fn (Conversation $c) => abs($c->first_response_at->diffInSeconds($c->created_at)))->values()->all();
            $resolvedConvs = $convs->whereNotNull('resolved_at');
            $rt = $resolvedConvs->map(fn (Conversation $c) => abs($c->resolved_at->diffInSeconds($c->created_at)))->values()->all();
            $reopened = $resolvedConvs->where('reopened_count', '>', 0)->count();

            return [
                'agent' => ['id' => $agent->id, 'name' => $agent->name],
                'kpis' => $this->kpis($scoped),
                'stats' => [
                    ['label' => 'Median 1st response', 'value' => $this->humanDuration($this->median($frt))],
                    ['label' => 'p90 1st response', 'value' => $this->humanDuration($this->percentile($frt, 90))],
                    ['label' => 'Median resolution', 'value' => $this->humanDuration($this->median($rt))],
                    ['label' => 'Messages sent', 'value' => number_format($agentMsgs->count())],
                    ['label' => 'Reopen rate', 'value' => ($resolvedConvs->count() > 0 ? round($reopened / $resolvedConvs->count() * 100, 1) : 0.0).'%'],
                ],
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
            $paid = (int) $this->chatOrders($f)->whereIn('status', ['paid', 'fulfilled', 'delivered'])->count();
            $discountTotal = (float) $this->chatOrders($f)->sum('discount_amount');
            $discountedOrders = (int) $this->chatOrders($f)->where('discount_amount', '>', 0)->count();

            // Top products from order line items.
            $productQty = [];
            foreach ($this->chatOrders($f)->get(['line_items']) as $o) {
                foreach ((array) $o->line_items as $li) {
                    $title = (string) ($li['title'] ?? 'Item');
                    $productQty[$title] = ($productQty[$title] ?? 0) + (int) ($li['qty'] ?? 1);
                }
            }
            arsort($productQty);
            $topProducts = collect($productQty)->take(5)
                ->map(fn ($qty, $title) => ['title' => $title, 'qty' => $qty])->values()->all();

            // AOV per bucket, aligned to the revenue trend.
            $revByBucket = [];
            $cntByBucket = [];
            foreach ($this->chatOrders($f)->get(['total', 'created_at']) as $o) {
                $k = $this->bucketKey($o->created_at, $f->group);
                $revByBucket[$k] = ($revByBucket[$k] ?? 0) + (float) $o->total;
                $cntByBucket[$k] = ($cntByBucket[$k] ?? 0) + 1;
            }
            $aovTrend = array_map(function ($p) use ($revByBucket, $cntByBucket) {
                $c = $cntByBucket[$p['day']] ?? 0;

                return ['day' => $p['day'], 'value' => $c > 0 ? round(($revByBucket[$p['day']] ?? 0) / $c, 2) : 0.0];
            }, $this->dailySeries($f, 'revenue'));

            return [
                'revenue' => round($revenue, 2),
                'orders' => $count,
                'aov' => $count > 0 ? round($revenue / $count, 2) : 0.0,
                'conversion_rate' => $conversations > 0 ? round($count / $conversations * 100, 1) : 0.0,
                'discount_total' => round($discountTotal, 2),
                'discounted_orders' => $discountedOrders,
                'funnel' => [
                    ['label' => 'Conversations', 'value' => $conversations],
                    ['label' => 'Orders', 'value' => $count],
                    ['label' => 'Paid', 'value' => $paid],
                ],
                'top_products' => $topProducts,
                'by_channel' => $this->channelBreakdown($f),
                'by_agent' => collect($this->agentLeaderboard($f))
                    ->map(fn ($a) => ['name' => $a['name'], 'revenue' => $a['revenue'], 'handled' => $a['handled']])
                    ->all(),
                'trend' => $this->dailySeries($f, 'revenue'),
                'aov_trend' => $aovTrend,
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

    /**
     * Broadcast performance across the range: cumulative funnel + spend.
     *
     * @return array{sent:int, delivered:int, read:int, replied:int, failed:int, skipped:int, delivery_rate:float, read_rate:float, reply_rate:float, spend:float}
     */
    public function broadcastPerformance(AnalyticsFilters $f): array
    {
        return $this->remember($f, 'broadcasts', function () use ($f) {
            $rows = BroadcastRecipient::whereBetween('created_at', [$f->from, $f->to])
                ->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

            $r = fn (string $k) => (int) ($rows[$k] ?? 0);
            // Status advances, so each tier includes the ones beyond it.
            $sent = $r('sent') + $r('delivered') + $r('read') + $r('replied');
            $delivered = $r('delivered') + $r('read') + $r('replied');
            $read = $r('read') + $r('replied');
            $replied = $r('replied');
            $spend = (float) Broadcast::whereBetween('created_at', [$f->from, $f->to])->sum('spent_cost');

            return [
                'sent' => $sent,
                'delivered' => $delivered,
                'read' => $read,
                'replied' => $replied,
                'failed' => $r('failed'),
                'skipped' => $r('skipped'),
                'delivery_rate' => $sent > 0 ? round($delivered / $sent * 100, 1) : 0.0,
                'read_rate' => $delivered > 0 ? round($read / $delivered * 100, 1) : 0.0,
                'reply_rate' => $delivered > 0 ? round($replied / $delivered * 100, 1) : 0.0,
                'spend' => round($spend, 2),
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
                'distribution' => collect(range(5, 1))->map(fn ($r) => [
                    'rating' => $r, 'count' => $all->where('rating', $r)->count(),
                ])->values()->all(),
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

    /**
     * Granular operational report for managers: distributions + percentiles +
     * staffing signals, not just averages.
     *
     * @return array<string, mixed>
     */
    public function operations(AnalyticsFilters $f): array
    {
        return $this->remember($f, 'operations', function () use ($f) {
            $convs = $this->conversations($f)->get(['created_at', 'first_response_at', 'resolved_at', 'assignee_id', 'status', 'sla_breaching']);
            $total = $convs->count();
            $resolved = $convs->whereNotNull('resolved_at');

            $frt = $convs->whereNotNull('first_response_at')
                ->map(fn (Conversation $c) => abs($c->first_response_at->diffInSeconds($c->created_at)))->values()->all();
            $rt = $resolved
                ->map(fn (Conversation $c) => abs($c->resolved_at->diffInSeconds($c->created_at)))->values()->all();

            $breaches = $convs->where('sla_breaching', true)->count();
            $status = $convs->groupBy('status')->map->count();

            $engaged = (int) $this->aiActions($f)->whereIn('type', ['reply', 'draft'])->distinct()->count('conversation_id');
            $handoff = (int) $this->aiActions($f)->where('type', 'handoff')->distinct()->count('conversation_id');

            return [
                'total' => $total,
                'resolved' => $resolved->count(),
                'resolution_rate' => $total > 0 ? round($resolved->count() / $total * 100, 1) : 0.0,
                'unassigned' => $convs->whereNull('assignee_id')->count(),
                'median_first_response' => $this->humanDuration($this->median($frt)),
                'p90_first_response' => $this->humanDuration($this->percentile($frt, 90)),
                'median_resolution' => $this->humanDuration($this->median($rt)),
                'p90_resolution' => $this->humanDuration($this->percentile($rt, 90)),
                'sla_attainment' => $total > 0 ? round(($total - $breaches) / $total * 100, 1) : 0.0,
                'sla_breaches' => $breaches,
                'handoff_rate' => $engaged > 0 ? round($handoff / $engaged * 100, 1) : 0.0,
                'response_distribution' => $this->responseDistribution($f),
                'volume' => $this->dailySeries($f, 'conversations'),
                'resolved_trend' => $this->dailySeries($f, 'resolution'),
                'heatmap' => $this->volumeHeatmap($f),
                'by_channel' => $this->channelBreakdown($f),
                'status_split' => [
                    ['label' => 'Resolved', 'value' => $resolved->count(), 'tone' => 'success'],
                    ['label' => 'Open', 'value' => (int) $status->get('open', 0), 'tone' => 'info'],
                    ['label' => 'Pending', 'value' => (int) $status->get('pending', 0), 'tone' => 'warning'],
                ],
            ];
        });
    }

    /**
     * Conversation volume by weekday (0=Sun) × hour (0–23) in the workspace tz —
     * the "when do customers message us" heatmap that drives staffing.
     *
     * @return array{grid: array<int, array<int, int>>, max: int}
     */
    public function volumeHeatmap(AnalyticsFilters $f): array
    {
        $tz = Tenancy::currentOrFail()->timezone ?: 'UTC';
        /** @var array<int, array<int, int>> $grid */
        $grid = array_fill(0, 7, array_fill(0, 24, 0));

        foreach ($this->conversations($f)->get(['created_at']) as $c) {
            $local = $c->created_at->clone()->setTimezone($tz);
            $grid[(int) $local->dayOfWeek][(int) $local->format('G')]++;
        }

        $max = 0;
        foreach ($grid as $row) {
            $max = max($max, max($row));
        }

        return ['grid' => $grid, 'max' => $max];
    }

    /** @param  array<int, float|int>  $values */
    private function median(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);

        return $n % 2 === 1 ? (float) $values[$mid] : (float) (($values[$mid - 1] + $values[$mid]) / 2);
    }

    /** @param  array<int, float|int>  $values */
    private function percentile(array $values, int $p): float
    {
        if ($values === []) {
            return 0.0;
        }
        sort($values);
        $rank = (int) ceil($p / 100 * count($values)) - 1;

        return (float) $values[max(0, min($rank, count($values) - 1))];
    }

    private function humanDuration(float $seconds): string
    {
        $seconds = (int) round($seconds);
        if ($seconds <= 0) {
            return '—';
        }
        if ($seconds < 3600) {
            $m = intdiv($seconds, 60);
            $s = $seconds % 60;

            return $m > 0 ? "{$m}m {$s}s" : "{$s}s";
        }
        if ($seconds < 86400) {
            return intdiv($seconds, 3600).'h '.intdiv($seconds % 3600, 60).'m';
        }

        return intdiv($seconds, 86400).'d '.intdiv($seconds % 86400, 3600).'h';
    }
}
