import { useMemo, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Search, Upload, Plus, Tag as TagIcon, Send } from 'lucide-react';
import { useToast } from '@/components/ui/Toast';
import { AppShell } from '@/components/shell/AppShell';
import { Page } from '@/components/shell/Page';
import { Table, type Column } from '@/components/ui/Table';
import { Avatar } from '@/components/ui/Avatar';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { FilteredEmpty } from '@/components/ui/States';
import type { Channel } from '@/types';

interface Row {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    channel: Channel;
    lifecycle: string;
    tags: string[];
    orders: number;
}

export default function ContactsIndex({ contacts }: { contacts: Row[] }) {
    const { toast } = useToast();
    const [query, setQuery] = useState('');
    const [selected, setSelected] = useState<Set<number>>(new Set());
    const fileRef = useRef<HTMLInputElement>(null);

    const onImport = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        router.post('/contacts/import', { file }, {
            forceFormData: true,
            onSuccess: () => toast('Import processed', { tone: 'success' }),
            onError: () => toast('Import failed — check the file format', { tone: 'error' }),
        });
        e.target.value = '';
    };

    const filtered = useMemo(
        () => contacts.filter((c) => c.name.toLowerCase().includes(query.toLowerCase())),
        [contacts, query],
    );

    const toggle = (id: number) =>
        setSelected((s) => {
            const next = new Set(s);
            next.has(id) ? next.delete(id) : next.add(id);
            return next;
        });

    const columns: Column<Row>[] = [
        {
            key: 'sel',
            header: '',
            render: (r) => (
                <input
                    type="checkbox"
                    checked={selected.has(r.id)}
                    onChange={(e) => {
                        e.stopPropagation();
                        toggle(r.id);
                    }}
                    onClick={(e) => e.stopPropagation()}
                    className="size-4 rounded border-strong accent-[var(--accent)]"
                />
            ),
        },
        {
            key: 'name',
            header: 'Name',
            render: (r) => (
                <div className="flex items-center gap-2.5">
                    <Avatar name={r.name} size="sm" channel={r.channel} />
                    <div>
                        <p className="font-medium text-primary">{r.name}</p>
                        <p className="text-[12px] text-tertiary">{r.email}</p>
                    </div>
                </div>
            ),
        },
        { key: 'lifecycle', header: 'Lifecycle', render: (r) => <Badge tone="accent">{r.lifecycle}</Badge> },
        {
            key: 'tags',
            header: 'Tags',
            render: (r) => (
                <div className="flex flex-wrap gap-1">
                    {r.tags.slice(0, 2).map((t) => (
                        <Badge key={t} tone="neutral">{t}</Badge>
                    ))}
                </div>
            ),
        },
        { key: 'orders', header: 'Orders', align: 'end', render: (r) => r.orders },
        { key: 'phone', header: 'Phone', align: 'end', render: (r) => <span className="text-secondary">{r.phone}</span> },
    ];

    return (
        <AppShell title="Contacts">
            <Head title="Contacts" />
            <Page
                title="Contacts"
                description={`${contacts.length} customers`}
                actions={
                    <>
                        <input ref={fileRef} type="file" accept=".csv,text/csv" className="hidden" onChange={onImport} />
                        <Button variant="secondary" onClick={() => fileRef.current?.click()}>
                            <Upload className="size-4" /> Import
                        </Button>
                        <Button><Plus className="size-4" /> Add contact</Button>
                    </>
                }
            >
                <div className="mb-3 flex items-center gap-2 rounded-[var(--radius-control)] border border-default bg-surface px-3 sm:w-80">
                    <Search className="size-4 text-tertiary" />
                    <input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search contacts"
                        className="h-9 flex-1 bg-transparent text-sm outline-none placeholder:text-tertiary"
                    />
                </div>

                {selected.size > 0 && (
                    <div className="animate-in mb-3 flex items-center gap-3 rounded-[var(--radius-control)] border border-default bg-surface px-4 py-2.5">
                        <span className="text-[13px] font-medium">{selected.size} selected</span>
                        <div className="ms-auto flex gap-1.5">
                            <Button size="sm" variant="ghost"><TagIcon className="size-3.5" /> Tag</Button>
                            <Button size="sm" variant="ghost"><Send className="size-3.5" /> Message</Button>
                            <Button size="sm" variant="ghost" onClick={() => setSelected(new Set())}>Clear</Button>
                        </div>
                    </div>
                )}

                <Table
                    columns={columns}
                    rows={filtered}
                    onRowClick={(r) => router.visit(`/contacts/${r.id}`)}
                    empty={<FilteredEmpty onClear={() => setQuery('')} />}
                />
            </Page>
        </AppShell>
    );
}
