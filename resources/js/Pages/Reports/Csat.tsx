import { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { AppShell } from '@/components/shell/AppShell';
import { Page } from '@/components/shell/Page';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Tabs } from '@/components/ui/Tabs';
import { FilterBar, type AnalyticsFilterState } from '@/components/analytics/FilterBar';
import { LineChart } from '@/components/analytics/LineChart';
import { relativeTime } from '@/lib/utils';

interface Report {
    average: number;
    responses: number;
    trend: { day: string; value: number }[];
    by_agent: { name: string; average: number; count: number }[];
    by_channel: { channel: string; average: number; count: number }[];
    comments: { rating: number; comment: string; channel: string; agent: string | null; at: string }[];
}
interface Props {
    report: Report;
    channels: { channel: string; name: string }[];
    agents: { id: number; name: string }[];
    filters: AnalyticsFilterState;
}

function qs(filters: AnalyticsFilterState, extra: Record<string, string>) {
    const p = new URLSearchParams({ range: filters.range, ...extra });
    if (filters.channel) p.set('channel', filters.channel);
    return p.toString();
}

export default function Csat({ report, channels, agents, filters }: Props) {
    const [tab, setTab] = useState('agent');

    return (
        <AppShell title="CSAT">
            <Head title="CSAT" />
            <Page
                title="Customer satisfaction"
                description="Post-resolution survey ratings across the team."
                actions={
                    <a href={`/reports/csat?${qs(filters, { export: 'csv' })}`}>
                        <Button variant="secondary"><Download className="size-4" /> Export CSV</Button>
                    </a>
                }
            >
                <div className="mb-3">
                    <FilterBar routeUrl="/reports/csat" filters={filters} channels={channels} agents={agents} />
                </div>

                <div className="grid grid-cols-1 gap-3 lg:grid-cols-3">
                    <Card className="p-5">
                        <p className="text-[13px] text-secondary">Average score</p>
                        <p className="mt-1 text-3xl font-semibold tracking-tight tnum">
                            {report.average > 0 ? report.average : '—'}
                            <span className="text-base font-normal text-tertiary">/5</span>
                        </p>
                        <p className="mt-1 text-[12px] text-tertiary tnum">{report.responses} responses</p>
                    </Card>
                    <Card className="p-5 lg:col-span-2">
                        <h3 className="mb-3 text-sm font-semibold">Score trend</h3>
                        <LineChart data={report.trend} max={5} />
                    </Card>
                </div>

                <Card className="mt-3">
                    <div className="px-5 pt-2">
                        <Tabs
                            tabs={[
                                { id: 'agent', label: 'By agent', count: report.by_agent.length },
                                { id: 'channel', label: 'By channel', count: report.by_channel.length },
                                { id: 'comments', label: 'Comments', count: report.comments.length },
                            ]}
                            active={tab}
                            onChange={setTab}
                        />
                    </div>
                    <div className="p-5">
                        {tab === 'agent' && (
                            <div className="space-y-2">
                                {report.by_agent.map((a) => (
                                    <div key={a.name} className="flex items-center justify-between text-sm">
                                        <span className="font-medium">{a.name}</span>
                                        <span className="tnum"><span className="text-success">{a.average}</span> <span className="text-tertiary">({a.count})</span></span>
                                    </div>
                                ))}
                                {report.by_agent.length === 0 && <p className="text-[13px] text-tertiary">No ratings yet.</p>}
                            </div>
                        )}
                        {tab === 'channel' && (
                            <div className="space-y-2">
                                {report.by_channel.map((c) => (
                                    <div key={c.channel} className="flex items-center justify-between text-sm">
                                        <span className="font-medium capitalize">{c.channel}</span>
                                        <span className="tnum"><span className="text-success">{c.average}</span> <span className="text-tertiary">({c.count})</span></span>
                                    </div>
                                ))}
                                {report.by_channel.length === 0 && <p className="text-[13px] text-tertiary">No ratings yet.</p>}
                            </div>
                        )}
                        {tab === 'comments' && (
                            <div className="space-y-3">
                                {report.comments.map((c, i) => (
                                    <div key={i} className="border-b border-default pb-3 last:border-0 last:pb-0">
                                        <div className="flex items-center gap-2">
                                            <Badge tone={c.rating >= 4 ? 'success' : c.rating >= 3 ? 'warning' : 'danger'}>{c.rating}/5</Badge>
                                            <span className="text-[12px] capitalize text-tertiary">
                                                {c.channel}{c.agent ? ` · ${c.agent}` : ''} · {relativeTime(c.at)}
                                            </span>
                                        </div>
                                        <p className="mt-1 text-[13px] text-secondary">{c.comment}</p>
                                    </div>
                                ))}
                                {report.comments.length === 0 && <p className="text-[13px] text-tertiary">No comments yet.</p>}
                            </div>
                        )}
                    </div>
                </Card>
            </Page>
        </AppShell>
    );
}
