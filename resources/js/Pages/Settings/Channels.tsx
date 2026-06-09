import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Copy, LogIn, Check } from 'lucide-react';
import { SettingsLayout } from '@/components/settings/SettingsLayout';
import { Card } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Drawer } from '@/components/ui/Drawer';
import { Tabs } from '@/components/ui/Tabs';
import { Input } from '@/components/ui/Input';
import { ChannelDot } from '@/components/ui/Avatar';
import { useToast } from '@/components/ui/Toast';
import { launchEmbeddedSignup } from '@/lib/metaSdk';
import type { Channel as Ch } from '@/types';

interface Row {
    type: Ch;
    name: string | null;
    external_id: string | null;
    status: string;
    onboardable: boolean;
    webhook_url: string | null;
    verify_token: string | null;
}

interface Embedded {
    configured: boolean;
    app_id: string | null;
    graph_version: string;
    config_id: Record<string, string | null>;
}

const labels: Record<string, string> = {
    whatsapp: 'WhatsApp', instagram: 'Instagram', messenger: 'Messenger',
    telegram: 'Telegram', line: 'Line', viber: 'Viber', web: 'Web widget',
};

export default function Channels({ channels, embedded }: { channels: Row[]; embedded: Embedded }) {
    const { toast } = useToast();
    const [active, setActive] = useState<Row | null>(null);

    return (
        <SettingsLayout title="Channels">
            <Head title="Channels" />
            <p className="mb-4 text-[13px] text-secondary">
                Connect a channel with one click via Facebook, or paste credentials manually — your choice.
            </p>
            <div className="grid gap-3 sm:grid-cols-2">
                {channels.map((c) => {
                    const connected = c.status === 'connected';
                    const action = c.status === 'action_needed';
                    return (
                        <Card key={c.type} className="p-4">
                            <div className="flex items-center gap-2.5">
                                <ChannelDot channel={c.type} className="size-3" />
                                <span className="text-sm font-medium">{labels[c.type]}</span>
                                {connected && <Badge tone="success" className="ms-auto">Connected</Badge>}
                                {action && <Badge tone="warning" className="ms-auto">Action needed</Badge>}
                                {!connected && !action && <Badge tone="neutral" className="ms-auto">Not connected</Badge>}
                            </div>
                            <p className="mt-2 text-[12px] text-tertiary">
                                {connected
                                    ? [c.name, c.external_id].filter(Boolean).join(' · ')
                                    : c.onboardable
                                      ? 'Connect to start receiving messages.'
                                      : 'Manual setup.'}
                            </p>
                            {c.onboardable ? (
                                <Button
                                    size="sm"
                                    variant={connected ? 'secondary' : 'primary'}
                                    className="mt-3 w-full"
                                    onClick={() => setActive(c)}
                                >
                                    {connected ? 'Manage' : action ? 'Reconnect' : 'Connect'}
                                </Button>
                            ) : (
                                <Button size="sm" variant="secondary" className="mt-3 w-full" disabled>
                                    {connected ? 'Manage' : 'Connect'}
                                </Button>
                            )}
                        </Card>
                    );
                })}
                <Card className="p-4 opacity-60">
                    <div className="flex items-center gap-2.5">
                        <span className="size-3 rounded-full bg-strong" />
                        <span className="text-sm font-medium">Email</span>
                        <Badge tone="neutral" className="ms-auto">Coming soon</Badge>
                    </div>
                    <p className="mt-2 text-[12px] text-tertiary">An email channel is on the roadmap.</p>
                </Card>
            </div>

            {active && (
                <ConnectDrawer channel={active} embedded={embedded} onClose={() => setActive(null)} toast={toast} />
            )}
        </SettingsLayout>
    );
}

