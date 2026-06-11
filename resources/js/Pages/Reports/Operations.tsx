import { Head } from '@inertiajs/react';
import { Download, MessagesSquare, CircleCheck, Timer, Gauge, ShieldCheck, Bot } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { AppShell } from '@/components/shell/AppShell';
import { Page } from '@/components/shell/Page';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { FilterBar, type AnalyticsFilterState } from '@/components/analytics/FilterBar';
import { BarChart } from '@/components/analytics/BarChart';
import { Heatmap } from '@/components/analytics/Heatmap';
import { cn } from '@/lib/utils';

interface ChannelRow {
    channel: string;
    conversations: number;
    avg_response: string;
    resolution_rate: number;
    csat: number | null;
}
interface Ops {
    total: number;
    resolved: number;
    resolution_rate: number;
    unassigned: number;
    median_first_response: string;
    p90_first_response: string;
    median_resolution: string;
    p90_resolution: string;
    sla_attainment: number;
    sla_breaches: number;
    handoff_rate: number;
    response_distribution: Record<string, number>;
    volume: { day: string; value: number }[];
    heatmap: { grid: number[][]; max: number };
    by_channel: ChannelRow[];
    status_split: { label: string; value: number; tone: 'success' | 'info' | 'warning' }[];
}
interface Props {
    ops: Ops;
    channels: { channel: string; name: string }[];
    agents: { id: number; name: string }[];
    filters: AnalyticsFilterState;
}

const toneBar: Record<string, string> = { success: 'bg-success', info: 'bg-info', warning: 'bg-warning' };
const toneText: Record<string, string> = { success: 'text-success', info: 'text-info', warning: 'text-warning' };

function qs(filters: AnalyticsFilterState, extra: Record<string, string>) {
    const p = new URLSearchParams({ range: filters.range, ...extra });
    if (filters.channel) p.set('channel', filters.channel);
    if (filters.agent) p.set('agent', String(filters.agent));
    return p.toString();
}

function Kpi({ icon: Icon, label, value, sub, tone }: { icon: LucideIcon; label: string; value: string; sub?: string; tone?: string }) {
    return (
        <Card className="p-4">
            <div className="flex items-center justify-between">
                <p className="text-[13px] text-secondary">{label}</p>
                <span className={cn('flex size-7 items-center justify-center rounded-[8px] bg-accent-subtle', tone ?? 'text-accent')}>
                    <Icon className="size-4" />
                </span>
            </div>
            <p className="mt-2 text-2xl font-semibold tracking-tight tnum">{value}</p>
            {sub && <p className="mt-0.5 text-[12px] text-tertiary">{sub}</p>}
        </Card>
    );
}

