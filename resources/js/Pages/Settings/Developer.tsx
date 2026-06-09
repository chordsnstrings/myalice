import { Head } from '@inertiajs/react';
import { Plus, Copy, KeyRound, Webhook } from 'lucide-react';
import { SettingsLayout } from '@/components/settings/SettingsLayout';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { useToast } from '@/components/ui/Toast';

export default function Developer() {
    const { toast } = useToast();
    return (
        <SettingsLayout title="Developer">
            <Head title="Developer" />

            <div className="mb-3 flex items-center justify-between">
                <h3 className="flex items-center gap-2 text-sm font-semibold"><KeyRound className="size-4 text-accent" /> API keys</h3>
                <Button size="sm"><Plus className="size-4" /> Create key</Button>
            </div>
            <Card className="divide-y divide-[var(--border)]">
                {[
                    { name: 'Production', scopes: 'read, write', last: '2h ago' },
                    { name: 'Zapier', scopes: 'read', last: '3d ago' },
                ].map((k) => (
                    <div key={k.name} className="flex items-center gap-3 px-4 py-3">
                        <div className="flex-1">
                            <p className="text-sm font-medium">{k.name}</p>
                            <p className="text-[12px] text-tertiary">Scopes: {k.scopes} · used {k.last}</p>
                        </div>
                        <code className="rounded bg-surface-2 px-2 py-1 text-[12px] text-secondary">sk_live_••••7f2a</code>
                        <button onClick={() => toast('Copied', { tone: 'success' })} className="text-tertiary hover:text-primary"><Copy className="size-4" /></button>
                    </div>
                ))}
            </Card>

            <div className="mb-3 mt-6 flex items-center justify-between">
                <h3 className="flex items-center gap-2 text-sm font-semibold"><Webhook className="size-4 text-accent" /> Webhooks</h3>
                <Button size="sm"><Plus className="size-4" /> Add endpoint</Button>
            </div>
            <Card className="divide-y divide-[var(--border)]">
                <div className="flex items-center gap-3 px-4 py-3">
                    <div className="flex-1">
                        <p className="text-sm font-medium">https://hooks.acme.com/arks</p>
                        <p className="text-[12px] text-tertiary">message.created, order.created</p>
                    </div>
                    <Badge tone="success">Active</Badge>
                    <Button size="sm" variant="ghost" onClick={() => toast('Ping sent', { tone: 'info' })}>Test</Button>
                </div>
            </Card>
            <p className="mt-3 text-[12px] text-tertiary">Deliveries are signed and retried with backoff; endpoints auto-disable after repeated failures.</p>
        </SettingsLayout>
    );
}
