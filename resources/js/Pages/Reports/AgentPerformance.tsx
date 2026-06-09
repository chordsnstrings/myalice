import { Head, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { AppShell } from '@/components/shell/AppShell';
import { Page } from '@/components/shell/Page';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { FilterBar, type AnalyticsFilterState } from '@/components/analytics/FilterBar';

interface Agent {
    id: number;
    name: string;
    handled: number;
    avg_response: string;
    resolution_rate: number;
    csat: number | null;
    revenue: number;
}
interface Props {
    leaderboard: Agent[];
    channels: { channel: string; name: string }[];
    agents: { id: number; name: string }[];
    filters: AnalyticsFilterState;
}

function qs(filters: AnalyticsFilterState, extra: Record<string, string>) {
    const p = new URLSearchParams({ range: filters.range, ...extra });
    if (filters.channel) p.set('channel', filters.channel);
    if (filters.agent) p.set('agent', String(filters.agent));
    return p.toString();
}

export default function AgentPerformance({ leaderboard, channels, agents, filters }: Props) {
    return (
        <AppShell title="Agent performance">
            <Head title="Agent performance" />
            <Page
                title="Agent performance"
                description="Response, resolution, satisfaction and sales per team member."
                actions={
                    <a href={`/reports/agents?${qs(filters, { export: 'csv' })}`}>
                        <Button variant="secondary"><Download className="size-4" /> Export CSV</Button>
                    </a>
                }
            >
                <div className="mb-3">
                    <FilterBar routeUrl="/reports/agents" filters={filters} channels={channels} agents={agents} />
                </div>

                <Card className="overflow-x-auto">
                    <table className="w-full min-w-[640px] text-sm">
                        <thead>
                            <tr className="border-b border-default text-[12px] uppercase tracking-wide text-tertiary">
                                <th className="px-4 py-2.5 text-start font-medium">Agent</th>
                                <th className="px-4 py-2.5 text-end font-medium">Handled</th>
                                <th className="px-4 py-2.5 text-end font-medium">Avg response</th>
                                <th className="px-4 py-2.5 text-end font-medium">Resolution</th>
                                <th className="px-4 py-2.5 text-end font-medium">CSAT</th>
                                <th className="px-4 py-2.5 text-end font-medium">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            {leaderboard.length === 0 ? (
                                <tr><td colSpan={6} className="px-4 py-10 text-center text-tertiary">No activity for these filters</td></tr>
                            ) : (
                                leaderboard.map((a) => (
                                    <tr
                                        key={a.id}
                                        onClick={() => router.visit(`/reports/agents/${a.id}?range=${filters.range}`)}
                                        className="cursor-pointer border-b border-default last:border-0 hover:bg-surface-hover"
                                    >
                                        <td className="px-4 py-3 font-medium">{a.name}</td>
                                        <td className="px-4 py-3 text-end tnum">{a.handled}</td>
                                        <td className="px-4 py-3 text-end tnum text-secondary">{a.avg_response}</td>
                                        <td className="px-4 py-3 text-end tnum">{a.resolution_rate}%</td>
                                        <td className="px-4 py-3 text-end tnum text-success">{a.csat ?? '—'}</td>
                                        <td className="px-4 py-3 text-end tnum">${a.revenue.toLocaleString()}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </Card>
            </Page>
        </AppShell>
    );
}
