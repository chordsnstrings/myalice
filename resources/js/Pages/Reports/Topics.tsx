import { Head, router } from '@inertiajs/react';
import { Download, Tags, Layers, PieChart } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { AppShell } from '@/components/shell/AppShell';
import { Page } from '@/components/shell/Page';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { FilterBar, type AnalyticsFilterState } from '@/components/analytics/FilterBar';
import { cn } from '@/lib/utils';

type Tone = 'neutral' | 'accent' | 'success' | 'warning' | 'danger' | 'info';
interface TopicRow {
    id: number;
    name: string;
    color: string;
    count: number;
    share: number;
    resolution_rate: number;
    csat: number | null;
}
interface Topics {
    total: number;
    tagged: number;
    untagged: number;
    coverage: number;
    tags: TopicRow[];
}
interface Props {
    topics: Topics;
    channels: { channel: string; name: string }[];
    agents: { id: number; name: string }[];
    filters: AnalyticsFilterState;
}

const TONES: Tone[] = ['neutral', 'accent', 'success', 'warning', 'danger', 'info'];
const tone = (c: string): Tone => (TONES.includes(c as Tone) ? (c as Tone) : 'accent');

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

function Kpi({ icon: Icon, label, value, sub }: { icon: LucideIcon; label: string; value: string; sub?: string }) {
    return (
        <Card className="p-4">
            <div className="flex items-center justify-between">
                <p className="text-[13px] text-secondary">{label}</p>
                <span className="flex size-7 items-center justify-center rounded-[8px] bg-accent-subtle text-accent">
                    <Icon className="size-4" />
                </span>
            </div>
            <p className="mt-2 text-2xl font-semibold tracking-tight tnum">{value}</p>
            {sub && <p className="mt-0.5 text-[12px] text-tertiary">{sub}</p>}
        </Card>
    );
}

export default function Topics({ topics, channels, agents, filters }: Props) {
    const max = Math.max(...topics.tags.map((t) => t.count), 1);

    return (
        <AppShell title="Topics">
            <Head title="Topics" />
            <Page
                title="Topics"
                description="What customers are contacting you about, from conversation tags."
                actions={
                    <a href={`/reports/topics?${qs(filters, { export: 'csv' })}`}>
                        <Button variant="secondary"><Download className="size-4" /> Export CSV</Button>
                    </a>
                }
            >
                <div className="mb-3">
                    <FilterBar routeUrl="/reports/topics" filters={filters} channels={channels} agents={agents} grouping={false} />
                </div>

                <div className="stagger grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Kpi icon={Layers} label="Conversations" value={topics.total.toLocaleString()} />
                    <Kpi icon={Tags} label="Distinct topics" value={String(topics.tags.length)} />
                    <Kpi icon={PieChart} label="Tagged" value={`${topics.coverage}%`} sub={`${topics.tagged} of ${topics.total}`} />
                    <Kpi icon={Layers} label="Untagged" value={topics.untagged.toLocaleString()} sub="needs triage" />
                </div>

                <Card className="mt-3 overflow-hidden">
                    <div className="border-b border-default px-5 py-3.5">
                        <h3 className="text-sm font-semibold">Topics by volume</h3>
                        <p className="text-[12px] text-tertiary">Click a topic to open those conversations in the inbox.</p>
                    </div>
                    {topics.tags.length === 0 ? (
                        <div className="px-5 py-12 text-center">
                            <Tags className="mx-auto mb-3 size-6 text-tertiary" />
                            <p className="text-sm text-secondary">No tagged conversations in this range.</p>
                            <p className="mt-1 text-[13px] text-tertiary">Tag conversations from the inbox to see topics here.</p>
                        </div>
                    ) : (
                        <div className="divide-y divide-[var(--border)]">
                            {topics.tags.map((t) => (
                                <button
                                    key={t.id}
                                    onClick={() => router.visit(`/inbox?tag=${encodeURIComponent(t.name)}`)}
                                    className="flex w-full items-center gap-4 px-5 py-3 text-start transition-colors hover:bg-surface-hover"
                                >
                                    <div className="flex w-44 shrink-0 items-center gap-2">
                                        <Badge tone={tone(t.color)}>{t.name}</Badge>
                                    </div>
                                    <div className="flex flex-1 items-center gap-3">
                                        <div className="h-2.5 flex-1 overflow-hidden rounded-full bg-surface-2">
                                            <div className="brand-gradient h-full rounded-full" style={{ width: `${(t.count / max) * 100}%` }} />
                                        </div>
                                        <span className="w-10 shrink-0 text-end text-[13px] tnum">{t.count}</span>
                                        <span className="w-12 shrink-0 text-end text-[12px] text-tertiary tnum">{t.share}%</span>
                                    </div>
                                    <div className="hidden w-24 shrink-0 text-end text-[13px] sm:block">
                                        <span className="text-tertiary">res </span>
                                        <span className="tnum">{t.resolution_rate}%</span>
                                    </div>
                                    <div className="hidden w-16 shrink-0 text-end text-[13px] tnum sm:block">
                                        <span className={cn(t.csat ? 'text-success' : 'text-tertiary')}>{t.csat ?? '—'}</span>
                                    </div>
                                </button>
                            ))}
                        </div>
                    )}
                </Card>
            </Page>
        </AppShell>
    );
}
