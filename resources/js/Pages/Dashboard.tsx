import { Head } from '@inertiajs/react';
import { ArrowUpRight, ArrowDownRight, TrendingUp } from 'lucide-react';
import { AppShell } from '@/components/shell/AppShell';
import { Card } from '@/components/ui/Card';
import { cn, money } from '@/lib/utils';

interface Kpi {
    label: string;
    value: string;
    delta: number;
    spark: number[];
}

interface Agent {
    name: string;
    handled: number;
    csat: number;
    response: string;
}

interface Props {
    kpis: Kpi[];
    agents: Agent[];
    revenue: number;
    recovered: number;
}

function Sparkline({ data, positive }: { data: number[]; positive: boolean }) {
    const max = Math.max(...data);
    const min = Math.min(...data);
    const range = max - min || 1;
    const pts = data
        .map((v, i) => `${(i / (data.length - 1)) * 100},${28 - ((v - min) / range) * 24 - 2}`)
        .join(' ');
    return (
        <svg viewBox="0 0 100 28" preserveAspectRatio="none" className="h-8 w-full">
            <polyline
                points={pts}
                fill="none"
                stroke={positive ? 'var(--accent)' : 'var(--danger)'}
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
                vectorEffect="non-scaling-stroke"
            />
        </svg>
    );
}

export default function Dashboard({ kpis, agents, revenue, recovered }: Props) {
    return (
        <AppShell title="Analytics">
            <Head title="Dashboard" />
            <div className="h-full overflow-y-auto">
                <div className="mx-auto max-w-[1200px] px-6 py-6">
                    <div className="mb-5 flex items-center justify-between">
                        <div>
                            <h2 className="text-xl font-semibold tracking-tight">Overview</h2>
                            <p className="text-[13px] text-secondary">Last 7 days · all channels</p>
                        </div>
                        <div className="flex gap-1.5">
                            {['7d', '30d', '90d'].map((r, i) => (
                                <button
                                    key={r}
                                    className={cn(
                                        'h-8 rounded-[var(--radius-control)] px-3 text-[13px] font-medium',
                                        i === 0
                                            ? 'bg-accent-subtle text-accent'
                                            : 'text-secondary hover:bg-surface-hover',
                                    )}
                                >
                                    {r}
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* KPI grid */}
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        {kpis.map((k) => {
                            const positive = k.delta >= 0;
                            return (
                                <Card key={k.label} className="p-4">
                                    <p className="text-[13px] text-secondary">{k.label}</p>
                                    <div className="mt-1 flex items-end justify-between">
                                        <span className="text-2xl font-semibold tracking-tight tnum">{k.value}</span>
                                        <span
                                            className={cn(
                                                'mb-1 flex items-center gap-0.5 text-[12px] font-medium',
                                                positive ? 'text-success' : 'text-danger',
                                            )}
                                        >
                                            {positive ? (
                                                <ArrowUpRight className="size-3.5" />
                                            ) : (
                                                <ArrowDownRight className="size-3.5" />
                                            )}
                                            {Math.abs(k.delta)}%
                                        </span>
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
                                    <p className="mt-1 text-3xl font-semibold tracking-tight tnum">
                                        {money(revenue)}
                                    </p>
                                </div>
                                <div className="flex items-center gap-1.5 rounded-full bg-success-subtle px-2.5 py-1 text-[12px] font-medium text-success">
                                    <TrendingUp className="size-3.5" /> +18% vs prev
                                </div>
                            </div>
                            <div className="mt-6 flex h-32 items-end gap-1.5">
                                {[40, 55, 48, 70, 62, 85, 78, 92, 88, 100, 95, 110].map((h, i) => (
                                    <div
                                        key={i}
                                        className="flex-1 rounded-t bg-accent/80 transition-colors hover:bg-accent"
                                        style={{ height: `${h * 0.7}%` }}
                                    />
                                ))}
                            </div>
                        </Card>

                        <Card className="flex flex-col justify-center p-5">
                            <p className="text-[13px] text-secondary">Abandoned-cart recovered</p>
                            <p className="mt-1 text-3xl font-semibold tracking-tight tnum">{money(recovered)}</p>
                            <div className="mt-4 space-y-2 text-[13px]">
                                <div className="flex justify-between">
                                    <span className="text-secondary">Carts nudged</span>
                                    <span className="font-medium tnum">1,204</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-secondary">Recovered</span>
                                    <span className="font-medium tnum">312</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-secondary">Recovery rate</span>
                                    <span className="font-medium text-success tnum">25.9%</span>
                                </div>
                            </div>
                        </Card>
                    </div>

                    {/* Agent leaderboard */}
                    <Card className="mt-3 overflow-hidden">
                        <div className="border-b border-default px-5 py-3.5">
                            <h3 className="text-sm font-semibold">Agent performance</h3>
                        </div>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-start text-[12px] uppercase tracking-wide text-tertiary">
                                    <th className="px-5 py-2.5 text-start font-medium">Agent</th>
                                    <th className="px-5 py-2.5 text-end font-medium">Handled</th>
                                    <th className="px-5 py-2.5 text-end font-medium">Avg response</th>
                                    <th className="px-5 py-2.5 text-end font-medium">CSAT</th>
                                </tr>
                            </thead>
                            <tbody>
                                {agents.map((a) => (
                                    <tr key={a.name} className="border-t border-default hover:bg-surface-hover">
                                        <td className="px-5 py-3 font-medium">{a.name}</td>
                                        <td className="px-5 py-3 text-end tnum">{a.handled}</td>
                                        <td className="px-5 py-3 text-end tnum text-secondary">{a.response}</td>
                                        <td className="px-5 py-3 text-end">
                                            <span className="font-medium text-success tnum">{a.csat}%</span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </Card>
                </div>
            </div>
        </AppShell>
    );
}
