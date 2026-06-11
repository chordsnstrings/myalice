import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Send, AlertTriangle, RefreshCw } from 'lucide-react';
import { AppShell } from '@/components/shell/AppShell';
import { Page } from '@/components/shell/Page';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { useToast } from '@/components/ui/Toast';
import { money } from '@/lib/utils';
import type { PageProps } from '@/types';

interface Product {
    id: number;
    title: string;
    price: number;
    currency: string;
    stock: number;
    source: string;
    type: string;
}

interface Props {
    products: Product[];
    store: { platform: string; store_url?: string; last_synced_at: string | null } | null;
    shopify_connected: boolean;
}

export default function Products({ products, store, shopify_connected }: Props) {
    const { toast } = useToast();
    const canManage = !!usePage<PageProps>().props.auth.can?.manage_bots;

    const setType = (id: number, type: string) =>
        router.patch(`/products/${id}/type`, { type }, { preserveScroll: true, preserveState: false });

    return (
        <AppShell title="Commerce">
            <Head title="Products" />
            <Page title="Product catalog" description={store ? `Synced from ${store.platform} · updated ${store.last_synced_at}` : undefined}>
                <div className="mb-3 flex gap-1.5 text-[13px]">
                    <Link href="/orders" className="rounded-full px-3 py-1 font-medium text-secondary hover:bg-surface-hover">Orders</Link>
                    <Link href="/products" className="rounded-full bg-accent-subtle px-3 py-1 font-medium text-accent">Products</Link>
                </div>

                {canManage && <ShopifyCard connected={shopify_connected} store={store} toast={toast} />}
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-3">
                    {products.map((p) => (
                        <Card key={p.id} className="p-4">
                            <div className="mb-3 flex aspect-[4/3] items-center justify-center rounded-[var(--radius-control)] bg-surface-2 text-tertiary">
                                <span className="text-[12px]">No image</span>
                            </div>
                            <p className="text-sm font-medium text-primary">{p.title}</p>
                            <div className="mt-1 flex items-center justify-between">
                                <span className="text-sm font-semibold tnum">{money(p.price, p.currency)}</span>
                                {p.stock === 0 ? (
                                    <Badge tone="danger"><AlertTriangle className="size-3" /> Out of stock</Badge>
                                ) : (
                                    <span className="text-[12px] text-tertiary tnum">{p.stock} in stock</span>
                                )}
                            </div>
                            {canManage && (
                                <select
                                    className="mt-3 h-8 w-full rounded-[var(--radius-control)] border border-strong bg-surface px-2 text-[12px] text-secondary focus:border-accent focus-visible:outline-none"
                                    value={p.type}
                                    onChange={(e) => setType(p.id, e.target.value)}
                                    title="Used by the AI for the pre-approved service discount"
                                >
                                    <option value="product">Physical product</option>
                                    <option value="service">Service</option>
                                </select>
                            )}
                            <Button variant="secondary" size="sm" className="mt-2 w-full" disabled={p.stock === 0}>
                                <Send className="size-3.5" /> Send to chat
                            </Button>
                        </Card>
                    ))}
                </div>
            </Page>
        </AppShell>
    );
}

function ShopifyCard({
    connected,
    store,
    toast,
}: {
    connected: boolean;
    store: { store_url?: string; last_synced_at: string | null } | null;
    toast: ReturnType<typeof useToast>['toast'];
}) {
    const [open, setOpen] = useState(false);
    const [storeUrl, setStoreUrl] = useState('');
    const [token, setToken] = useState('');
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const connect = () => {
        setBusy(true);
        setErrors({});
        router.post('/store/connect', { store_url: storeUrl, access_token: token }, {
            preserveScroll: true,
            onSuccess: () => { toast('Shopify connected', { tone: 'success' }); setOpen(false); setToken(''); },
            onError: (e) => setErrors(e),
            onFinish: () => setBusy(false),
        });
    };
    const sync = () => router.post('/store/sync', {}, { preserveScroll: true, onSuccess: () => toast('Catalog synced', { tone: 'success' }) });

    if (connected) {
        return (
            <Card className="mb-4 flex items-center gap-3 p-4">
                <Badge tone="success">Shopify connected</Badge>
                <span className="text-[13px] text-secondary">{store?.store_url} · updated {store?.last_synced_at ?? 'never'}</span>
                <Button size="sm" variant="secondary" className="ms-auto" onClick={sync}><RefreshCw className="size-4" /> Sync now</Button>
            </Card>
        );
    }

    return (
        <Card className="mb-4 p-4">
            <div className="flex items-center gap-3">
                <Badge tone="neutral">Shopify not connected</Badge>
                <span className="text-[13px] text-secondary">Sync your catalog and let the AI place real orders.</span>
                <Button size="sm" className="ms-auto" onClick={() => setOpen((o) => !o)}>{open ? 'Cancel' : 'Connect Shopify'}</Button>
            </div>
            {open && (
                <div className="mt-3 grid gap-3 sm:grid-cols-2">
                    <Input label="Store domain" placeholder="acme.myshopify.com" error={errors.store_url} value={storeUrl} onChange={(e) => setStoreUrl(e.target.value)} />
                    <Input label="Admin API access token" type="password" error={errors.access_token} value={token} onChange={(e) => setToken(e.target.value)} />
                    <div className="sm:col-span-2 flex justify-end">
                        <Button loading={busy} disabled={!storeUrl || !token} onClick={connect}>Test &amp; connect</Button>
                    </div>
                </div>
            )}
        </Card>
    );
}
