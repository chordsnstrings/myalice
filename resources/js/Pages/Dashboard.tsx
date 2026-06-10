import { Head, Link, router } from '@inertiajs/react';
import { ArrowUpRight, ArrowDownRight, TrendingUp, Sparkles } from 'lucide-react';
import { AppShell } from '@/components/shell/AppShell';
import { Card } from '@/components/ui/Card';
import { FilterBar, type AnalyticsFilterState } from '@/components/analytics/FilterBar';
import { Sparkline } from '@/components/analytics/Sparkline';
import { BarChart } from '@/components/analytics/BarChart';
import { cn, money } from '@/lib/utils';

interface Kpi {
    label: string;
    value: string;
    delta: number;
    spark: number[];
}
interface Agent {
    id: number;
    name: string;
    handled: number;
    avg_response: string;
    resolution_rate: number;
    csat: number | null;
    revenue: number;
}
interface AiPerformance {
    engaged: number;
    replies: number;
    drafts: number;
    leads: number;
    orders: number;
    handoffs: number;
    errors: number;
    conversion_rate: number;
}
interface Props {
    kpis: Kpi[];
    revenueTrend: { day: string; value: number }[];
    leaderboard: Agent[];
    recovered: number;
    channels: { channel: string; name: string }[];
    agents: { id: number; name: string }[];
    ai: AiPerformance | null;
    filters: AnalyticsFilterState;
}

export default function Dashboard({ kpis, revenueTrend, leaderboard, recovered, channels, agents, ai, filters }: Props) {
    const totalRevenue = revenueTrend.reduce((s, d) => s + d.value, 0);

    return (
        <AppShell title="Analytics">
            <Head title="Dashboard" />
            <div className="h-full overflow-y-auto">
                <div className="mx-auto max-w-[1200px] px-6 py-6">
                    <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 className="text-xl font-semibold tracking-tight">Overview</h2>
                            <p className="text-[13px] text-secondary">Team performance across your channels</p>
                        </div>
                        <FilterBar routeUrl="/dashboard" filters={filters} channels={channels} agents={agents} />
                    </div>

                    {/* KPI grid */}
                    <div className="stagger grid grid-cols-2 gap-3 lg:grid-cols-4">
                        {kpis.map((k, i) => {
                            const positive = k.delta >= 0;
                            return (
                                <Card key={k.label} className="p-4" style={{ '--i': i } as React.CSSProperties}>
                                    <p className="text-[13px] text-secondary">{k.label}</p>
                                    <div className="mt-1 flex items-end justify-between">
                                        <span className="text-2xl font-semibold tracking-tight tnum">{k.value}</span>
                                        {k.delta !== 0 && (
                                            <span
                                                className={cn(
                                                    'mb-1 flex items-center gap-0.5 text-[12px] font-medium',
                                                    positive ? 'text-success' : 'text-danger',
                                                )}
                                            >
                                                {positive ? <ArrowUpRight className="size-3.5" /> : <ArrowDownRight className="size-3.5" />}
                                                {Math.abs(k.delta)}%
                                            </span>
                                        )}
                                    </div>
                                    <div className="mt-2">
                                        <Sparkline data={k.spark} positive={positive} />
                                    </div>
                                </Card>
                            );
                        })}
                    </div>

                    {/* Revenue + recovered */}
                    <div className="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-3">
                        <Card className="p-5 lg:col-span-2">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-[13px] text-secondary">Revenue attributed to chat</p>
                                    <p className="mt-1 text-3xl font-semibold tracking-tight tnum">{money(totalRevenue)}</p>
                                </div>
                                <div className="flex items-center gap-1.5 rounded-full bg-success-subtle px-2.5 py-1 text-[12px] font-medium text-success">
                                    <TrendingUp className="size-3.5" /> chat orders
                                </div>
                            </div>
                            <div className="mt-6">
                                <BarChart data={revenueTrend} format={(v) => money(v)} />
                            </div>
                        </Card>

                        <Card className="flex flex-col justify-center p-5">
                            <p className="text-[13px] text-secondary">Abandoned-cart recovered</p>
                            <p className="mt-1 text-3xl font-semibold tracking-tight tnum">{money(recovered)}</p>
                            <p className="mt-3 text-[13px] text-secondary">
                                Recovered revenue from automation. See{' '}
                                <Link href="/automations" className="text-accent hover:underline">automations</Link>.
                            </p>
                        </Card>
                    </div>

                    {/* Agent leaderboard */}
                    <Card className="mt-3 overflow-hidden">
                        <div className="flex items-center justify-between border-b border-default px-5 py-3.5">
                            <h3 className="text-sm font-semibold">Agent performance</h3>
                            <Link href="/reports/agents" className="text-[13px] font-medium text-accent hover:underline">
                                Full report →
                            </Link>
                        </div>
                        <div className="overflow-x-auto">
                        <table className="w-full min-w-[520px] text-sm">
                            <thead>
                                <tr className="text-[12px] uppercase tracking-wide text-tertiary">
                                    <th className="px-5 py-2.5 text-start font-medium">Agent</th>
                                    <th className="px-5 py-2.5 text-end font-medium">Handled</th>
                                    <th className="px-5 py-2.5 text-end font-medium">Avg response</th>
                                    <th className="px-5 py-2.5 text-end font-medium">Resolution</th>
                                    <th className="px-5 py-2.5 text-end font-medium">CSAT</th>
                                </tr>
                            </thead>
                            <tbody>
                                {leaderboard.length === 0 ? (
                                    <tr><td colSpan={5} className="px-5 py-8 text-center text-tertiary">No activity in this range</td></tr>
                                ) : (
                                    leaderboard.map((a) => (
                                        <tr
                                            key={a.id}
                                            onClick={() => router.visit(`/reports/agents/${a.id}`)}
                                            className="cursor-pointer border-t border-default hover:bg-surface-hover"
                                        >
                                            <td className="px-5 py-3 font-medium">{a.name}</td>
                                            <td className="px-5 py-3 text-end tnum">{a.handled}</td>
                                            <td className="px-5 py-3 text-end tnum text-secondary">{a.avg_response}</td>
                                            <td className="px-5 py-3 text-end tnum">{a.resolution_rate}%</td>
                                            <td className="px-5 py-3 text-end">
                                                <span className="font-medium text-success tnum">{a.csat ?? '—'}</span>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                        </div>
                    </Card>

                    {ai && (
                        <Card className="mt-3 p-5">
                            <div className="mb-4 flex items-center justify-between">
                                <h3 className="flex items-center gap-1.5 text-sm font-semibold">
                                    <Sparkles className="size-4 text-accent" /> AI assistant
                                </h3>
                                <Link href="/settings/ai-agents" className="text-[13px] font-medium text-accent hover:underline">
                                    Configure →
                                </Link>
                            </div>
                            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                                <AiStat label="Conversations engaged" value={ai.engaged} />
                                <AiStat label="Replies sent" value={ai.replies} />
                                <AiStat label="Leads captured" value={ai.leads} />
                                <AiStat label="Orders created" value={ai.orders} />
                                <AiStat label="Handoffs" value={ai.handoffs} />
                                <AiStat label="Close rate" value={`${ai.conversion_rate}%`} accent />
                            </div>
                        </Card>
                    )}
                </div>
            </div>
        </AppShell>
    );
}

function AiStat({ label, value, accent }: { label: string; value: number | string; accent?: boolean }) {
    return (
        <div>
            <p className={cn('text-2xl font-semibold tracking-tight tnum', accent && 'text-success')}>{value}</p>
            <p className="mt-0.5 text-[12px] text-secondary">{label}</p>
        </div>
    );
}
