import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { UserPlus, Trash2 } from 'lucide-react';
import { SettingsLayout } from '@/components/settings/SettingsLayout';
import { Card } from '@/components/ui/Card';
import { Avatar } from '@/components/ui/Avatar';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Input } from '@/components/ui/Input';
import { ConfirmModal } from '@/components/ui/Modal';
import type { PageProps } from '@/types';

interface Member {
    id: number;
    name: string;
    email: string;
    role: string;
    status: string;
}
interface Props {
    members: Member[];
    subscription: { seats: number };
}

const roles = ['owner', 'manager', 'agent', 'developer'] as const;

export default function Team({ members, subscription }: Props) {
    const { props } = usePage<PageProps>();
    const me = props.auth.user;
    const [inviting, setInviting] = useState(false);
    const [email, setEmail] = useState('');
    const [role, setRole] = useState<(typeof roles)[number]>('agent');
    const [saving, setSaving] = useState(false);
    const [removing, setRemoving] = useState<Member | null>(null);

    const invite = () => {
        setSaving(true);
        router.post(
            '/settings/team',
            { email, role },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
                onSuccess: () => { setInviting(false); setEmail(''); setRole('agent'); },
            },
        );
    };

    const remove = (m: Member) =>
        router.delete(`/settings/team/${m.id}`, { preserveScroll: true });

    return (
        <SettingsLayout title="Team & roles">
            <Head title="Team" />
            <div className="mb-4 flex items-center justify-between">
                <p className="text-[13px] text-secondary">
                    <span className="font-medium text-primary">{members.length}</span> of {subscription.seats} seats used
                </p>
                <Button size="sm" onClick={() => setInviting(true)}>
                    <UserPlus className="size-4" /> Invite member
                </Button>
            </div>
            <Card className="divide-y divide-[var(--border)]">
                {members.map((m) => (
                    <div key={m.id} className="group flex items-center gap-3 px-4 py-3">
                        <Avatar name={m.name} size="sm" />
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-medium">
                                {m.name}
                                {m.id === me?.id && <span className="ms-1.5 text-[12px] font-normal text-tertiary">(you)</span>}
                            </p>
                            <p className="text-[12px] text-tertiary">{m.email}</p>
                        </div>
                        <Badge tone={m.role === 'owner' ? 'accent' : 'neutral'}>{m.role}</Badge>
                        <Badge tone="success">{m.status}</Badge>
                        {m.id !== me?.id && (
                            <button
                                onClick={() => setRemoving(m)}
                                aria-label={`Remove ${m.name}`}
                                className="press flex size-8 items-center justify-center rounded-[var(--radius-control)] text-tertiary opacity-0 transition-opacity hover:bg-danger-subtle hover:text-danger group-hover:opacity-100"
                            >
                                <Trash2 className="size-4" />
                            </button>
                        )}
                    </div>
                ))}
            </Card>

            <Modal
                open={inviting}
                onClose={() => setInviting(false)}
                title="Invite a teammate"
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setInviting(false)}>Cancel</Button>
                        <Button onClick={invite} loading={saving} disabled={!email.trim()}>Send invite</Button>
                    </>
                }
            >
                <p className="mb-3 text-[13px] text-secondary">
                    They'll be added to this workspace. Existing users can switch into it from the workspace menu.
                </p>
                <div className="space-y-3">
                    <Input
                        label="Email"
                        type="email"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        placeholder="teammate@company.com"
                        autoFocus
                    />
                    <div className="space-y-1.5">
                        <label className="block text-[13px] font-medium text-primary">Role</label>
                        <select
                            value={role}
                            onChange={(e) => setRole(e.target.value as (typeof roles)[number])}
                            className="h-9 w-full cursor-pointer rounded-[var(--radius-control)] border border-strong bg-surface px-3 text-sm capitalize text-primary outline-none transition-colors hover:bg-surface-hover focus:border-accent"
                        >
                            {roles.map((r) => (
                                <option key={r} value={r} className="capitalize">{r}</option>
                            ))}
                        </select>
                    </div>
                </div>
            </Modal>

            <ConfirmModal
                open={!!removing}
                onClose={() => setRemoving(null)}
                onConfirm={() => removing && remove(removing)}
                title={`Remove ${removing?.name ?? 'member'}?`}
                consequence="They'll lose access to this workspace. Their data and history stay intact, and you can re-invite them anytime."
                confirmLabel="Remove"
            />
        </SettingsLayout>
    );
}
