import { useEffect, useMemo, useRef, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import {
    Search,
    Smile,
    Paperclip,
    Zap,
    ShoppingBag,
    Send,
    MoreHorizontal,
    Check,
    CheckCheck,
    Clock,
    AlertTriangle,
    Tag,
    Plus,
    PanelRightClose,
    Bot,
    FileText,
    Lock,
} from 'lucide-react';
import { AppShell } from '@/components/shell/AppShell';
import { Avatar, ChannelDot } from '@/components/ui/Avatar';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Tooltip } from '@/components/ui/Tooltip';
import { FilteredEmpty } from '@/components/ui/States';
import { useToast } from '@/components/ui/Toast';
import { cn, relativeTime, money } from '@/lib/utils';
import type { Conversation, Message, PageProps } from '@/types';

interface Props {
    conversations: Conversation[];
    messages: Record<number, Message[]>;
}

type ComposerState = 'free' | 'template' | 'bot' | 'resolved';

const filters = ['All', 'Mine', 'Unassigned', 'Bots', 'Closed'] as const;

function StatusTick({ status }: { status?: Message['status'] }) {
    if (status === 'read') return <CheckCheck className="size-3.5 text-accent" />;
    if (status === 'delivered') return <CheckCheck className="size-3.5 text-tertiary" />;
    if (status === 'sent') return <Check className="size-3.5 text-tertiary" />;
    if (status === 'sending' || status === 'queued') return <Clock className="size-3 text-tertiary" />;
    if (status === 'failed') return <AlertTriangle className="size-3.5 text-danger" />;
    return null;
}

