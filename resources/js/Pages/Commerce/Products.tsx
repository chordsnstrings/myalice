import { Head, Link } from '@inertiajs/react';
import { Send, AlertTriangle } from 'lucide-react';
import { AppShell } from '@/components/shell/AppShell';
import { Page } from '@/components/shell/Page';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { money } from '@/lib/utils';

interface Product {
    id: number;
    title: string;
    price: number;
    currency: string;
    stock: number;
    source: string;
}

export default function Products({ products, store }: { products: Product[]; store: { platform: string; last_synced_at: string | null } | null }) {
    return (
        <AppShell title="Commerce">
            <Head title="Products" />
            <Page title="Product catalog" description={store ? `Synced from ${store.platform} · updated ${store.last_synced_at}` : undefined}>
                <div className="mb-3 flex gap-1.5 text-[13px]">
                    <Link href="/orders" className="rounded-full px-3 py-1 font-medium text-secondary hover:bg-surface-hover">Orders</Link>
                    <Link href="/products" className="rounded-full bg-accent-subtle px-3 py-1 font-medium text-accent">Products</Link>
                </div>
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
                            <Button variant="secondary" size="sm" className="mt-3 w-full" disabled={p.stock === 0}>
                                <Send className="size-3.5" /> Send to chat
                            </Button>
                        </Card>
                    ))}
                </div>
            </Page>
        </AppShell>
    );
}
