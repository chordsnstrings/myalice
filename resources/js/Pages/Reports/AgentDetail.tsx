import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Clock } from 'lucide-react';
import { AppShell } from '@/components/shell/AppShell';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Tooltip } from '@/components/ui/Tooltip';
import { LineChart } from '@/components/analytics/LineChart';
import { relativeTime } from '@/lib/utils';

interface Detail {
    agent: { id: number; name: string };
    kpis: { label: string; value: string }[];
    volume: { day: string; value: number }[];
    response_distribution: Record<string, number>;
    comments: { rating: number; comment: string; channel: string; at: string }[];
    active_hours: number;
}

export default function AgentDetail({ detail }: { detail: Detail }) {
    const distMax = Math.max(...Object.values(detail.response_distribution), 1);

    return (
        <AppShell title={detail.agent.name}>
            <Head title={detail.agent.name} />
            <div className="h-full overflow-y-auto">
                <div className="mx-auto max-w-[1000px] px-6 py-6">
                    <Link href="/reports/agents" className="mb-4 inline-flex items-center gap-1.5 text-[13px] text-secondary hover:text-primary">
                        <ArrowLeft className="size-4" /> Agent performance
                    </Link>
                    <h2 className="text-xl font-semibold tracking-tight">{detail.agent.name}</h2>

                    <div className="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                        {detail.kpis.map((k) => (
                            <Card key={k.label} className="p-4">
                                <p className="text-[13px] text-secondary">{k.label}</p>
                                <p className="mt-1 text-2xl font-semibold tracking-tight tnum">{k.value}</p>
                            </Card>
                        ))}
                    </div>

                    <div className="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-3">
                        <Card className="p-5 lg:col-span-2">
                            <h3 className="mb-3 text-sm font-semibold">Daily volume</h3>
                            <LineChart data={detail.volume} />
                        </Card>
                        <Card className="p-5">
                            <h3 className="mb-3 text-sm font-semibold">Response time</h3>
                            <div className="space-y-2">
                                {Object.entries(detail.response_distribution).map(([bucket, n]) => (
                                    <div key={bucket} className="flex items-center gap-2 text-[13px]">
                                        <span className="w-14 text-tertiary">{bucket}</span>
                                        <div className="h-2 flex-1 overflow-hidden rounded-full bg-surface-2">
                                            <div className="h-full rounded-full bg-accent" style={{ width: `${(n / distMax) * 100}%` }} />
                                        </div>
                                        <span className="w-6 text-end tnum">{n}</span>
                                    </div>
                                ))}
                            </div>
                            <div className="mt-4 flex items-center gap-2 border-t border-default pt-3 text-[13px] text-secondary">
                                <Clock className="size-4" />
                                <Tooltip label="Distinct hours with an outbound agent message — a proxy, not real presence tracking.">
                                    <span>Active hours (proxy): <span className="font-medium text-primary tnum">{detail.active_hours}</span></span>
                                </Tooltip>
                            </div>
                        </Card>
                    </div>

                    <Card className="mt-3 p-5">
                        <h3 className="mb-3 text-sm font-semibold">Recent CSAT feedback</h3>
                        {detail.comments.length === 0 ? (
                            <p className="text-[13px] text-tertiary">No comments in this range.</p>
                        ) : (
                            <div className="space-y-3">
                                {detail.comments.map((c, i) => (
                                    <div key={i} className="border-b border-default pb-3 last:border-0 last:pb-0">
                                        <div className="flex items-center gap-2">
                                            <Badge tone={c.rating >= 4 ? 'success' : c.rating >= 3 ? 'warning' : 'danger'}>{c.rating}/5</Badge>
                                            <span className="text-[12px] capitalize text-tertiary">{c.channel} · {relativeTime(c.at)}</span>
                                        </div>
                                        <p className="mt-1 text-[13px] text-secondary">{c.comment}</p>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Card>
                </div>
            </div>
        </AppShell>
    );
}