function ConnectDrawer({
    channel,
    embedded,
    onClose,
    toast,
}: {
    channel: Row;
    embedded: Embedded;
    onClose: () => void;
    toast: (m: string, o?: { tone?: 'success' | 'error' | 'info' }) => void;
}) {
    const connected = channel.status === 'connected';
    const configId = embedded.config_id[channel.type];
    const quickAvailable = embedded.configured && !!configId && !!embedded.app_id;
    const [tab, setTab] = useState(quickAvailable ? 'quick' : 'manual');
    const [busy, setBusy] = useState(false);
    const [form, setForm] = useState<Record<string, string>>({});
    const [errors, setErrors] = useState<Record<string, string>>({});

    const copy = (v: string, label: string) => {
        navigator.clipboard?.writeText(v);
        toast(`${label} copied`, { tone: 'success' });
    };

    const quickConnect = async () => {
        setBusy(true);
        try {
            const result = await launchEmbeddedSignup(embedded.app_id!, embedded.graph_version, configId!);
            if (!result) {
                setBusy(false);
                return;
            }
            const payload: Record<string, string> = {};
            for (const [k, v] of Object.entries(result)) {
                if (v) payload[k] = v;
            }
            router.post(`/settings/channels/${channel.type}/embedded`, payload, {
                onSuccess: () => { toast(`${labels[channel.type]} connected`, { tone: 'success' }); onClose(); },
                onError: (e) => toast(Object.values(e)[0] ?? 'Connection failed', { tone: 'error' }),
                onFinish: () => setBusy(false),
            });
        } catch {
            toast('Facebook sign-in was cancelled', { tone: 'info' });
            setBusy(false);
        }
    };

    const manualConnect = () => {
        setBusy(true);
        router.post(`/settings/channels/${channel.type}/connect`, form, {
            onSuccess: () => { toast(`${labels[channel.type]} connected`, { tone: 'success' }); onClose(); },
            onError: (e) => setErrors(e),
            onFinish: () => setBusy(false),
        });
    };

    const disconnect = () => {
        router.delete(`/settings/channels/${channel.type}`, {
            onSuccess: () => { toast(`${labels[channel.type]} disconnected`, { tone: 'success' }); onClose(); },
        });
    };

    return (
        <Drawer
            open
            onClose={onClose}
            title={`${connected ? 'Manage' : 'Connect'} ${labels[channel.type]}`}
            footer={
                connected ? (
                    <Button variant="danger" onClick={disconnect}>Disconnect</Button>
                ) : tab === 'manual' ? (
                    <Button loading={busy} onClick={manualConnect}>Test &amp; connect</Button>
                ) : undefined
            }
        >
            {connected && (
                <div className="mb-4 flex items-center gap-2 rounded-[var(--radius-control)] border border-success/30 bg-success-subtle px-3 py-2 text-[13px] text-success">
                    <Check className="size-4" /> Connected as {channel.name} ({channel.external_id})
                </div>
            )}

            <Tabs
                tabs={[{ id: 'quick', label: 'Quick connect' }, { id: 'manual', label: 'Manual' }]}
                active={tab}
                onChange={setTab}
            />

            <div className="mt-4">
                {tab === 'quick' ? (
                    quickAvailable ? (
                        <div className="space-y-3">
                            <p className="text-[13px] text-secondary">
                                Sign in with Facebook to authorize {labels[channel.type]} — no copying tokens.
                            </p>
                            <Button loading={busy} onClick={quickConnect} className="w-full bg-[#1877F2] hover:brightness-110">
                                <LogIn className="size-4" /> Connect with Facebook
                            </Button>
                        </div>
                    ) : (
                        <div className="rounded-[var(--radius-control)] bg-warning-subtle px-3 py-2.5 text-[13px] text-warning">
                            Embedded Signup isn't configured yet. Set <code className="text-[12px]">META_APP_ID</code> and the
                            channel's config id, or use the <strong>Manual</strong> tab.
                        </div>
                    )
                ) : (
                    <div className="space-y-4">
                        {channel.type === 'whatsapp' ? (
                            <>
                                <Input label="Permanent access token" error={errors.access_token} value={form.access_token ?? ''} onChange={(e) => setForm({ ...form, access_token: e.target.value })} />
                                <Input label="Phone number ID" error={errors.phone_number_id} value={form.phone_number_id ?? ''} onChange={(e) => setForm({ ...form, phone_number_id: e.target.value })} />
                                <Input label="WABA ID (optional)" value={form.waba_id ?? ''} onChange={(e) => setForm({ ...form, waba_id: e.target.value })} />
                            </>
                        ) : (
                            <Input label="Page access token" error={errors.page_token} value={form.page_token ?? ''} onChange={(e) => setForm({ ...form, page_token: e.target.value })} hint={`For the ${labels[channel.type]} page/account.`} />
                        )}
                        {errors.connection && <p className="text-[12px] text-danger">{errors.connection}</p>}

                        <div className="rounded-[var(--radius-card)] border border-default bg-canvas p-3">
                            <p className="mb-2 text-[12px] font-semibold uppercase tracking-wide text-tertiary">Webhook setup (paste into Meta)</p>
                            <Field label="Callback URL" value={channel.webhook_url ?? ''} onCopy={() => copy(channel.webhook_url ?? '', 'Callback URL')} />
                            <Field label="Verify token" value={channel.verify_token ?? '(set in .env)'} onCopy={() => copy(channel.verify_token ?? '', 'Verify token')} />
                        </div>
                    </div>
                )}
            </div>
        </Drawer>
    );
}

function Field({ label, value, onCopy }: { label: string; value: string; onCopy: () => void }) {
    return (
        <div className="mt-2 first:mt-0">
            <p className="text-[11px] text-tertiary">{label}</p>
            <div className="flex items-center gap-2 rounded-[var(--radius-control)] bg-surface-2 px-2.5 py-1.5">
                <code className="flex-1 truncate text-[12px] text-secondary">{value}</code>
                <button onClick={onCopy} className="text-tertiary hover:text-primary"><Copy className="size-3.5" /></button>
            </div>
        </div>
    );
}
