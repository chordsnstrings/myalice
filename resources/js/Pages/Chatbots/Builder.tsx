import { useState } from 'react';
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
} from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
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

interface Node {
    id: string;
    type: string;
    label: string;
    x: number;
    y: number;
}

const initialNodes: Node[] = [
    { id: 'n1', type: 'start', label: 'Start', x: 40, y: 40 },
    { id: 'n2', type: 'message', label: 'Welcome message', x: 40, y: 130 },
    { id: 'n3', type: 'buttons', label: 'How can we help?', x: 40, y: 240 },
    { id: 'n4', type: 'condition', label: 'Order status?', x: 320, y: 240 },
    { id: 'n5', type: 'handoff', label: 'Handoff to agent', x: 320, y: 360 },
];

export default function Builder({ bot }: { bot: { id: number; name: string; status: string } }) {
    const { toast } = useToast();
    const [selected, setSelected] = useState<string>('n2');
    const node = initialNodes.find((n) => n.id === selected);

    return (
        <div className="flex h-screen flex-col bg-canvas">
            <Head title={bot.name} />
            {/* Top bar */}
            <header className="flex h-14 shrink-0 items-center gap-3 border-b border-default bg-surface px-4">
                <button onClick={() => router.visit('/chatbots')} className="text-secondary hover:text-primary">
                    <ArrowLeft className="size-5" />
                </button>
                <div>
                    <p className="text-sm font-semibold">{bot.name}</p>
                    <p className="text-[12px] text-tertiary">Draft · autosaved</p>
                </div>
                <Badge tone={bot.status === 'live' ? 'success' : 'neutral'} className="ms-1">{bot.status}</Badge>
                <div className="ms-auto flex items-center gap-2">
                    <Button variant="secondary" size="sm" onClick={() => toast('Opening test simulator…', { tone: 'info' })}>
                        <Play className="size-3.5" /> Test
                    </Button>
                    <Button size="sm" onClick={() => toast('Published — changes are live', { tone: 'success' })}>
                        <Check className="size-3.5" /> Publish
                    </Button>
                </div>
            </header>

            <div className="flex min-h-0 flex-1">
                {/* Palette */}
                <aside className="w-44 shrink-0 space-y-1 border-e border-default bg-surface p-3">
                    <p className="px-2 pb-1 text-[11px] font-semibold uppercase tracking-wide text-tertiary">Nodes</p>
                    {palette.map((p) => {
                        const Icon = p.icon;
                        return (
                            <div
                                key={p.id}
                                draggable
                                className="flex cursor-grab items-center gap-2.5 rounded-[var(--radius-control)] border border-default bg-canvas px-2.5 py-2 text-[13px] font-medium text-secondary transition-colors hover:border-strong hover:text-primary active:cursor-grabbing"
                            >
                                <Icon className="size-4 text-accent" />
                                {p.label}
                            </div>
                        );
                    })}
                </aside>

                {/* Canvas */}
                <div
                    className="relative min-w-0 flex-1 overflow-auto"
                    style={{
                        backgroundImage: 'radial-gradient(var(--border) 1px, transparent 1px)',
                        backgroundSize: '20px 20px',
                    }}
                >
                    <svg className="pointer-events-none absolute inset-0 size-full">
                        <line x1="120" y1="80" x2="120" y2="130" stroke="var(--border-strong)" strokeWidth="2" />
                        <line x1="120" y1="172" x2="120" y2="240" stroke="var(--border-strong)" strokeWidth="2" />
                        <line x1="240" y1="262" x2="320" y2="262" stroke="var(--border-strong)" strokeWidth="2" />
                        <line x1="400" y1="282" x2="400" y2="360" stroke="var(--border-strong)" strokeWidth="2" />
                    </svg>
                    {initialNodes.map((n) => {
                        const item = palette.find((p) => p.id === n.type);
                        const Icon = item?.icon ?? Play;
                        return (
                            <button
                                key={n.id}
                                onClick={() => setSelected(n.id)}
                                style={{ left: n.x, top: n.y }}
                                className={cn(
                                    'absolute flex w-[200px] items-center gap-2.5 rounded-[var(--radius-card)] border bg-surface px-3 py-2.5 text-start shadow-[var(--shadow-sm)] transition-all',
                                    selected === n.id ? 'border-accent ring-2 ring-accent/30' : 'border-default hover:border-strong',
                                )}
                            >
                                <span className={cn('flex size-7 items-center justify-center rounded-md', n.type === 'start' ? 'bg-success-subtle text-success' : 'bg-accent-subtle text-accent')}>
                                    <Icon className="size-4" />
                                </span>
                                <div className="min-w-0">
                                    <p className="truncate text-[13px] font-medium">{n.label}</p>
                                    <p className="text-[11px] capitalize text-tertiary">{n.type}</p>
                                </div>
                            </button>
                        );
                    })}
                </div>

                {/* Inspector */}
                <aside className="w-72 shrink-0 overflow-y-auto border-s border-default bg-surface p-4">
                    {node ? (
                        <>
                            <p className="text-[11px] font-semibold uppercase tracking-wide text-tertiary">Selected node</p>
                            <h3 className="mt-1 text-sm font-semibold capitalize">{node.type}</h3>
                            <div className="mt-4 space-y-3">
                                <div>
                                    <label className="mb-1 block text-[12px] font-medium">Label</label>
                                    <input
                                        defaultValue={node.label}
                                        className="h-9 w-full rounded-[var(--radius-control)] border border-strong bg-canvas px-3 text-[13px] outline-none focus:border-accent"
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-[12px] font-medium">Message text</label>
                                    <textarea
                                        rows={4}
                                        defaultValue="Hi 👋 Welcome to Acme. How can we help today?"
                                        className="w-full resize-none rounded-[var(--radius-control)] border border-strong bg-canvas p-2.5 text-[13px] outline-none focus:border-accent"
                                    />
                                </div>
                                <div className="rounded-[var(--radius-control)] bg-warning-subtle px-3 py-2 text-[12px] text-warning">
                                    Every Question/Buttons node needs a fallback (re-ask or handoff).
                                </div>
                            </div>
                        </>
                    ) : (
                        <p className="text-sm text-tertiary">Select a node to edit it.</p>
                    )}
                </aside>
            </div>
        </div>
    );
}
