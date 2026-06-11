import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, Plus } from 'lucide-react';
import { cn, initials } from '@/lib/utils';
import { Modal } from '@/components/ui/Modal';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import type { PageProps } from '@/types';

/** Brand mark + active-workspace name; opens a switcher of all memberships. */
export function WorkspaceSwitcher() {
    const { props } = usePage<PageProps>();
    const current = props.auth.workspace;
    const workspaces = props.auth.workspaces ?? [];
    const [open, setOpen] = useState(false);
    const [creating, setCreating] = useState(false);
    const [name, setName] = useState('');
    const [saving, setSaving] = useState(false);

    const switchTo = (id: number) => {
        if (id === current?.id) return setOpen(false);
        router.post(`/workspaces/${id}/switch`, {}, { onFinish: () => setOpen(false) });
    };

    const create = () => {
        setSaving(true);
        router.post(
            '/workspaces',
            { name },
            { onFinish: () => setSaving(false), onSuccess: () => { setCreating(false); setName(''); } },
        );
    };

    return (
        <div className="relative">
            <button
                onClick={() => setOpen((o) => !o)}
                className="press group flex w-full items-center gap-2.5 rounded-[var(--radius-control)] px-1.5 py-1 text-start hover:bg-surface-hover"
            >
                <span className="brand-gradient glow-accent flex size-7 shrink-0 items-center justify-center rounded-[9px] text-[13px] font-bold text-accent-contrast">
                    {current ? initials(current.name) : 'A'}
                </span>
                <span className="min-w-0 flex-1 leading-tight">
                    <span className="block truncate text-[13px] font-semibold text-primary">{current?.name ?? 'ARKS'}</span>
                    <span className="block truncate text-[10px] font-medium uppercase tracking-wide text-tertiary">
                        {current?.plan ?? 'Workspace'}
                    </span>
                </span>
                <ChevronsUpDown className="size-3.5 shrink-0 text-tertiary" />
            </button>

            {open && (
                <>
                    <div className="fixed inset-0 z-40" onClick={() => setOpen(false)} />
                    <div className="animate-pop absolute inset-x-0 top-full z-50 mt-1.5 rounded-[var(--radius-card)] border border-default bg-surface p-1.5 shadow-[var(--shadow-md)]">
                        <p className="px-2.5 pb-1 pt-1 text-[11px] font-semibold uppercase tracking-wide text-tertiary">
                            Workspaces
                        </p>
                        <div className="max-h-64 overflow-y-auto">
                            {workspaces.map((w) => {
                                const active = w.id === current?.id;
                                return (
                                    <button
                                        key={w.id}
                                        onClick={() => switchTo(w.id)}
                                        className={cn(
                                            'press flex w-full items-center gap-2.5 rounded-[var(--radius-control)] px-2 py-1.5 text-start text-[13px] transition-colors',
                                            active ? 'bg-accent-subtle text-accent' : 'text-secondary hover:bg-surface-hover hover:text-primary',
                                        )}
                                    >
                                        <span className={cn(
                                            'flex size-6 shrink-0 items-center justify-center rounded-md text-[11px] font-semibold',
                                            active ? 'brand-gradient text-accent-contrast' : 'bg-surface-2 text-secondary',
                                        )}>
                                            {initials(w.name)}
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate font-medium">{w.name}</span>
                                            <span className="block truncate text-[11px] capitalize text-tertiary">{w.role}</span>
                                        </span>
                                        {active && <Check className="size-4 shrink-0" />}
                                    </button>
                                );
                            })}
                        </div>
                        <div className="my-1 h-px bg-default" />
                        <button
                            onClick={() => { setOpen(false); setCreating(true); }}
                            className="press flex w-full items-center gap-2.5 rounded-[var(--radius-control)] px-2 py-1.5 text-start text-[13px] font-medium text-accent hover:bg-surface-hover"
                        >
                            <span className="flex size-6 items-center justify-center rounded-md bg-accent-subtle">
                                <Plus className="size-3.5" />
                            </span>
                            Create workspace
                        </button>
                    </div>
                </>
            )}

            <Modal
                open={creating}
                onClose={() => setCreating(false)}
                title="Create a workspace"
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setCreating(false)}>Cancel</Button>
                        <Button onClick={create} loading={saving} disabled={!name.trim()}>Create workspace</Button>
                    </>
                }
            >
                <p className="mb-3 text-[13px] text-secondary">
                    A fresh, isolated workspace — its own channels, contacts, team, billing and analytics. You'll be its owner.
                </p>
                <Input
                    label="Workspace name"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    placeholder="e.g. Northwind Coffee"
                    onKeyDown={(e) => e.key === 'Enter' && name.trim() && create()}
                    autoFocus
                />
            </Modal>
        </div>
    );
}
