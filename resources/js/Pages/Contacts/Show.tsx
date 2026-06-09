import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, MessageSquare, Trash2 } from 'lucide-react';
import { AppShell } from '@/components/shell/AppShell';
import { Avatar } from '@/components/ui/Avatar';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Tabs } from '@/components/ui/Tabs';
import { ConfirmModal } from '@/components/ui/Modal';
import { money } from '@/lib/utils';
import type { Channel } from '@/types';

interface Props {
    contact: {
        id: number;
        name: string;
        email: string | null;
        phone: string | null;
        channel: Channel;
        lifecycle: string;
        tags: string[];
    };
    orders: { id: number; number: string; total: number; currency: string; status: string }[];
}

export default function ContactShow({ contact, orders }: Props) {
    const [tab, setTab] = useState('overview');
    const [confirm, setConfirm] = useState(false);

    return (
        <AppShell title="Contact">
            <Head title={contact.name} />
            <div className="h-full overflow-y-auto">
                <div className="mx-auto max-w-3xl px-6 py-6">
                    <Link href="/contacts" className="mb-4 inline-flex items-center gap-1.5 text-[13px] text-secondary hover:text-primary">
                        <ArrowLeft className="size-4" /> Contacts
                    </Link>

                    <div className="flex items-start gap-4">
                        <Avatar name={contact.name} size="lg" channel={contact.channel} />
                        <div className="flex-1">
                            <h2 className="text-xl font-semibold tracking-tight">{contact.name}</h2>
                            <p className="text-[13px] text-secondary">{contact.email} · {contact.phone}</p>
                            <div className="mt-2 flex flex-wrap gap-1.5">
                                <Badge tone="accent">{contact.lifecycle}</Badge>
                                {contact.tags.map((t) => (
                                    <Badge key={t} tone="neutral">{t}</Badge>
                                ))}
                            </div>
                        </div>
                        <div className="flex gap-2">
                            <Button variant="secondary" onClick={() => router.visit('/inbox')}>
                                <MessageSquare className="size-4" /> Message
                            </Button>
                            <Button variant="ghost" onClick={() => setConfirm(true)} aria-label="Delete contact">
                                <Trash2 className="size-4 text-danger" />
                            </Button>
                        </div>
                    </div>

                    <div className="mt-6">
                        <Tabs
                            tabs={[
                                { id: 'overview', label: 'Overview' },
                                { id: 'orders', label: 'Orders', count: orders.length },
                                { id: 'activity', label: 'Activity' },
                                { id: 'notes', label: 'Notes' },
                            ]}
                            active={tab}
                            onChange={setTab}
                        />

                        <div className="mt-4">
                            {tab === 'overview' && (
                                <Card className="divide-y divide-[var(--border)]">
                                    {[
                                        ['Channel', contact.channel],
                                        ['Lifecycle stage', contact.lifecycle],
                                        ['Email', contact.email ?? '—'],
                                        ['Phone', contact.phone ?? '—'],
                                        ['Opt-in status', 'Subscribed'],
                                    ].map(([k, v]) => (
                                        <div key={k} className="flex justify-between px-5 py-3 text-sm">
                                            <span className="text-secondary">{k}</span>
                                            <span className="font-medium capitalize">{v}</span>
                                        </div>
                                    ))}
                                </Card>
                            )}
                            {tab === 'orders' && (
                                <Card className="divide-y divide-[var(--border)]">
                                    {orders.length === 0 ? (
                                        <p className="px-5 py-8 text-center text-sm text-tertiary">No orders linked</p>
                                    ) : (
                                        orders.map((o) => (
                                            <div key={o.id} className="flex items-center justify-between px-5 py-3 text-sm">
                                                <span className="font-medium tnum">{o.number}</span>
                                                <span className="tnum text-secondary">{money(o.total, o.currency)}</span>
                                                <Badge tone="success">{o.status}</Badge>
                                            </div>
                                        ))
                                    )}
                                </Card>
                            )}
                            {tab === 'activity' && (
                                <Card className="p-5 text-sm text-secondary">A timeline of messages, orders and campaign touches appears here.</Card>
                            )}
                            {tab === 'notes' && (
                                <textarea
                                    placeholder="Internal notes…"
                                    rows={5}
                                    className="w-full resize-none rounded-[var(--radius-card)] border border-default bg-surface p-3 text-sm outline-none focus:border-accent"
                                />
                            )}
                        </div>
                    </div>
                </div>
            </div>

            <ConfirmModal
                open={confirm}
                onClose={() => setConfirm(false)}
                onConfirm={() => router.visit('/contacts')}
                title="Delete contact"
                consequence={`This permanently deletes ${contact.name} and their conversation history. This can't be undone.`}
                confirmLabel="Delete contact"
            />
        </AppShell>
    );
}
