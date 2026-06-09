import { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import {
    Search,
    Inbox,
    Users,
    Bot,
    Megaphone,
    Workflow,
    ShoppingBag,
    BarChart3,
    Settings,
    Moon,
    Sun,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { useTheme } from '@/hooks/useTheme';

interface Command {
    id: string;
    label: string;
    group: string;
    icon: React.ComponentType<{ className?: string }>;
    run: () => void;
}

/** ⌘K command palette — fuzzy nav + actions, keyboard-only operable (B2). */
export function CommandPalette() {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);
    const inputRef = useRef<HTMLInputElement>(null);
    const { toggle, theme } = useTheme();

    const commands = useMemo<Command[]>(
        () => [
            { id: 'inbox', label: 'Go to Inbox', group: 'Navigate', icon: Inbox, run: () => router.visit('/inbox') },
            { id: 'contacts', label: 'Go to Contacts', group: 'Navigate', icon: Users, run: () => router.visit('/contacts') },
            { id: 'bots', label: 'Go to Chatbots', group: 'Navigate', icon: Bot, run: () => router.visit('/chatbots') },
            { id: 'broadcasts', label: 'Go to Broadcasts', group: 'Navigate', icon: Megaphone, run: () => router.visit('/broadcasts') },
            { id: 'automations', label: 'Go to Automations', group: 'Navigate', icon: Workflow, run: () => router.visit('/automations') },
            { id: 'commerce', label: 'Go to Commerce', group: 'Navigate', icon: ShoppingBag, run: () => router.visit('/orders') },
            { id: 'analytics', label: 'Go to Analytics', group: 'Navigate', icon: BarChart3, run: () => router.visit('/dashboard') },
            { id: 'settings', label: 'Open Settings', group: 'Navigate', icon: Settings, run: () => router.visit('/settings') },
            { id: 'new-broadcast', label: 'New broadcast', group: 'Actions', icon: Megaphone, run: () => router.visit('/broadcasts/create') },
            {
                id: 'theme',
                label: theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme',
                group: 'Actions',
                icon: theme === 'dark' ? Sun : Moon,
                run: toggle,
            },
        ],
        [theme, toggle],
    );

    const results = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return commands;
        return commands.filter((c) => c.label.toLowerCase().includes(q));
    }, [commands, query]);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                setOpen((o) => !o);
            }
            if (e.key === 'Escape') setOpen(false);
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, []);

    useEffect(() => {
        if (open) {
            setQuery('');
            setActive(0);
            setTimeout(() => inputRef.current?.focus(), 10);
        }
    }, [open]);

    useEffect(() => setActive(0), [query]);

    if (!open) return null;

    const onKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive((a) => Math.min(a + 1, results.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive((a) => Math.max(a - 1, 0));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            results[active]?.run();
            setOpen(false);
        }
    };

    return createPortal(
        <div className="fixed inset-0 z-[95] flex items-start justify-center p-4 pt-[12vh]">
            <div className="absolute inset-0 bg-gray-900/40 animate-in" onClick={() => setOpen(false)} />
            <div
                role="dialog"
                aria-label="Command palette"
                className="animate-pop relative w-full max-w-lg overflow-hidden rounded-[var(--radius-card)] border border-default bg-surface shadow-[var(--shadow-md)]"
            >
                <div className="flex items-center gap-2.5 border-b border-default px-4">
                    <Search className="size-4 text-tertiary" />
                    <input
                        ref={inputRef}
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        onKeyDown={onKeyDown}
                        placeholder="Search or jump to…"
                        className="h-12 flex-1 bg-transparent text-sm text-primary outline-none placeholder:text-tertiary"
                    />
                    <kbd className="rounded border border-default px-1.5 py-0.5 text-[11px] text-tertiary">ESC</kbd>
                </div>
                <div className="max-h-80 overflow-y-auto p-1.5">
                    {results.length === 0 ? (
                        <p className="px-3 py-6 text-center text-[13px] text-tertiary">No matches</p>
                    ) : (
                        results.map((c, i) => {
                            const Icon = c.icon;
                            return (
                                <button
                                    key={c.id}
                                    onMouseEnter={() => setActive(i)}
                                    onClick={() => {
                                        c.run();
                                        setOpen(false);
                                    }}
                                    className={cn(
                                        'flex w-full items-center gap-3 rounded-md px-3 py-2 text-start text-sm',
                                        i === active ? 'bg-accent-subtle text-accent' : 'text-primary',
                                    )}
                                >
                                    <Icon className="size-4 shrink-0 opacity-80" />
                                    <span className="flex-1">{c.label}</span>
                                    <span className="text-[11px] text-tertiary">{c.group}</span>
                                </button>
                            );
                        })
                    )}
                </div>
            </div>
        </div>,
        document.body,
    );
}
