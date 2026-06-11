import { useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';
import { Bell, Inbox } from 'lucide-react';
import { cn, relativeTime } from '@/lib/utils';
import { Tooltip } from '@/components/ui/Tooltip';

interface Item {
    id: number;
    title: string;
    text: string;
    tone: 'warning' | 'info';
    channel: string;
    at: string | null;
}

/** Top-bar bell: real "needs attention" feed (unassigned / SLA-breaching chats). */
export function NotificationsBell() {
    const [open, setOpen] = useState(false);
    const [items, setItems] = useState<Item[]>([]);
    const [loaded, setLoaded] = useState(false);

    const load = () => {
        window.axios
            .get('/notifications')
            .then((res) => {
                setItems(res.data.items ?? []);
                setLoaded(true);
            })
            .catch(() => setLoaded(true));
    };

    useEffect(load, []);

    return (
        <div className="relative">
            <Tooltip label="Notifications">
                <button
                    onClick={() => setOpen((o) => !o)}
                    aria-label="Notifications"
                    className="press relative flex size-9 items-center justify-center rounded-[var(--radius-control)] text-secondary hover:bg-surface-hover hover:text-primary sm:size-8"
                >
                    <Bell className="size-[18px]" />
                    {items.length > 0 && (
                        <span className="absolute end-2 top-2 size-1.5 rounded-full bg-danger sm:end-1.5 sm:top-1.5" />
                    )}
                </button>
            </Tooltip>

            {open && (
                <>
                    <div className="fixed inset-0 z-40" onClick={() => setOpen(false)} />
                    <div className="animate-pop absolute end-0 top-full z-50 mt-1.5 w-80 rounded-[var(--radius-card)] border border-default bg-surface p-1.5 shadow-[var(--shadow-md)]">
                        <div className="flex items-center justify-between px-2.5 py-1.5">
                            <p className="text-[13px] font-semibold text-primary">Needs attention</p>
                            {items.length > 0 && (
                                <span className="rounded-full bg-surface-2 px-1.5 text-[11px] font-medium text-secondary">{items.length}</span>
                            )}
                        </div>
                        <div className="max-h-80 overflow-y-auto">
                            {!loaded ? (
                                <div className="space-y-1.5 p-1.5">
                                    {[0, 1, 2].map((i) => <div key={i} className="skeleton h-11 w-full" />)}
                                </div>
                            ) : items.length === 0 ? (
                                <div className="flex flex-col items-center gap-1.5 px-4 py-8 text-center">
                                    <Inbox className="size-5 text-tertiary" />
                                    <p className="text-[13px] text-secondary">You're all caught up</p>
                                </div>
                            ) : (
                                items.map((it) => (
                                    <Link
                                        key={it.id}
                                        href="/inbox"
                                        onClick={() => setOpen(false)}
                                        className="flex items-start gap-2.5 rounded-[var(--radius-control)] px-2.5 py-2 transition-colors hover:bg-surface-hover"
                                    >
                                        <span
                                            className={cn(
                                                'mt-1.5 size-2 shrink-0 rounded-full',
                                                it.tone === 'warning' ? 'bg-warning' : 'bg-info',
                                            )}
                                        />
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-[13px] font-medium text-primary">{it.title}</span>
                                            <span className="block truncate text-[12px] text-secondary">{it.text}</span>
                                        </span>
                                        <span className="shrink-0 text-[11px] text-tertiary">{it.at ? relativeTime(it.at) : ''}</span>
                                    </Link>
                                ))
                            )}
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}