export default function InboxIndex({ conversations, messages: seed }: Props) {
    const { toast } = useToast();
    const [activeFilter, setActiveFilter] = useState<(typeof filters)[number]>('All');
    const [query, setQuery] = useState('');
    const [selectedId, setSelectedId] = useState<number | null>(conversations[0]?.id ?? null);
    const [threads, setThreads] = useState(seed);
    const [draft, setDraft] = useState('');
    const [contextOpen, setContextOpen] = useState(true);
    const endRef = useRef<HTMLDivElement>(null);
    const workspaceId = usePage<PageProps>().props.auth.workspace?.id;

    // Live inbound messages over the workspace private channel (A10.7). No-op
    // when the realtime broker is unconfigured (graceful degrade).
    useEffect(() => {
        const echo = window.Echo;
        if (!echo || !workspaceId) return;

        const channel = echo.private(`workspace.${workspaceId}`);
        channel.listen('.message.created', (e: { conversation_id: number } & Message) => {
            setThreads((t) => ({
                ...t,
                [e.conversation_id]: [...(t[e.conversation_id] ?? []), e],
            }));
        });

        return () => {
            echo.leave(`workspace.${workspaceId}`);
        };
    }, [workspaceId]);

    const selected = conversations.find((c) => c.id === selectedId) ?? null;
    const messages = selectedId ? (threads[selectedId] ?? []) : [];

    // Composer state machine (B3.4) — window closed ⇒ template required (C-01)
    const composerState: ComposerState = useMemo(() => {
        if (!selected) return 'free';
        if (selected.status === 'resolved') return 'resolved';
        if (!selected.window_open) return 'template';
        return 'free';
    }, [selected]);

    const filtered = useMemo(() => {
        let list = conversations;
        if (activeFilter === 'Mine') list = list.filter((c) => c.assignee?.id === 1);
        if (activeFilter === 'Unassigned') list = list.filter((c) => !c.assignee);
        if (activeFilter === 'Closed') list = list.filter((c) => c.status === 'resolved');
        if (query) list = list.filter((c) => c.contact.name.toLowerCase().includes(query.toLowerCase()));
        return list;
    }, [conversations, activeFilter, query]);

    const send = () => {
        if (!draft.trim() || !selectedId) return;
        const optimistic: Message = {
            id: Date.now(),
            direction: 'out',
            author: 'agent',
            body: draft,
            sent_at: new Date().toISOString(),
            status: 'sending',
        };
        setThreads((t) => ({ ...t, [selectedId]: [...(t[selectedId] ?? []), optimistic] }));
        setDraft('');
        setTimeout(() => endRef.current?.scrollIntoView({ behavior: 'smooth' }), 20);
        // reconcile optimistic → delivered
        setTimeout(() => {
            setThreads((t) => ({
                ...t,
                [selectedId]: (t[selectedId] ?? []).map((m) =>
                    m.id === optimistic.id ? { ...m, status: 'delivered' } : m,
                ),
            }));
        }, 900);
    };

    return (
        <AppShell title="Inbox">
            <Head title="Inbox" />
            <div className="flex h-full">
                {/* LEFT — conversation list (B3.1) */}
                <div className="flex w-[300px] shrink-0 flex-col border-e border-default bg-surface">
                    <div className="border-b border-default p-3">
                        <div className="flex items-center gap-2 rounded-[var(--radius-control)] border border-default bg-canvas px-2.5">
                            <Search className="size-4 text-tertiary" />
                            <input
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Search conversations"
                                className="h-8 flex-1 bg-transparent text-[13px] outline-none placeholder:text-tertiary"
                            />
                        </div>
                        <div className="mt-2.5 flex gap-1 overflow-x-auto">
                            {filters.map((f) => (
                                <button
                                    key={f}
                                    onClick={() => setActiveFilter(f)}
                                    className={cn(
                                        'whitespace-nowrap rounded-full px-2.5 py-1 text-[12px] font-medium transition-colors',
                                        activeFilter === f
                                            ? 'bg-accent-subtle text-accent'
                                            : 'text-secondary hover:bg-surface-hover',
                                    )}
                                >
                                    {f}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="min-h-0 flex-1 overflow-y-auto">
                        {filtered.length === 0 ? (
                            <FilteredEmpty onClear={() => { setActiveFilter('All'); setQuery(''); }} />
                        ) : (
                            filtered.map((c) => (
                                <button
                                    key={c.id}
                                    onClick={() => setSelectedId(c.id)}
                                    className={cn(
                                        'flex w-full items-start gap-3 border-b border-default px-3 py-3 text-start transition-colors',
                                        selectedId === c.id ? 'bg-accent-subtle' : 'hover:bg-surface-hover',
                                    )}
                                >
                                    <Avatar name={c.contact.name} channel={c.channel} />
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="truncate text-[13px] font-medium text-primary">
                                                {c.contact.name}
                                            </span>
                                            <span className="shrink-0 text-[11px] text-tertiary">
                                                {relativeTime(c.last_message_at)}
                                            </span>
                                        </div>
                                        <div className="mt-0.5 flex items-center gap-1.5">
                                            <p className="min-w-0 flex-1 truncate text-[12px] text-secondary">
                                                {c.last_message}
                                            </p>
                                            {c.sla_breaching && (
                                                <span className="size-1.5 shrink-0 rounded-full bg-warning" title="SLA breaching" />
                                            )}
                                            {c.unread > 0 && (
                                                <span className="flex size-4 shrink-0 items-center justify-center rounded-full bg-accent text-[10px] font-semibold text-accent-contrast">
                                                    {c.unread}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </button>
                            ))
                        )}
                    </div>
                </div>

                {/* CENTER — thread + composer (B3.2 / B3.4) */}
                {selected ? (
                    <div className="flex min-w-0 flex-1 flex-col bg-canvas">
                        {/* header */}
                        <div className="flex h-14 shrink-0 items-center gap-3 border-b border-default bg-surface px-4">
                            <Avatar name={selected.contact.name} size="sm" channel={selected.channel} />
                            <div className="min-w-0">
                                <div className="flex items-center gap-2">
                                    <span className="truncate text-sm font-semibold">{selected.contact.name}</span>
                                    <ChannelDot channel={selected.channel} />
                                </div>
                                <div className="flex items-center gap-2 text-[12px] text-tertiary">
                                    {selected.window_open ? (
                                        <span className="flex items-center gap-1 text-success">
                                            <span className="size-1.5 rounded-full bg-success" /> Window open
                                        </span>
                                    ) : (
                                        <span className="flex items-center gap-1 text-warning">
                                            <span className="size-1.5 rounded-full bg-warning" /> 24h window closed
                                        </span>
                                    )}
                                </div>
                            </div>
                            <div className="ms-auto flex items-center gap-1">
                                <Badge tone={selected.status === 'open' ? 'accent' : 'neutral'}>
                                    {selected.status}
                                </Badge>
                                <Tooltip label="Toggle context">
                                    <button
                                        onClick={() => setContextOpen((o) => !o)}
                                        className="flex size-8 items-center justify-center rounded-[var(--radius-control)] text-secondary hover:bg-surface-hover"
                                    >
                                        <PanelRightClose className="size-[18px]" />
                                    </button>
                                </Tooltip>
                                <button className="flex size-8 items-center justify-center rounded-[var(--radius-control)] text-secondary hover:bg-surface-hover">
                                    <MoreHorizontal className="size-[18px]" />
                                </button>
                            </div>
                        </div>

                        {/* thread */}
                        <div className="min-h-0 flex-1 space-y-3 overflow-y-auto px-6 py-5">
                            {messages.map((m) =>
                                m.author === 'system' ? (
                                    <div key={m.id} className="flex justify-center">
                                        <span className="rounded-full bg-surface-2 px-3 py-1 text-[12px] text-tertiary">
                                            {m.body}
                                        </span>
                                    </div>
                                ) : (
                                    <div
                                        key={m.id}
                                        className={cn('flex animate-in', m.direction === 'out' ? 'justify-end' : 'justify-start')}
                                    >
                                        <div
                                            className={cn(
                                                'max-w-[68%] rounded-[var(--radius-card)] px-3.5 py-2 text-[13px] leading-relaxed',
                                                m.direction === 'out'
                                                    ? 'bg-accent text-accent-contrast'
                                                    : 'border border-default bg-surface text-primary',
                                            )}
                                        >
                                            {m.author === 'bot' && (
                                                <span className="mb-0.5 flex items-center gap-1 text-[11px] opacity-80">
                                                    <Bot className="size-3" /> Bot
                                                </span>
                                            )}
                                            <p>{m.body}</p>
                                            <div
                                                className={cn(
                                                    'mt-1 flex items-center justify-end gap-1 text-[10px]',
                                                    m.direction === 'out' ? 'text-accent-contrast/70' : 'text-tertiary',
                                                )}
                                            >
                                                {relativeTime(m.sent_at)}
                                                {m.direction === 'out' && <StatusTick status={m.status} />}
                                            </div>
                                            {m.status === 'failed' && (
                                                <button className="mt-1 text-[11px] font-medium underline">Retry</button>
                                            )}
                                        </div>
                                    </div>
                                ),
                            )}
                            <div ref={endRef} />
                        </div>

                        {/* composer state machine (B3.4) */}
                        <div className="shrink-0 border-t border-default bg-surface p-3">
                            {composerState === 'template' && (
                                <div className="mb-2.5 flex items-center gap-2 rounded-[var(--radius-control)] border border-warning/30 bg-warning-subtle px-3 py-2 text-[12px] text-warning">
                                    <Lock className="size-4 shrink-0" />
                                    <span className="flex-1">
                                        Outside the 24-hour window — only an approved template can be sent.
                                    </span>
                                    <Button size="sm" variant="secondary" onClick={() => toast('Template picker', { tone: 'info' })}>
                                        <FileText className="size-3.5" /> Choose template
                                    </Button>
                                </div>
                            )}
                            {composerState === 'resolved' && (
                                <div className="mb-2.5 rounded-[var(--radius-control)] bg-surface-2 px-3 py-2 text-[12px] text-secondary">
                                    This conversation is resolved. Typing will reopen it.
                                </div>
                            )}

                            <div
                                className={cn(
                                    'rounded-[var(--radius-card)] border border-default bg-canvas',
                                    composerState === 'template' && 'pointer-events-none opacity-50',
                                )}
                            >
                                <textarea
                                    value={draft}
                                    onChange={(e) => setDraft(e.target.value)}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter' && !e.shiftKey) {
                                            e.preventDefault();
                                            send();
                                        }
                                    }}
                                    placeholder="Type a message…  (Enter to send, Shift+Enter for newline)"
                                    rows={2}
                                    className="w-full resize-none bg-transparent px-3.5 pt-3 text-[13px] outline-none placeholder:text-tertiary"
                                />
                                <div className="flex items-center gap-1 px-2 pb-2">
                                    {[Smile, Paperclip, Zap, ShoppingBag].map((Icon, i) => (
                                        <button
                                            key={i}
                                            className="flex size-8 items-center justify-center rounded-[var(--radius-control)] text-secondary hover:bg-surface-hover hover:text-primary"
                                        >
                                            <Icon className="size-[18px]" />
                                        </button>
                                    ))}
                                    <Button size="sm" className="ms-auto" onClick={send} disabled={!draft.trim()}>
                                        <Send className="size-3.5" /> Send
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                ) : (
                    <div className="flex flex-1 items-center justify-center text-sm text-tertiary">
                        Select a conversation
                    </div>
                )}

                {/* RIGHT — customer context (B3.3) */}
                {selected && contextOpen && (
                    <div className="hidden w-[300px] shrink-0 flex-col overflow-y-auto border-s border-default bg-surface xl:flex">
                        <div className="flex flex-col items-center border-b border-default px-5 py-6 text-center">
                            <Avatar name={selected.contact.name} size="lg" channel={selected.channel} />
                            <p className="mt-3 text-sm font-semibold">{selected.contact.name}</p>
                            <p className="text-[12px] text-tertiary capitalize">{selected.channel} · since 2024</p>
                            <Badge tone="accent" className="mt-2">{selected.contact.lifecycle ?? 'Customer'}</Badge>
                        </div>

                        <Section title="Tags">
                            <div className="flex flex-wrap gap-1.5">
                                <Badge tone="info">VIP</Badge>
                                <Badge tone="neutral">Returning</Badge>
                                <button className="inline-flex items-center gap-1 rounded-full border border-dashed border-strong px-2 py-0.5 text-[12px] text-tertiary hover:text-secondary">
                                    <Plus className="size-3" /> Add
                                </button>
                            </div>
                        </Section>

                        <Section title="Recent orders" action="+ Create">
                            <div className="space-y-2">
                                {[
                                    { id: '#1182', total: 84.0, status: 'Fulfilled' },
                                    { id: '#1140', total: 129.5, status: 'Delivered' },
                                ].map((o) => (
                                    <div
                                        key={o.id}
                                        className="flex items-center justify-between rounded-[var(--radius-control)] border border-default px-3 py-2 text-[13px] hover:bg-surface-hover"
                                    >
                                        <span className="font-medium tnum">{o.id}</span>
                                        <span className="tnum text-secondary">{money(o.total)}</span>
                                        <Badge tone="success">{o.status}</Badge>
                                    </div>
                                ))}
                            </div>
                        </Section>

                        <Section title="Products">
                            <button className="flex w-full items-center justify-center gap-1.5 rounded-[var(--radius-control)] border border-dashed border-strong py-2 text-[13px] text-secondary hover:bg-surface-hover">
                                <ShoppingBag className="size-4" /> Send a product to chat
                            </button>
                        </Section>

                        <Section title="Internal notes">
                            <textarea
                                placeholder="Notes are only visible to your team…"
                                rows={3}
                                className="w-full resize-none rounded-[var(--radius-control)] border border-default bg-canvas p-2.5 text-[12px] outline-none placeholder:text-tertiary focus:border-accent"
                            />
                        </Section>
                    </div>
                )}
            </div>
        </AppShell>
    );
}

function Section({ title, action, children }: { title: string; action?: string; children: React.ReactNode }) {
    return (
        <div className="border-b border-default px-5 py-4">
            <div className="mb-2.5 flex items-center justify-between">
                <h4 className="flex items-center gap-1.5 text-[12px] font-semibold uppercase tracking-wide text-tertiary">
                    {title === 'Tags' && <Tag className="size-3" />}
                    {title}
                </h4>
                {action && <button className="text-[12px] font-medium text-accent hover:underline">{action}</button>}
            </div>
            {children}
        </div>
    );
}
