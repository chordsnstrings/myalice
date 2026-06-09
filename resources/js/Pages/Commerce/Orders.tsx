import { Head, Link, router } from '@inertiajs/react';
import { Plus, RefreshCw } from 'lucide-react';
import { AppShell } from '@/components/shell/AppShell';
import { Page } from '@/components/shell/Page';
import { Table, type Column } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { FirstUseEmpty } from '@/components/ui/States';
import { money, relativeTime } from '@/lib/utils';

interface Order {
    id: number;
    number: string;
    customer: string;
    total: number;
    currency: string;
    status: string;
    source: string;
    created_at: string;
}

const tone: Record<string, 'neutral' | 'warning' | 'success' | 'info' | 'danger'> = {
    pending: 'warning',
    paid: 'info',
    fulfilled: 'accent' as 'info',
    delivered: 'success',
    cancelled: 'danger',
};

export default function Orders({ orders, store }: { orders: Order[]; store: { platform: string; last_synced_at: string | null } | null }) {
    const columns: Column<Order>[] = [
        { key: 'number', header: 'Order', render: (o) => <span className="font-medium tnum">{o.number}</span> },
        { key: 'customer', header: 'Customer', render: (o) => o.customer },
        { key: 'source', header: 'Source', render: (o) => <Badge tone="neutral">{o.source}</Badge> },
        { key: 'status', header: 'Status', render: (o) => <Badge tone={tone[o.status] ?? 'neutral'}>{o.status}</Badge> },
        { key: 'date', header: 'Date', align: 'end', render: (o) => <span className="text-secondary">{relativeTime(o.created_at)}</span> },
        { key: 'total', header: 'Total', align: 'end', render: (o) => money(o.total, o.currency) },
    ];

    return (
        <AppShell title="Commerce">
            <Head title="Orders" />
            <Page
                title="Orders"
                description={store ? `Synced from ${store.platform} · updated ${store.last_synced_at}` : undefined}
                actions={
                    <>
                        <Button variant="secondary"><RefreshCw className="size-4" /> Sync</Button>
                        <Button><Plus className="size-4" /> Create order</Button>
                    </>
                }
            >
                <div className="mb-3 flex gap-1.5 text-[13px]">
                    {['All', 'Products', 'Orders'].map((t, i) => (
                        <Link
                            key={t}
                            href={t === 'Products' ? '/products' : '/orders'}
                            className={`rounded-full px-3 py-1 font-medium ${i === 2 || t === 'All' ? 'bg-accent-subtle text-accent' : 'text-secondary hover:bg-surface-hover'}`}
                        >
                            {t === 'All' ? 'Orders' : t}
                        </Link>
                    ))}
                </div>
                <Table
                    columns={columns}
                    rows={orders}
                    onRowClick={() => router.visit('/orders')}
                    empty={<FirstUseEmpty title="No orders yet" description="Orders created from chat or synced from your store appear here." />}
                />
            </Page>
        </AppShell>
    );
}