export default function Operations({ ops, channels, agents, filters }: Props) {
    const distEntries = Object.entries(ops.response_distribution);
    const distMax = Math.max(...distEntries.map(([, v]) => v), 1);
    const splitTotal = ops.status_split.reduce((s, x) => s + x.value, 0) || 1;

    return (
        <AppShell title="Operations">
            <Head title="Operations" />
            <Page
                title="Operations"
                description="Granular response, resolution and staffing analytics."
                actions={
                    <a href={`/reports/operations?${qs(filters, { export: 'csv' })}`}>
                        <Button variant="secondary"><Download className="size-4" /> Export CSV</Button>
                    </a>
                }
            >
                <div className="mb-3">
                    <FilterBar routeUrl="/reports/operations" filters={filters} channels={channels} agents={agents} />
                </div>

                {/* KPI grid */}
                <div className="stagger grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-6">
                    <Kpi icon={MessagesSquare} label="Conversations" value={ops.total.toLocaleString()} sub={`${ops.unassigned} unassigned`} />
                    <Kpi icon={CircleCheck} label="Resolution rate" value={`${ops.resolution_rate}%`} sub={`${ops.resolved} resolved`} />
                    <Kpi icon={Timer} label="Median 1st response" value={ops.median_first_response} sub={`p90 ${ops.p90_first_response}`} />
                    <Kpi icon={Gauge} label="Median resolution" value={ops.median_resolution} sub={`p90 ${ops.p90_resolution}`} />
                    <Kpi icon={ShieldCheck} label="SLA attainment" value={`${ops.sla_attainment}%`} sub={`${ops.sla_breaches} breaches`} tone={ops.sla_attainment >= 90 ? 'text-success' : 'text-warning'} />
                    <Kpi icon={Bot} label="AI → human handoff" value={`${ops.handoff_rate}%`} sub="of AI-engaged chats" />
                </div>

                {/* Status split + response distribution */}
                <div className="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-2">
                    <Card className="p-5">
                        <h3 className="text-sm font-semibold">Conversation status</h3>
                        <div className="mt-4 flex h-2.5 overflow-hidden rounded-full bg-surface-2">
                            {ops.status_split.map((s) => (
                                <div key={s.label} className={cn('h-full', toneBar[s.tone])} style={{ width: `${(s.value / splitTotal) * 100}%` }} />
                            ))}
                        </div>
                        <div className="mt-4 space-y-2">
                            {ops.status_split.map((s) => (
                                <div key={s.label} className="flex items-center justify-between text-sm">
                                    <span className="flex items-center gap-2">
                                        <span className={cn('size-2.5 rounded-full', toneBar[s.tone])} />
                                        {s.label}
                                    </span>
                                    <span className="tnum text-secondary">{s.value.toLocaleString()}</span>
                                </div>
                            ))}
                        </div>
                    </Card>

                    <Card className="p-5">
                        <h3 className="text-sm font-semibold">First-response time</h3>
                        <div className="mt-4 space-y-2.5">
                            {distEntries.map(([bucket, v]) => (
                                <div key={bucket} className="flex items-center gap-3 text-[13px]">
                                    <span className="w-12 shrink-0 text-tertiary">{bucket}</span>
                                    <div className="h-2.5 flex-1 overflow-hidden rounded-full bg-surface-2">
                                        <div className="brand-gradient h-full rounded-full" style={{ width: `${(v / distMax) * 100}%` }} />
                                    </div>
                                    <span className="w-8 shrink-0 text-end tnum text-secondary">{v}</span>
                                </div>
                            ))}
                        </div>
                    </Card>
                </div>

                {/* Volume trend */}
                <Card className="mt-3 p-5">
                    <h3 className="mb-4 text-sm font-semibold">Conversation volume</h3>
                    <BarChart data={ops.volume} className="h-40" />
                </Card>

                {/* Peak-hours heatmap */}
                <Card className="mt-3 p-5">
                    <div className="mb-4">
                        <h3 className="text-sm font-semibold">Peak hours</h3>
                        <p className="text-[12px] text-secondary">When conversations start, by weekday and hour — use it to plan coverage.</p>
                    </div>
                    <Heatmap grid={ops.heatmap.grid} max={ops.heatmap.max} />
                </Card>

                {/* Channel breakdown */}
                <Card className="mt-3 overflow-hidden">
                    <div className="border-b border-default px-5 py-3.5">
                        <h3 className="text-sm font-semibold">By channel</h3>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[520px] text-sm">
                            <thead>
                                <tr className="bg-surface-2/50 text-[11px] font-semibold uppercase tracking-wider text-tertiary">
                                    <th className="px-5 py-2.5 text-start font-medium">Channel</th>
                                    <th className="px-5 py-2.5 text-end font-medium">Conversations</th>
                                    <th className="px-5 py-2.5 text-end font-medium">Avg response</th>
                                    <th className="px-5 py-2.5 text-end font-medium">Resolution</th>
                                    <th className="px-5 py-2.5 text-end font-medium">CSAT</th>
                                </tr>
                            </thead>
                            <tbody>
                                {ops.by_channel.length === 0 ? (
                                    <tr><td colSpan={5} className="px-5 py-8 text-center text-tertiary">No activity in this range</td></tr>
                                ) : (
                                    ops.by_channel.map((c) => (
                                        <tr key={c.channel} className="border-t border-default">
                                            <td className="px-5 py-3 font-medium capitalize">{c.channel}</td>
                                            <td className="px-5 py-3 text-end tnum">{c.conversations}</td>
                                            <td className="px-5 py-3 text-end tnum text-secondary">{c.avg_response}</td>
                                            <td className="px-5 py-3 text-end tnum">{c.resolution_rate}%</td>
                                            <td className="px-5 py-3 text-end tnum">
                                                <span className={c.csat ? toneText.success : 'text-tertiary'}>{c.csat ?? '—'}</span>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </Page>
        </AppShell>
    );
}
