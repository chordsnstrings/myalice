import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    MessageSquare,
    HelpCircle,
    MousePointerClick,
    GitBranch,
    Zap,
    ShoppingBag,
    UserRound,
    Play,
    Check,
    Plus,
    Trash2,
    CircleDot,
    Loader2,
    AlertTriangle,
} from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Input, Textarea } from '@/components/ui/Input';
import { useToast } from '@/components/ui/Toast';
import { cn } from '@/lib/utils';

const palette = [
    { id: 'message', label: 'Message', icon: MessageSquare },
    { id: 'question', label: 'Question', icon: HelpCircle },
    { id: 'buttons', label: 'Buttons', icon: MousePointerClick },
    { id: 'condition', label: 'Condition', icon: GitBranch },
    { id: 'action', label: 'Action', icon: Zap },
    { id: 'product', label: 'Product', icon: ShoppingBag },
    { id: 'handoff', label: 'Handoff', icon: UserRound },
];

const iconFor: Record<string, typeof MessageSquare> = {
    start: Play,
    message: MessageSquare,
    question: HelpCircle,
    buttons: MousePointerClick,
    condition: GitBranch,
    action: Zap,
    product: ShoppingBag,
    handoff: UserRound,
};

const NODE_W = 200;
const NODE_H = 58;
const TERMINAL = ['handoff', 'end'];
const BRANCHING = ['question', 'buttons', 'condition'];

/** Subtle per-type colour so the flow reads at a glance. */
const typeStyle: Record<string, string> = {
    start: 'bg-success-subtle text-success',
    message: 'bg-info-subtle text-info',
    question: 'bg-accent-subtle text-accent',
    buttons: 'bg-accent-subtle text-accent',
    condition: 'bg-warning-subtle text-warning',
    action: 'bg-info-subtle text-info',
    product: 'bg-success-subtle text-success',
    handoff: 'bg-danger-subtle text-danger',
};

interface FlowNode {
    id: string;
    type: string;
    label: string;
    text?: string | null;
    x: number;
    y: number;
    next?: string | null;
    fallback?: string | null;
}
interface Graph {
    nodes: FlowNode[];
}
interface Issue {
    node: string | null;
    severity: string;
    message: string;
}
interface Bot {
    id: number;
    name: string;
    status: string;
    version: number;
}

/** Smooth connector path between two nodes, choosing vertical or side ports. */
function connector(a: FlowNode, b: FlowNode): string {
    const ac = { x: a.x + NODE_W / 2, y: a.y + NODE_H / 2 };
    const bc = { x: b.x + NODE_W / 2, y: b.y + NODE_H / 2 };
    const vertical = Math.abs(bc.x - ac.x) < NODE_W && bc.y > ac.y;
    if (vertical) {
        const sx = ac.x;
        const sy = a.y + NODE_H;
        const tx = bc.x;
        const ty = b.y;
        const dy = (ty - sy) / 2;
        return `M ${sx} ${sy} C ${sx} ${sy + dy} ${tx} ${ty - dy} ${tx} ${ty}`;
    }
    const toRight = bc.x > ac.x;
    const sx = toRight ? a.x + NODE_W : a.x;
    const sy = ac.y;
    const tx = toRight ? b.x : b.x + NODE_W;
    const ty = bc.y;
    const dx = (tx - sx) / 2;
    return `M ${sx} ${sy} C ${sx + dx} ${sy} ${tx - dx} ${ty} ${tx} ${ty}`;
}

