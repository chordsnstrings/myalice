import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { AppShell } from '@/components/shell/AppShell';
import { Page } from '@/components/shell/Page';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { FilterBar, type AnalyticsFilterState } from '@/components/analytics/FilterBar';
import { BarChart } from '@/components/analytics/BarChart';
import { money } from '@/lib/utils';

interface Sales {
    revenue: number;
    orders: number;
    aov: number;
    conversion_rate: number;
    discount_total: number;
    discounted_orders: number;
    funnel: { label: string; value: number }[];
    top_products: { title: string; qty: number }[];
    by_agent: { name: string; revenue: number; handled: number }[];
    trend: { day: string; value: number }[];
    aov_trend: { day: string; value: number }[];
}
interface Props {
    sales: Sales;
    channels: { channel: string; name: string }[];
    agents: { id: number; name: string }[];
    filters: AnalyticsFilterState;
}

function qs(filters: AnalyticsFilterState, extra: Record<string, string>) {
    const p = new URLSearchParams({ range: filters.range, ...extra });
    if (filters.channel) p.set('channel', filters.channel);
    if (filters.agent) p.set('agent', String(filters.agent));
    if (filters.group && filters.group !== 'day') p.set('group', filters.group);
    if (filters.range === 'custom') {
        if (filters.from) p.set('from', filters.from);
        if (filters.to) p.set('to', filters.to);
    }
    return p.toString();
}

export default function Sales({ sales, channels, agents, filters }: Props) {
    const kpis: [string, string][] = [
        ['Revenue from chat', money(sales.revenue)],
        ['Orders', String(sales.orders)],
        ['Avg order value', money(sales.aov)],
        ['Conversion rate', `${sales.conversion_rate}%`],
        ['Discount given', money(sales.discount_total)],
        ['Discounted orders', String(sales.discounted_orders)],
    ];
    const funnelMax = Math.max(...sales.funnel.map((s) => s.value), 1);
    const topMax = Math.max(...sales.top_products.map((p) => p.qty), 1);

    return (
        <AppShell title="Sales & conversion">
            <Head title="Sales & conversion" />
            <Page
                title="Sales & conversion"
                description="Revenue, discounts and conversion attributable to conversations."
                actions={
                    <a href={`/reports/sales?${qs(filters, { export: 'csv' })}`}>
                        <Button variant="secondary"><Download className="size-4" /> Export CSV</Button>
                    </a>
                }
            >
                <div className="mb-3">
                    <FilterBar routeUrl="/reports/sales" filters={filters} channels={channels} agents={agents} />
                </div>

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-6">
                    {kpis.map(([label, value]) => (
                        <Card key={label} className="p-4">
                            <p className="text-[13px] text-secondary">{label}</p>
                            <p className="mt-1 text-2xl font-semibold tracking-tight tnum">{value}</p>
                        </Card>
                    ))}
                </div>

                {/* Funnel + top products */}
                <div className="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-2">
                    <Card className="p-5">
                        <h3 className="text-sm font-semibold">Conversation → order funnel</h3>
                        <div className="mt-4 space-y-3">
                            {sales.funnel.map((s, i) => {
                                const prev = i > 0 ? sales.funnel[i - 1].value : null;
                                const conv = prev && prev > 0 ? Math.round((s.value / prev) * 100) : null;
                                return (
                                    <div key={s.label}>
                                        <div className="mb-1 flex items-center justify-between text-[13px]">
                                            <span className="text-secondary">{s.label}</span>
                                            <span className="tnum font-medium">
                                                {s.value.toLocaleString()}
                                                {conv !== null && <span className="ms-2 text-[12px] font-normal text-tertiary">{conv}%</span>}
                                            </span>
                                        </div>
                                        <div className="h-3 overflow-hidden rounded-full bg-surface-2">
                                            <div className="brand-gradient h-full rounded-full" style={{ width: `${(s.value / funnelMax) * 100}%` }} />
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </Card>

                    <Card className="p-5">
                        <h3 className="text-sm font-semibold">Top products</h3>
                        <div className="mt-4 space-y-2.5">
                            {sales.top_products.length === 0 ? (
                                <p className="text-[13px] text-tertiary">No chat orders in this range.</p>
                            ) : (
                                sales.top_products.map((p) => (
                                    <div key={p.title} className="flex items-center gap-3 text-[13px]">
                                        <span className="w-40 shrink-0 truncate text-secondary">{p.title}</span>
                                        <div className="h-2.5 flex-1 overflow-hidden rounded-full bg-surface-2">
                                            <div className="h-full rounded-full bg-accent" style={{ width: `${(p.qty / topMax) * 100}%` }} />
                                        </div>
                                        <span className="w-8 shrink-0 text-end tnum text-secondary">{p.qty}</span>
                                    </div>
                                ))
                            )}
                        </div>
                    </Card>
                </div>

                {/* Trends */}
                <div className="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-2">
                    <Card className="p-5">
                        <h3 className="mb-4 text-sm font-semibold">Chat revenue</h3>
                        <BarChart data={sales.trend} format={(v) => money(v)} className="h-36" />
                    </Card>
                    <Card className="p-5">
                        <h3 className="mb-4 text-sm font-semibold">Average order value</h3>
                        <BarChart data={sales.aov_trend} format={(v) => money(v)} className="h-36" />
                    </Card>
                </div>

                <Card className="mt-3 overflow-hidden">
                    <div className="border-b border-default px-5 py-3.5">
                        <h3 className="text-sm font-semibold">Revenue by agent</h3>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[420px] text-sm">
                            <thead>
                                <tr className="bg-surface-2/50 text-[11px] font-semibold uppercase tracking-wider text-tertiary">
                                    <th className="px-5 py-2.5 text-start font-medium">Agent</th>
                                    <th className="px-5 py-2.5 text-end font-medium">Handled</th>
                                    <th className="px-5 py-2.5 text-end font-medium">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                {sales.by_agent.map((a) => (
                                    <tr key={a.name} className="border-t border-default hover:bg-surface-hover">
                                        <td className="px-5 py-3 font-medium">{a.name}</td>
                                        <td className="px-5 py-3 text-end tnum">{a.handled}</td>
                                        <td className="px-5 py-3 text-end tnum">{money(a.revenue)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </Page>
        </AppShell>
    );
}
