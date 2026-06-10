import { Head, Link, router } from '@inertiajs/react';
import { Plus, FileText } from 'lucide-react';
import { AppShell } from '@/components/shell/AppShell';
import { Page } from '@/components/shell/Page';
import { Table, type Column } from '@/components/ui/Table';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { FirstUseEmpty } from '@/components/ui/States';
import { money, relativeTime } from '@/lib/utils';

interface Summary {
    sent: number;
    delivered: number;
    read: number;
    replied: number;
    delivery_rate: number;
    read_rate: number;
    reply_rate: number;
    spend: number;
}

interface Broadcast {
    id: number;
    name: string;
    template: string | null;
    status: string;
    recipients: number;
    delivered: number;
    read: number;
    replied: number;
    credit_cost: number;
    schedule_at: string | null;
}

const tone: Record<string, 'neutral' | 'warning' | 'success' | 'info' | 'danger'> = {
    draft: 'neutral',
    scheduled: 'info',
    launching: 'warning',
    sending: 'warning',
    sent: 'success',
    completed: 'success',
    paused: 'warning',
    canceled: 'neutral',
    failed: 'danger',
};

export default function BroadcastsIndex({ broadcasts, summary }: { broadcasts: Broadcast[]; summary: Summary }) {
    const columns: Column<Broadcast>[] = [
        {
            key: 'name',
            header: 'Broadcast',
            render: (b) => (
                <Link href={`/broadcasts/${b.id}`} className="block">
                    <p className="font-medium text-accent hover:underline">{b.name}</p>
                    {b.template && <p className="text-[12px] text-tertiary">{b.template}</p>}
                </Link>
            ),
        },
        { key: 'status', header: 'Status', render: (b) => <Badge tone={tone[b.status] ?? 'neutral'}>{b.status}</Badge> },
        { key: 'recipients', header: 'Recipients', align: 'end', render: (b) => b.recipients.toLocaleString() },
        {
            key: 'engagement',
            header: 'Delivered / Read',
            align: 'end',
            render: (b) =>
                b.status === 'sent' ? (
                    <span className="text-secondary">
                        {b.delivered.toLocaleString()} / {b.read.toLocaleString()}
                    </span>
                ) : (
                    <span className="text-tertiary">—</span>
                ),
        },
        { key: 'cost', header: 'Cost', align: 'end', render: (b) => money(b.credit_cost) },
        {
            key: 'when',
            header: 'Schedule',
            align: 'end',
            render: (b) => <span className="text-secondary">{b.schedule_at ? relativeTime(b.schedule_at) : '—'}</span>,
        },
    ];

    return (
        <AppShell title="Broadcasts">
            <Head title="Broadcasts" />
            <Page
                title="Broadcasts"
                actions={
                    <>
                        <Button variant="secondary" onClick={() => router.visit('/templates')}>
                            <FileText className="size-4" /> Templates
                        </Button>
                        <Button onClick={() => router.visit('/broadcasts/create')}>
                            <Plus className="size-4" /> New broadcast
                        </Button>
                    </>
                }
            >
                {summary.sent > 0 && (
                    <div className="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                        <SummaryStat label="Sent (30d)" value={summary.sent.toLocaleString()} />
                        <SummaryStat label="Delivered" value={`${summary.delivery_rate}%`} />
                        <SummaryStat label="Read" value={`${summary.read_rate}%`} />
                        <SummaryStat label="Replied" value={`${summary.reply_rate}%`} />
                        <SummaryStat label="Replies" value={summary.replied.toLocaleString()} />
                        <SummaryStat label="Spend" value={money(summary.spend)} />
                    </div>
                )}
                <Table
                    columns={columns}
                    rows={broadcasts}
                    empty={
                        <FirstUseEmpty
                            title="Send your first campaign"
                            description="Reach your audience with compliant, costed WhatsApp template broadcasts."
                            action={<Link href="/broadcasts/create"><Button>New broadcast</Button></Link>}
                        />
                    }
                />
            </Page>
        </AppShell>
    );
}

function SummaryStat({ label, value }: { label: string; value: string }) {
    return (
        <Card className="p-3.5">
            <p className="text-xl font-semibold tracking-tight tnum">{value}</p>
            <p className="mt-0.5 text-[12px] text-secondary">{label}</p>
        </Card>
    );
}