export default function Builder({ bot, graph, issues: initialIssues }: { bot: Bot; graph: Graph; issues: Issue[] }) {
    const { toast } = useToast();
    const [nodes, setNodes] = useState<FlowNode[]>(graph.nodes);
    const [selectedId, setSelectedId] = useState<string | null>(graph.nodes[0]?.id ?? null);
    const [issues, setIssues] = useState<Issue[]>(initialIssues);
    const [saveState, setSaveState] = useState<'saved' | 'saving' | 'error'>('saved');
    const dirty = useRef(false);
    const saveTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const node = nodes.find((n) => n.id === selectedId) ?? null;
    const byId = useMemo(() => Object.fromEntries(nodes.map((n) => [n.id, n])), [nodes]);

    const save = useCallback(
        (next: FlowNode[]) => {
            setSaveState('saving');
            window.axios
                .put(`/chatbots/${bot.id}`, { graph: { nodes: next } })
                .then((res) => {
                    setIssues(res.data.issues ?? []);
                    setSaveState('saved');
                })
                .catch(() => setSaveState('error'));
        },
        [bot.id],
    );

    // Debounced autosave whenever the graph changes (after the first render).
    useEffect(() => {
        if (!dirty.current) return;
        if (saveTimer.current) clearTimeout(saveTimer.current);
        saveTimer.current = setTimeout(() => save(nodes), 600);
        return () => {
            if (saveTimer.current) clearTimeout(saveTimer.current);
        };
    }, [nodes, save]);

    const mutate = (updater: (prev: FlowNode[]) => FlowNode[]) => {
        dirty.current = true;
        setNodes(updater);
    };

    const patchNode = (id: string, patch: Partial<FlowNode>) =>
        mutate((prev) => prev.map((n) => (n.id === id ? { ...n, ...patch } : n)));

    const addNode = (type: string) => {
        const n = nodes.filter((x) => x.type === type).length + 1;
        const id = `${type}_${n}`;
        const node: FlowNode = {
            id,
            type,
            label: `${palette.find((p) => p.id === type)?.label ?? 'Node'} ${n}`,
            text: type === 'message' ? 'New message' : null,
            x: 60 + (nodes.length % 4) * 40,
            y: 60 + nodes.length * 24,
            next: null,
            fallback: null,
        };
        mutate((prev) => [...prev, node]);
        setSelectedId(id);
    };

    const deleteNode = (id: string) => {
        mutate((prev) =>
            prev
                .filter((n) => n.id !== id)
                .map((n) => ({
                    ...n,
                    next: n.next === id ? null : n.next,
                    fallback: n.fallback === id ? null : n.fallback,
                })),
        );
        setSelectedId(null);
    };

    // --- Node dragging ---
    const drag = useRef<{ id: string; dx: number; dy: number } | null>(null);
    const onNodePointerDown = (e: React.PointerEvent, n: FlowNode) => {
        setSelectedId(n.id);
        drag.current = { id: n.id, dx: e.clientX - n.x, dy: e.clientY - n.y };
        (e.target as HTMLElement).setPointerCapture?.(e.pointerId);
    };
    const onPointerMove = (e: React.PointerEvent) => {
        if (!drag.current) return;
        const { id, dx, dy } = drag.current;
        patchNode(id, { x: Math.max(8, e.clientX - dx), y: Math.max(8, e.clientY - dy) });
    };
    const onPointerUp = () => {
        drag.current = null;
    };

    const edges = useMemo(() => {
        const out: { from: FlowNode; to: FlowNode; kind: 'next' | 'fallback' }[] = [];
        for (const n of nodes) {
            if (n.next && byId[n.next]) out.push({ from: n, to: byId[n.next], kind: 'next' });
            if (n.fallback && byId[n.fallback]) out.push({ from: n, to: byId[n.fallback], kind: 'fallback' });
        }
        return out;
    }, [nodes, byId]);

    const errorCount = issues.filter((i) => i.severity === 'error').length;
    const others = nodes.filter((n) => n.id !== selectedId);

    return (
        <div className="flex h-screen flex-col bg-canvas">
            <Head title={bot.name} />
            <header className="flex h-14 shrink-0 items-center gap-3 border-b border-default bg-surface px-4">
                <button onClick={() => router.visit('/chatbots')} className="text-secondary hover:text-primary">
                    <ArrowLeft className="size-5" />
                </button>
                <div>
                    <p className="text-sm font-semibold">{bot.name}</p>
                    <p className="flex items-center gap-1 text-[12px] text-tertiary">
                        {saveState === 'saving' ? (
                            <><Loader2 className="size-3 animate-spin" /> Saving…</>
                        ) : saveState === 'error' ? (
                            <span className="text-danger">Save failed — retrying on next change</span>
                        ) : (
                            <><Check className="size-3" /> Saved</>
                        )}
                    </p>
                </div>
                <Badge tone={bot.status === 'live' ? 'success' : 'neutral'} className="ms-1">{bot.status}</Badge>
                {errorCount > 0 && (
                    <Badge tone="danger"><AlertTriangle className="size-3" /> {errorCount} to fix</Badge>
                )}
                <div className="ms-auto flex items-center gap-2">
                    <Button variant="secondary" size="sm" onClick={() => toast('Opening test simulator…', { tone: 'info' })}>
                        <Play className="size-3.5" /> Test
                    </Button>
                    <Button
                        size="sm"
                        onClick={() =>
                            router.post(`/chatbots/${bot.id}/publish`, {}, {
                                onSuccess: () => toast('Published — changes are live', { tone: 'success' }),
                                onError: (e) => toast(e.flow ?? 'Fix flow errors before publishing', { tone: 'error' }),
                            })
                        }
                    >
                        <Check className="size-3.5" /> Publish
                    </Button>
                </div>
            </header>

            <div className="flex min-h-0 flex-1">
                {/* Palette */}
                <aside className="w-44 shrink-0 space-y-1 border-e border-default bg-surface p-3">
                    <p className="px-2 pb-1 text-[11px] font-semibold uppercase tracking-wide text-tertiary">Add a node</p>
                    {palette.map((p) => {
                        const Icon = p.icon;
                        return (
                            <button
                                key={p.id}
                                onClick={() => addNode(p.id)}
                                className="press group flex w-full items-center gap-2.5 rounded-[var(--radius-control)] border border-default bg-canvas px-2.5 py-2 text-[13px] font-medium text-secondary transition-all hover:border-strong hover:text-primary hover:shadow-[var(--shadow-xs)]"
                            >
                                <span className={cn('flex size-6 items-center justify-center rounded-md', typeStyle[p.id] ?? 'bg-accent-subtle text-accent')}>
                                    <Icon className="icon-pop size-3.5" />
                                </span>
                                <span className="flex-1 text-start">{p.label}</span>
                                <Plus className="size-3.5 text-tertiary opacity-0 transition-opacity group-hover:opacity-100" />
                            </button>
                        );
                    })}
                </aside>

                {/* Canvas */}
                <div
                    className="relative min-w-0 flex-1 overflow-auto"
                    style={{ backgroundImage: 'radial-gradient(var(--border) 1px, transparent 1px)', backgroundSize: '20px 20px' }}
                    onPointerMove={onPointerMove}
                    onPointerUp={onPointerUp}
                >
                    <svg className="pointer-events-none absolute inset-0 size-full" style={{ minHeight: 600 }}>
                        <defs>
                            <marker id="arrow" markerWidth="8" markerHeight="8" refX="6" refY="4" orient="auto">
                                <path d="M1,1 L6,4 L1,7" fill="none" stroke="var(--accent)" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                            </marker>
                        </defs>
                        {edges.map(({ from, to, kind }) => (
                            <path
                                key={`${from.id}-${kind}-${to.id}`}
                                d={connector(from, to)}
                                fill="none"
                                stroke="var(--accent)"
                                strokeWidth="2"
                                strokeOpacity={kind === 'fallback' ? 0.4 : 0.6}
                                strokeDasharray={kind === 'fallback' ? '5 5' : undefined}
                                markerEnd="url(#arrow)"
                            />
                        ))}
                    </svg>
                    {nodes.map((n) => {
                        const Icon = iconFor[n.type] ?? MessageSquare;
                        const active = selectedId === n.id;
                        return (
                            <div
                                key={n.id}
                                onPointerDown={(e) => onNodePointerDown(e, n)}
                                style={{ left: n.x, top: n.y, width: NODE_W }}
                                className={cn(
                                    'group absolute flex cursor-grab items-center gap-2.5 rounded-[var(--radius-card)] border bg-surface px-3 py-2.5 text-start shadow-[var(--shadow-sm)] transition-shadow active:cursor-grabbing',
                                    active ? 'border-accent ring-2 ring-accent/30' : 'border-default hover:shadow-[var(--shadow-card-hover)]',
                                )}
                            >
                                <span className="absolute -top-1 left-1/2 size-2 -translate-x-1/2 rounded-full border-2 border-surface bg-strong" />
                                <span className="absolute -bottom-1 left-1/2 size-2 -translate-x-1/2 rounded-full border-2 border-surface bg-strong" />
                                <span className={cn('flex size-7 items-center justify-center rounded-md', typeStyle[n.type] ?? 'bg-accent-subtle text-accent')}>
                                    <Icon className="size-4" />
                                </span>
                                <div className="min-w-0">
                                    <p className="truncate text-[13px] font-medium">{n.label}</p>
                                    <p className="text-[11px] capitalize text-tertiary">{n.type}</p>
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* Inspector */}
                <aside className="w-72 shrink-0 overflow-y-auto border-s border-default bg-surface p-4">
                    {node ? (
                        <>
                            <div className="flex items-center justify-between">
                                <p className="text-[11px] font-semibold uppercase tracking-wide text-tertiary">Selected node</p>
                                {node.type !== 'start' && (
                                    <button
                                        onClick={() => deleteNode(node.id)}
                                        className="press flex size-7 items-center justify-center rounded-[var(--radius-control)] text-tertiary hover:bg-danger-subtle hover:text-danger"
                                        aria-label="Delete node"
                                    >
                                        <Trash2 className="size-4" />
                                    </button>
                                )}
                            </div>
                            <h3 className="mt-1 text-sm font-semibold capitalize">{node.type}</h3>

                            <div className="mt-4 space-y-3">
                                <Input
                                    label="Label"
                                    value={node.label}
                                    onChange={(e) => patchNode(node.id, { label: e.target.value })}
                                />
                                {['message', 'question', 'buttons', 'condition'].includes(node.type) && (
                                    <Textarea
                                        label={node.type === 'message' ? 'Message text' : 'Prompt'}
                                        rows={4}
                                        value={node.text ?? ''}
                                        onChange={(e) => patchNode(node.id, { text: e.target.value })}
                                        placeholder="What should the bot say here?"
                                    />
                                )}

                                {!TERMINAL.includes(node.type) && (
                                    <NodeSelect
                                        label="Next step"
                                        value={node.next ?? ''}
                                        options={others}
                                        onChange={(v) => patchNode(node.id, { next: v || null })}
                                    />
                                )}
                                {BRANCHING.includes(node.type) && (
                                    <NodeSelect
                                        label="Fallback (if no match)"
                                        value={node.fallback ?? ''}
                                        options={others}
                                        onChange={(v) => patchNode(node.id, { fallback: v || null })}
                                    />
                                )}
                            </div>

                            <NodeIssues issues={issues.filter((i) => i.node === node.id)} />
                        </>
                    ) : (
                        <div className="flex flex-col items-center justify-center py-16 text-center">
                            <CircleDot className="mb-3 size-6 text-tertiary" />
                            <p className="text-sm text-secondary">Select a node to edit it,</p>
                            <p className="text-[13px] text-tertiary">or add one from the left.</p>
                        </div>
                    )}
                </aside>
            </div>
        </div>
    );
}

function NodeSelect({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value: string;
    options: FlowNode[];
    onChange: (v: string) => void;
}) {
    return (
        <div className="space-y-1.5">
            <label className="block text-[13px] font-medium text-primary">{label}</label>
            <select
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="h-9 w-full cursor-pointer rounded-[var(--radius-control)] border border-strong bg-surface px-3 text-[13px] text-primary outline-none transition-colors hover:bg-surface-hover focus:border-accent"
            >
                <option value="">— none —</option>
                {options.map((o) => (
                    <option key={o.id} value={o.id}>{o.label}</option>
                ))}
            </select>
        </div>
    );
}

function NodeIssues({ issues }: { issues: Issue[] }) {
    if (issues.length === 0) return null;
    return (
        <div className="mt-4 space-y-1.5">
            {issues.map((i, idx) => (
                <div
                    key={idx}
                    className={cn(
                        'flex items-start gap-2 rounded-[var(--radius-control)] border px-3 py-2 text-[12px]',
                        i.severity === 'error'
                            ? 'border-danger/30 bg-danger-subtle text-danger'
                            : 'border-warning/30 bg-warning-subtle text-warning',
                    )}
                >
                    <AlertTriangle className="mt-px size-3.5 shrink-0" />
                    <span>{i.message}</span>
                </div>
            ))}
        </div>
    );
}
