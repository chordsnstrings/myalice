import { Head, Link, router } from '@inertiajs/react';
import { Plus, FileText } from 'lucide-react';
import { AppShell } from '@/components/shell/AppShell';
import { Page } from '@/components/shell/Page';
import { Table, type Column } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { FirstUseEmpty } from '@/components/ui/States';
import { money, relativeTime } from '@/lib/utils';

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
    sending: 'warning',
    sent: 'success',
    paused: 'warning',
    failed: 'danger',
};

export default function BroadcastsIndex({ broadcasts }: { broadcasts: Broadcast[] }) {
    const columns: Column<Broadcast>[] = [
        {
            key: 'name',
            header: 'Broadcast',
            render: (b) => (
                <div>
                    <p className="font-medium text-primary">{b.name}</p>
                    {b.template && <p className="text-[12px] text-tertiary">{b.template}</p>}
                </div>
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
