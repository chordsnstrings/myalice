import { Head, router } from '@inertiajs/react';
import { Pause, Play, X } from 'lucide-react';
import { AppShell } from '@/components/shell/AppShell';
import { Page } from '@/components/shell/Page';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { money } from '@/lib/utils';

interface Broadcast {
    id: number;
    name: string;
    channel: string;
    status: string;
    template: string | null;
    recipients: number;
    delivered: number;
    read: number;
    replied: number;
    failed: number;
    reserved_cost: number;
    spent_cost: number;
    schedule_at: string | null;
    started_at: string | null;
    completed_at: string | null;
}

const tone: Record<string, 'neutral' | 'warning' | 'success' | 'info' | 'danger'> = {
    draft: 'neutral', scheduled: 'info', launching: 'warning', sending: 'warning',
    completed: 'success', paused: 'warning', canceled: 'neutral', failed: 'danger',
};

export default function BroadcastShow({ broadcast, breakdown }: { broadcast: Broadcast; breakdown: Record<string, number> }) {
    const act = (verb: 'pause' | 'resume', method: 'put') =>
        router[method](`/broadcasts/${broadcast.id}/${verb}`, {}, { preserveScroll: true });
    const cancel = () => {
        if (confirm('Cancel this broadcast? Unsent reserve will be refunded.')) {
            router.delete(`/broadcasts/${broadcast.id}`, { preserveScroll: true });
        }
    };

    const stat = (k: string) => breakdown[k] ?? 0;

    return (
        <AppShell title={broadcast.name}>
            <Head title={broadcast.name} />
            <Page
                title={broadcast.name}
                description={`${broadcast.channel} · ${broadcast.template ?? 'no template'}`}
                actions={
                    <div className="flex items-center gap-2">
                        <Badge tone={tone[broadcast.status] ?? 'neutral'}>{broadcast.status}</Badge>
                        {broadcast.status === 'sending' && <Button size="sm" variant="secondary" onClick={() => act('pause', 'put')}><Pause className="size-4" /> Pause</Button>}
                        {broadcast.status === 'paused' && <Button size="sm" variant="secondary" onClick={() => act('resume', 'put')}><Play className="size-4" /> Resume</Button>}
                        {['scheduled', 'sending', 'paused', 'launching'].includes(broadcast.status) && <Button size="sm" variant="danger" onClick={cancel}><X className="size-4" /> Cancel</Button>}
                    </div>
                }
            >
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    <Stat label="Recipients" value={broadcast.recipients} />
                    <Stat label="Sent" value={stat('sent') + broadcast.delivered + broadcast.read + stat('replied')} />
                    <Stat label="Delivered" value={broadcast.delivered} />
                    <Stat label="Read" value={broadcast.read} />
                    <Stat label="Failed" value={broadcast.failed + stat('failed')} />
                    <Stat label="Skipped" value={stat('skipped')} />
                </div>

                <Card className="mt-4 p-5">
                    <p className="mb-3 text-[13px] font-semibold">Spend</p>
                    <div className="grid grid-cols-3 gap-4">
                        <Money label="Reserved" value={broadcast.reserved_cost} />
                        <Money label="Spent" value={broadcast.spent_cost} />
                        <Money label="Refunded" value={Math.max(0, broadcast.reserved_cost - broadcast.spent_cost)} />
                    </div>
                </Card>
            </Page>
        </AppShell>
    );
}

function Stat({ label, value }: { label: string; value: number }) {
    return (
        <Card className="p-4">
            <p className="text-2xl font-semibold tracking-tight tnum">{value.toLocaleString()}</p>
            <p className="mt-0.5 text-[12px] text-secondary">{label}</p>
        </Card>
    );
}

function Money({ label, value }: { label: string; value: number }) {
    return (
        <div>
            <p className="text-lg font-semibold tnum">{money(value)}</p>
            <p className="text-[12px] text-secondary">{label}</p>
        </div>
    );
}
