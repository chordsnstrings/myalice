import { useEffect, useMemo, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { createPortal } from 'react-dom';
import {
    Search,
    Smile,
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
    ArrowLeft,
    Info,
    Sparkles,
    Trash2,
    X,
} from 'lucide-react';
import { AppShell } from '@/components/shell/AppShell';
import { Avatar, ChannelDot } from '@/components/ui/Avatar';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Tooltip } from '@/components/ui/Tooltip';
import { FilteredEmpty } from '@/components/ui/States';
import { useToast } from '@/components/ui/Toast';
import { cn, relativeTime, money } from '@/lib/utils';
import type { Conversation, Message, PageProps, WorkspaceTag } from '@/types';

interface Props {
    conversations: Conversation[];
    messages: Record<number, Message[]>;
    agents: { id: number; name: string }[];
    templates: { id: number; name: string; body: string }[];
    quickReplies: { id: number; shortcut: string; body: string }[];
    allTags: WorkspaceTag[];
}

const EMOJIS = ['👍', '🙏', '😊', '🎉', '❤️', '🔥', '✅', '👋', '😍', '🚀', '💯', '🛒'];

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

export default function InboxIndex({ conversations, messages: seed, agents, templates, quickReplies, allTags }: Props) {
    const { toast } = useToast();
    const urlParams = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : new URLSearchParams();
    const [activeFilter, setActiveFilter] = useState<(typeof filters)[number]>(urlParams.get('status') === 'resolved' ? 'Closed' : 'All');
    const [channelFilter, setChannelFilter] = useState<string | null>(urlParams.get('channel'));
    const [tagFilter, setTagFilter] = useState<string | null>(urlParams.get('tag'));
    const [convTags, setConvTags] = useState<Record<number, WorkspaceTag[]>>(
        () => Object.fromEntries(conversations.map((c) => [c.id, c.tags ?? []])),
    );
    const [tool, setTool] = useState<'emoji' | 'quick' | null>(null);
    const [query, setQuery] = useState('');
    const [selectedId, setSelectedId] = useState<number | null>(conversations[0]?.id ?? null);
    const [threads, setThreads] = useState(seed);
    const [draft, setDraft] = useState('');
    const [templateOpen, setTemplateOpen] = useState(false);
    const [contextOpen, setContextOpen] = useState(true); // desktop side pane
    const [contextSheet, setContextSheet] = useState(false); // mobile/tablet sheet
    const [mobilePane, setMobilePane] = useState<'list' | 'thread'>('list');
    const endRef = useRef<HTMLDivElement>(null);
    const workspaceId = usePage<PageProps>().props.auth.workspace?.id;

    useEffect(() => {
        const echo = window.Echo;
        if (!echo || !workspaceId) return;
        const channel = echo.private(`workspace.${workspaceId}`);
        channel.listen('.message.created', (e: { conversation_id: number } & Message) => {
            // Dedupe: our own sends are appended optimistically, then echoed back.
            setThreads((t) => {
                const list = t[e.conversation_id] ?? [];
                if (list.some((m) => m.id === e.id)) return t;
                return { ...t, [e.conversation_id]: [...list, e] };
            });
        });
        return () => echo.leave(`workspace.${workspaceId}`);
    }, [workspaceId]);

    const selected = conversations.find((c) => c.id === selectedId) ?? null;
    const messages = selectedId ? (threads[selectedId] ?? []) : [];

    const openConversation = (id: number) => {
        setSelectedId(id);
        setMobilePane('thread');
    };

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
        if (channelFilter) list = list.filter((c) => c.channel === channelFilter);
        if (tagFilter) list = list.filter((c) => (convTags[c.id] ?? []).some((t) => t.name === tagFilter));
        if (query) list = list.filter((c) => c.contact.name.toLowerCase().includes(query.toLowerCase()));
        return list;
    }, [conversations, activeFilter, channelFilter, tagFilter, convTags, query]);

    const addTag = (name?: string, tagId?: number) => {
        if (!selectedId) return;
        window.axios
            .post(`/conversations/${selectedId}/tags`, name ? { name } : { tag_id: tagId })
            .then((res) => {
                const tag = res.data.tag as WorkspaceTag;
                setConvTags((m) => {
                    const cur = m[selectedId] ?? [];
                    return cur.some((t) => t.id === tag.id) ? m : { ...m, [selectedId]: [...cur, tag] };
                });
            });
    };

    const removeTag = (tagId: number) => {
        if (!selectedId) return;
        window.axios.delete(`/conversations/${selectedId}/tags/${tagId}`);
        setConvTags((m) => ({ ...m, [selectedId]: (m[selectedId] ?? []).filter((t) => t.id !== tagId) }));
    };

    const scrollEnd = () => setTimeout(() => endRef.current?.scrollIntoView({ behavior: 'smooth' }), 20);

    const send = async () => {
        const text = draft.trim();
        if (!text || !selectedId || !selected) return;
        if (selected.window_open === false) {
            setTemplateOpen(true); // outside the 24h window only a template can be sent
            return;
        }
        const temp: Message = {
            id: -Date.now(), direction: 'out', author: 'agent', body: text,
            sent_at: new Date().toISOString(), status: 'sending',
        };
        setThreads((t) => ({ ...t, [selectedId]: [...(t[selectedId] ?? []), temp] }));
        setDraft('');
        scrollEnd();
        try {
            const { data } = await window.axios.post(`/conversations/${selectedId}/messages`, { body: text });
            setThreads((t) => ({ ...t, [selectedId]: (t[selectedId] ?? []).map((m) => (m.id === temp.id ? data.message : m)) }));
        } catch {
            setThreads((t) => ({ ...t, [selectedId]: (t[selectedId] ?? []).map((m) => (m.id === temp.id ? { ...m, status: 'failed' as const } : m)) }));
            toast('Message failed to send', { tone: 'error' });
        }
    };

    const sendTemplate = async (templateId: number) => {
        if (!selectedId) return;
        try {
            const { data } = await window.axios.post(`/conversations/${selectedId}/messages`, { template_id: templateId });
            setThreads((t) => ({ ...t, [selectedId]: [...(t[selectedId] ?? []), data.message] }));
            setTemplateOpen(false);
            scrollEnd();
            toast('Template sent', { tone: 'success' });
        } catch {
            toast('Could not send template', { tone: 'error' });
        }
    };

    const resolveConversation = () => {
        if (!selectedId) return;
        router.put(`/conversations/${selectedId}/resolve`, {}, {
            preserveScroll: true,
            onSuccess: () => toast(selected?.status === 'resolved' ? 'Conversation reopened' : 'Conversation resolved', { tone: 'success' }),
        });
    };

    const assignConversation = (assigneeId: number | null) => {
        if (!selectedId) return;
        router.put(`/conversations/${selectedId}/assign`, { assignee_id: assigneeId }, {
            preserveScroll: true,
            onSuccess: () => toast('Assignment updated', { tone: 'success' }),
        });
    };

    const resumeAi = () => {
        if (!selectedId) return;
        router.put(`/conversations/${selectedId}/resume-ai`, {}, {
            preserveScroll: true,
            onSuccess: () => toast('AI resumed — it will take the next message', { tone: 'success' }),
        });
    };

    const sendAiDraft = (cid: number, mid: number) => {
        setThreads((t) => ({
            ...t,
            [cid]: (t[cid] ?? []).map((m) => (m.id === mid ? { ...m, status: 'sent' } : m)),
        }));
        router.post(`/inbox/ai-drafts/${mid}/send`, {}, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => toast('AI draft sent', { tone: 'success' }),
        });
    };

    const dismissAiDraft = (cid: number, mid: number) => {
        setThreads((t) => ({ ...t, [cid]: (t[cid] ?? []).filter((m) => m.id !== mid) }));
        router.delete(`/inbox/ai-drafts/${mid}`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => toast('AI draft dismissed', { tone: 'info' }),
        });
    };

    return (
        <AppShell title="Inbox">
            <Head title="Inbox" />
            <div className="flex h-full">
                {/* LEFT — conversation list (B3.1). Full-width on mobile until a chat is opened. */}
                <div
                    className={cn(
                        'w-full shrink-0 flex-col border-e border-default bg-surface lg:flex lg:w-[300px]',
                        mobilePane === 'thread' ? 'hidden lg:flex' : 'flex',
                    )}
                >
                    <div className="border-b border-default p-3">
                        <div className="flex items-center gap-2 rounded-[var(--radius-control)] border border-default bg-canvas px-2.5">
                            <Search className="size-4 text-tertiary" />
                            <input
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Search conversations"
                                className="h-9 flex-1 bg-transparent text-[13px] outline-none placeholder:text-tertiary"
                            />
                        </div>
                        <div className="mt-2.5 flex gap-1 overflow-x-auto">
                            {filters.map((f) => (
                                <button
                                    key={f}
                                    onClick={() => setActiveFilter(f)}
                                    className={cn(
                                        'press whitespace-nowrap rounded-full px-2.5 py-1 text-[12px] font-medium transition-colors',
                                        activeFilter === f ? 'bg-accent-subtle text-accent' : 'text-secondary hover:bg-surface-hover',
                                    )}
                                >
                                    {f}
                                </button>
                            ))}
                        </div>
                        {(channelFilter || tagFilter) && (
                            <div className="mt-2 flex flex-wrap gap-1.5">
                                {channelFilter && (
                                    <button onClick={() => setChannelFilter(null)} className="press inline-flex items-center gap-1 rounded-full bg-accent-subtle px-2.5 py-1 text-[12px] font-medium capitalize text-accent">
                                        {channelFilter}
                                        <X className="size-3" />
                                    </button>
                                )}
                                {tagFilter && (
                                    <button onClick={() => setTagFilter(null)} className="press inline-flex items-center gap-1 rounded-full bg-accent-subtle px-2.5 py-1 text-[12px] font-medium text-accent">
                                        #{tagFilter}
                                        <X className="size-3" />
                                    </button>
                                )}
                            </div>
                        )}
                    </div>

                    <div className="min-h-0 flex-1 overflow-y-auto">
                        {filtered.length === 0 ? (
                            <FilteredEmpty onClear={() => { setActiveFilter('All'); setQuery(''); }} />
                        ) : (
                            filtered.map((c) => (
                                <button
                                    key={c.id}
                                    onClick={() => openConversation(c.id)}
                                    className={cn(
                                        'relative flex w-full items-start gap-3 border-b border-default px-3 py-3 text-start transition-colors active:bg-surface-2',
                                        selectedId === c.id ? 'lg:bg-accent-subtle' : 'hover:bg-surface-hover',
                                    )}
                                >
                                    {selectedId === c.id && (
                                        <span className="brand-gradient absolute inset-y-2 start-0 hidden w-[3px] rounded-e-full lg:block" />
                                    )}
                                    <Avatar name={c.contact.name} channel={c.channel} />
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="truncate text-[13px] font-medium text-primary">{c.contact.name}</span>
                                            <span className="shrink-0 text-[11px] text-tertiary">{relativeTime(c.last_message_at)}</span>
                                        </div>
                                        <div className="mt-0.5 flex items-center gap-1.5">
                                            <p className="min-w-0 flex-1 truncate text-[12px] text-secondary">{c.last_message}</p>
                                            {c.sla_breaching && <span className="size-1.5 shrink-0 rounded-full bg-warning" title="SLA breaching" />}
                                            {c.unread > 0 && (
                                                <span className="brand-gradient flex size-4 shrink-0 items-center justify-center rounded-full text-[10px] font-semibold text-accent-contrast">
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
                <div
                    className={cn(
                        'min-w-0 flex-1 flex-col bg-canvas',
                        mobilePane === 'list' ? 'hidden lg:flex' : 'flex',
                    )}
                >
                    {selected ? (
                        <>
                            <div className="flex h-14 shrink-0 items-center gap-2 border-b border-default bg-surface px-3 sm:gap-3 sm:px-4">
                                <button
                                    onClick={() => setMobilePane('list')}
                                    aria-label="Back"
                                    className="press -ms-1 flex size-9 items-center justify-center rounded-[var(--radius-control)] text-secondary hover:bg-surface-hover lg:hidden"
                                >
                                    <ArrowLeft className="size-5" />
                                </button>
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
                                    {selected.ai_status === 'active' && (
                                        <Badge tone="accent" className="hidden gap-1 sm:inline-flex">
                                            <Sparkles className="size-3" /> AI handling
                                        </Badge>
                                    )}
                                    {selected.ai_status === 'handed_off' && (
                                        <Badge tone="warning" className="hidden sm:inline-flex">
                                            AI handed off
                                        </Badge>
                                    )}
                                    {(selected.ai_status === 'handed_off' || selected.ai_status === 'suppressed') && (
                                        <Button size="sm" variant="secondary" className="hidden sm:inline-flex" onClick={resumeAi} title="Hand back to the AI">
                                            <Sparkles className="size-3.5" /> Resume AI
                                        </Button>
                                    )}
                                    <select
                                        className="hidden h-8 rounded-[var(--radius-control)] border border-strong bg-surface px-2 text-[12px] text-secondary sm:block"
                                        value={selected.assignee?.id ?? ''}
                                        onChange={(e) => assignConversation(e.target.value ? Number(e.target.value) : null)}
                                        title="Assign to a teammate"
                                    >
                                        <option value="">Unassigned</option>
                                        {agents.map((a) => (
                                            <option key={a.id} value={a.id}>{a.name}</option>
                                        ))}
                                    </select>
                                    <Button size="sm" variant="secondary" className="hidden sm:inline-flex" onClick={resolveConversation}>
                                        {selected.status === 'resolved' ? 'Reopen' : 'Resolve'}
                                    </Button>
                                    <Badge tone={selected.status === 'open' ? 'accent' : 'neutral'} className="hidden sm:inline-flex">
                                        {selected.status}
                                    </Badge>
                                    {/* Desktop side-pane toggle */}
                                    <Tooltip label="Toggle context">
                                        <button
                                            onClick={() => setContextOpen((o) => !o)}
                                            className="press hidden size-8 items-center justify-center rounded-[var(--radius-control)] text-secondary hover:bg-surface-hover xl:flex"
                                        >
                                            <PanelRightClose className="size-[18px]" />
                                        </button>
                                    </Tooltip>
                                    {/* Mobile/tablet context sheet trigger */}
                                    <button
                                        onClick={() => setContextSheet(true)}
                                        aria-label="Customer details"
                                        className="press flex size-9 items-center justify-center rounded-[var(--radius-control)] text-secondary hover:bg-surface-hover sm:size-8 xl:hidden"
                                    >
                                        <Info className="size-[18px]" />
                                    </button>
                                    <button className="press flex size-9 items-center justify-center rounded-[var(--radius-control)] text-secondary hover:bg-surface-hover sm:size-8">
                                        <MoreHorizontal className="size-[18px]" />
                                    </button>
                                </div>
                            </div>

                            <div className="min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-5 sm:px-6">
                                {messages.map((m) =>
                                    m.author === 'system' ? (
                                        <div key={m.id} className="flex justify-center">
                                            <span className="rounded-full bg-surface-2 px-3 py-1 text-[12px] text-tertiary">{m.body}</span>
                                        </div>
                                    ) : m.author === 'bot' && m.status === 'draft' ? (
                                        <div key={m.id} className="flex justify-end">
                                            <div className="max-w-[82%] rounded-[var(--radius-card)] border border-warning/40 bg-warning-subtle px-3.5 py-2.5 text-[13px] sm:max-w-[68%]">
                                                <span className="mb-1 flex items-center gap-1 text-[11px] font-medium text-warning">
                                                    <Sparkles className="size-3" /> AI draft — review before sending
                                                </span>
                                                <p className="text-primary">{m.body}</p>
                                                <div className="mt-2 flex items-center gap-2">
                                                    <Button size="sm" onClick={() => sendAiDraft(selected.id, m.id)}>
                                                        <Send className="size-3.5" /> Send
                                                    </Button>
                                                    <Button size="sm" variant="ghost" onClick={() => dismissAiDraft(selected.id, m.id)}>
                                                        <Trash2 className="size-3.5" /> Dismiss
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <div key={m.id} className={cn('flex animate-bubble', m.direction === 'out' ? 'justify-end' : 'justify-start')}>
                                            <div
                                                className={cn(
                                                    'max-w-[82%] rounded-[var(--radius-card)] px-3.5 py-2 text-[13px] leading-relaxed shadow-[var(--shadow-xs)] sm:max-w-[68%]',
                                                    m.direction === 'out'
                                                        ? 'brand-gradient rounded-br-[5px] text-accent-contrast'
                                                        : 'rounded-bl-[5px] border border-default bg-surface text-primary',
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
                                                {m.status === 'failed' && <button className="mt-1 text-[11px] font-medium underline">Retry</button>}
                                            </div>
                                        </div>
                                    ),
                                )}
                                <div ref={endRef} />
                            </div>

                            <div className="shrink-0 border-t border-default bg-surface p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                                {composerState === 'template' && (
                                    <div className="mb-2.5 rounded-[var(--radius-control)] border border-warning/30 bg-warning-subtle px-3 py-2 text-[12px] text-warning">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Lock className="size-4 shrink-0" />
                                            <span className="flex-1">Outside the 24-hour window — only an approved template can be sent.</span>
                                            <Button size="sm" variant="secondary" onClick={() => setTemplateOpen((o) => !o)}>
                                                <FileText className="size-3.5" /> {templateOpen ? 'Hide templates' : 'Choose template'}
                                            </Button>
                                        </div>
                                        {templateOpen && (
                                            <div className="mt-2 space-y-1">
                                                {templates.length === 0 ? (
                                                    <p className="text-tertiary">No approved templates yet — create one under Templates.</p>
                                                ) : (
                                                    templates.map((t) => (
                                                        <button
                                                            key={t.id}
                                                            onClick={() => sendTemplate(t.id)}
                                                            className="block w-full truncate rounded-[var(--radius-control)] border border-default bg-surface px-2.5 py-1.5 text-start text-secondary hover:bg-surface-hover"
                                                        >
                                                            <span className="font-medium text-primary">{t.name}</span>
                                                            <span className="ms-2 text-tertiary">{t.body.slice(0, 64)}</span>
                                                        </button>
                                                    ))
                                                )}
                                            </div>
                                        )}
                                    </div>
                                )}
                                {composerState === 'resolved' && (
                                    <div className="mb-2.5 rounded-[var(--radius-control)] bg-surface-2 px-3 py-2 text-[12px] text-secondary">
                                        This conversation is resolved. Typing will reopen it.
                                    </div>
                                )}

                                <div className={cn('rounded-[var(--radius-card)] border border-default bg-canvas', composerState === 'template' && 'pointer-events-none opacity-50')}>
                                    <textarea
                                        value={draft}
                                        onChange={(e) => setDraft(e.target.value)}
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter' && !e.shiftKey) {
                                                e.preventDefault();
                                                send();
                                            }
                                        }}
                                        placeholder="Type a message…"
                                        rows={2}
                                        className="w-full resize-none bg-transparent px-3.5 pt-3 text-[13px] outline-none placeholder:text-tertiary"
                                    />
                                    <div className="relative flex items-center gap-1 px-2 pb-2">
                                        <button
                                            onClick={() => setTool((t) => (t === 'emoji' ? null : 'emoji'))}
                                            aria-label="Emoji"
                                            className={cn(
                                                'press flex size-9 items-center justify-center rounded-[var(--radius-control)] hover:bg-surface-hover hover:text-primary sm:size-8',
                                                tool === 'emoji' ? 'bg-surface-hover text-primary' : 'text-secondary',
                                            )}
                                        >
                                            <Smile className="size-[18px]" />
                                        </button>
                                        <button
                                            onClick={() => setTool((t) => (t === 'quick' ? null : 'quick'))}
                                            aria-label="Quick replies"
                                            className={cn(
                                                'press flex size-9 items-center justify-center rounded-[var(--radius-control)] hover:bg-surface-hover hover:text-primary sm:size-8',
                                                tool === 'quick' ? 'bg-surface-hover text-primary' : 'text-secondary',
                                            )}
                                        >
                                            <Zap className="size-[18px]" />
                                        </button>

                                        {tool === 'emoji' && (
                                            <>
                                                <div className="fixed inset-0 z-40" onClick={() => setTool(null)} />
                                                <div className="animate-pop absolute bottom-full start-2 z-50 mb-2 grid grid-cols-6 gap-1 rounded-[var(--radius-card)] border border-default bg-surface p-2 shadow-[var(--shadow-md)]">
                                                    {EMOJIS.map((e) => (
                                                        <button
                                                            key={e}
                                                            onClick={() => { setDraft((d) => d + e); setTool(null); }}
                                                            className="press flex size-8 items-center justify-center rounded-md text-lg hover:bg-surface-hover"
                                                        >
                                                            {e}
                                                        </button>
                                                    ))}
                                                </div>
                                            </>
                                        )}
                                        {tool === 'quick' && (
                                            <>
                                                <div className="fixed inset-0 z-40" onClick={() => setTool(null)} />
                                                <div className="animate-pop absolute bottom-full start-2 z-50 mb-2 max-h-64 w-72 overflow-y-auto rounded-[var(--radius-card)] border border-default bg-surface p-1.5 shadow-[var(--shadow-md)]">
                                                    {quickReplies.length === 0 ? (
                                                        <p className="px-2.5 py-3 text-[12px] text-tertiary">No saved replies yet.</p>
                                                    ) : (
                                                        quickReplies.map((q) => (
                                                            <button
                                                                key={q.id}
                                                                onClick={() => { setDraft((d) => (d ? d + ' ' : '') + q.body); setTool(null); }}
                                                                className="flex w-full flex-col rounded-[var(--radius-control)] px-2.5 py-1.5 text-start hover:bg-surface-hover"
                                                            >
                                                                <span className="text-[12px] font-semibold text-accent">{q.shortcut}</span>
                                                                <span className="line-clamp-1 text-[12px] text-secondary">{q.body}</span>
                                                            </button>
                                                        ))
                                                    )}
                                                </div>
                                            </>
                                        )}

                                        <Button size="sm" className="ms-auto press" onClick={send} disabled={!draft.trim()}>
                                            <Send className="size-3.5" /> Send
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </>
                    ) : (
                        <div className="flex flex-1 items-center justify-center text-sm text-tertiary">Select a conversation</div>
                    )}
                </div>

                {/* RIGHT — customer context: desktop side pane */}
                {selected && contextOpen && (
                    <aside className="hidden w-[300px] shrink-0 flex-col overflow-y-auto border-s border-default bg-surface xl:flex">
                        <ContextContent selected={selected} tags={convTags[selected.id] ?? []} allTags={allTags} onAdd={addTag} onRemove={removeTag} />
                    </aside>
                )}
            </div>

            {/* Customer context: mobile/tablet bottom sheet */}
            {selected && contextSheet &&
                createPortal(
                    <div className="fixed inset-0 z-[80] xl:hidden">
                        <div className="absolute inset-0 bg-gray-900/40 animate-in" onClick={() => setContextSheet(false)} />
                        <div className="pb-safe animate-slide-up absolute inset-x-0 bottom-0 max-h-[88vh] overflow-y-auto rounded-t-[18px] border-t border-default bg-surface">
                            <div className="sticky top-0 flex items-center justify-between border-b border-default bg-surface px-5 py-3">
                                <h2 className="text-sm font-semibold">Customer</h2>
                                <button onClick={() => setContextSheet(false)} aria-label="Close" className="press text-tertiary hover:text-primary">
                                    <X className="size-5" />
                                </button>
                            </div>
                            <ContextContent selected={selected} tags={convTags[selected.id] ?? []} allTags={allTags} onAdd={addTag} onRemove={removeTag} />
                        </div>
                    </div>,
                    document.body,
                )}
        </AppShell>
    );
}

function ContextContent({
    selected,
    tags,
    allTags,
    onAdd,
    onRemove,
}: {
    selected: Conversation;
    tags: WorkspaceTag[];
    allTags: WorkspaceTag[];
    onAdd: (name?: string, tagId?: number) => void;
    onRemove: (tagId: number) => void;
}) {
    const [menu, setMenu] = useState(false);
    const [draft, setDraft] = useState('');
    const tone = (c: string): 'neutral' | 'accent' | 'success' | 'warning' | 'danger' | 'info' =>
        (['neutral', 'accent', 'success', 'warning', 'danger', 'info'].includes(c) ? c : 'accent') as 'accent';
    const unused = allTags.filter((t) => !tags.some((x) => x.id === t.id));

    return (
        <>
            <div className="flex flex-col items-center border-b border-default px-5 py-6 text-center">
                <Avatar name={selected.contact.name} size="lg" channel={selected.channel} />
                <p className="mt-3 text-sm font-semibold">{selected.contact.name}</p>
                <p className="text-[12px] capitalize text-tertiary">{selected.channel} · since 2024</p>
                <Badge tone="accent" className="mt-2">{selected.contact.lifecycle ?? 'Customer'}</Badge>
            </div>

            <Section title="Topics">
                <div className="relative flex flex-wrap items-center gap-1.5">
                    {tags.length === 0 && <span className="text-[12px] text-tertiary">No topics yet</span>}
                    {tags.map((t) => (
                        <span key={t.id} className="group/tag inline-flex items-center">
                            <Badge tone={tone(t.color)}>
                                {t.name}
                                <button onClick={() => onRemove(t.id)} aria-label={`Remove ${t.name}`} className="ms-0.5 opacity-60 hover:opacity-100">
                                    <X className="size-3" />
                                </button>
                            </Badge>
                        </span>
                    ))}
                    <button
                        onClick={() => setMenu((o) => !o)}
                        className="press inline-flex items-center gap-1 rounded-full border border-dashed border-strong px-2 py-0.5 text-[12px] text-tertiary hover:text-secondary"
                    >
                        <Plus className="size-3" /> Add
                    </button>

                    {menu && (
                        <>
                            <div className="fixed inset-0 z-40" onClick={() => setMenu(false)} />
                            <div className="animate-pop absolute start-0 top-full z-50 mt-1.5 w-56 rounded-[var(--radius-card)] border border-default bg-surface p-1.5 shadow-[var(--shadow-md)]">
                                <input
                                    value={draft}
                                    onChange={(e) => setDraft(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && draft.trim() && (onAdd(draft.trim()), setDraft(''), setMenu(false))}
                                    placeholder="New topic + Enter"
                                    className="mb-1 h-8 w-full rounded-[var(--radius-control)] border border-strong bg-surface px-2.5 text-[13px] outline-none focus:border-accent"
                                    autoFocus
                                />
                                <div className="max-h-48 overflow-y-auto">
                                    {unused.map((t) => (
                                        <button
                                            key={t.id}
                                            onClick={() => { onAdd(undefined, t.id); setMenu(false); }}
                                            className="flex w-full items-center gap-2 rounded-[var(--radius-control)] px-2 py-1.5 text-start text-[13px] hover:bg-surface-hover"
                                        >
                                            <Badge tone={tone(t.color)}>{t.name}</Badge>
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </>
                    )}
                </div>
            </Section>

            <Section title="Recent orders" action="+ Create">
                <div className="space-y-2">
                    {[
                        { id: '#1182', total: 84.0, status: 'Fulfilled' },
                        { id: '#1140', total: 129.5, status: 'Delivered' },
                    ].map((o) => (
                        <div key={o.id} className="flex items-center justify-between rounded-[var(--radius-control)] border border-default px-3 py-2 text-[13px] hover:bg-surface-hover">
                            <span className="font-medium tnum">{o.id}</span>
                            <span className="tnum text-secondary">{money(o.total)}</span>
                            <Badge tone="success">{o.status}</Badge>
                        </div>
                    ))}
                </div>
            </Section>

            <Section title="Products">
                <button className="press flex w-full items-center justify-center gap-1.5 rounded-[var(--radius-control)] border border-dashed border-strong py-2 text-[13px] text-secondary hover:bg-surface-hover">
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
        </>
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
