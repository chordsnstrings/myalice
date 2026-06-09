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
    by_channel: { channel: string; conversations: number }[];
    by_agent: { name: string; revenue: number; handled: number }[];
    trend: { day: string; value: number }[];
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
    return p.toString();
}

export default function Sales({ sales, channels, agents, filters }: Props) {
    const kpis = [
        ['Revenue from chat', money(sales.revenue)],
        ['Orders', String(sales.orders)],
        ['Avg order value', money(sales.aov)],
        ['Conversion rate', `${sales.conversion_rate}%`],
    ];

    return (
        <AppShell title="Sales & conversion">
            <Head title="Sales & conversion" />
            <Page
                title="Sales & conversion"
                description="Revenue and conversion attributable to conversations."
                actions={
                    <a href={`/reports/sales?${qs(filters, { export: 'csv' })}`}>
                        <Button variant="secondary"><Download className="size-4" /> Export CSV</Button>
                    </a>
                }
            >
                <div className="mb-3">
                    <FilterBar routeUrl="/reports/sales" filters={filters} channels={channels} agents={agents} />
                </div>

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {kpis.map(([label, value]) => (
                        <Card key={label} className="p-4">
                            <p className="text-[13px] text-secondary">{label}</p>
                            <p className="mt-1 text-2xl font-semibold tracking-tight tnum">{value}</p>
                        </Card>
                    ))}
                </div>

                <Card className="mt-3 p-5">
                    <h3 className="mb-3 text-sm font-semibold">Chat revenue over time</h3>
                    <BarChart data={sales.trend} format={(v) => money(v)} />
                </Card>

                <Card className="mt-3 overflow-hidden">
                    <div className="border-b border-default px-5 py-3.5">
                        <h3 className="text-sm font-semibold">Revenue by agent</h3>
                    </div>
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-[12px] uppercase tracking-wide text-tertiary">
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
                </Card>
            </Page>
        </AppShell>
    );
}
