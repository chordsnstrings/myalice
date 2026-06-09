import { useState, type ReactNode } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import {
    Inbox,
    Users,
    Bot,
    Megaphone,
    Workflow,
    ShoppingBag,
    BarChart3,
    Settings,
    Search,
    Bell,
    Wallet,
    Moon,
    Sun,
    Lock,
    LogOut,
    ChevronDown,
} from 'lucide-react';
import { cn, money } from '@/lib/utils';
import { useTheme } from '@/hooks/useTheme';
import { Tooltip } from '@/components/ui/Tooltip';
import { Avatar } from '@/components/ui/Avatar';
import { CommandPalette } from './CommandPalette';
import type { PageProps } from '@/types';

interface NavItem {
    label: string;
    href: string;
    icon: React.ComponentType<{ className?: string }>;
    badge?: number;
    locked?: boolean;
}

const nav: NavItem[] = [
    { label: 'Inbox', href: '/inbox', icon: Inbox, badge: 7 },
    { label: 'Contacts', href: '/contacts', icon: Users },
    { label: 'Chatbots', href: '/chatbots', icon: Bot },
    { label: 'Broadcasts', href: '/broadcasts', icon: Megaphone },
    { label: 'Automations', href: '/automations', icon: Workflow, locked: true },
    { label: 'Commerce', href: '/orders', icon: ShoppingBag },
    { label: 'Analytics', href: '/dashboard', icon: BarChart3 },
];

export function AppShell({ title, children }: { title?: string; children: ReactNode }) {
    const { props, url } = usePage<PageProps>();
    const { theme, toggle } = useTheme();
    const [menuOpen, setMenuOpen] = useState(false);
    const workspace = props.auth.workspace;
    const user = props.auth.user;
    const lowWallet = (workspace?.wallet_balance ?? 0) < 25;

    const isActive = (href: string) => url.startsWith(href);

    return (
        <div className="flex h-screen overflow-hidden bg-canvas text-primary">
            <CommandPalette />

            {/* Nav rail (B2) */}
            <aside className="flex w-[228px] shrink-0 flex-col border-e border-default bg-surface">
                <div className="flex h-14 items-center gap-2.5 px-5">
                    <div className="flex size-7 items-center justify-center rounded-lg bg-accent text-accent-contrast">
                        <span className="text-sm font-bold">M</span>
                    </div>
                    <span className="text-[15px] font-semibold tracking-tight">MyAlice</span>
                </div>

                <nav className="flex-1 space-y-0.5 px-3 py-2">
                    {nav.map((item) => {
                        const active = isActive(item.href);
                        const Icon = item.icon;
                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={cn(
                                    'group relative flex items-center gap-3 rounded-[var(--radius-control)] px-3 py-2 text-[13px] font-medium transition-colors',
                                    active
                                        ? 'bg-accent-subtle text-accent'
                                        : 'text-secondary hover:bg-surface-hover hover:text-primary',
                                )}
                            >
                                {active && (
                                    <span className="absolute -start-3 top-1/2 h-5 w-[3px] -translate-y-1/2 rounded-e-full bg-accent" />
                                )}
                                <Icon className="size-[18px] shrink-0" />
                                <span className="flex-1">{item.label}</span>
                                {item.locked ? (
                                    <Lock className="size-3.5 text-tertiary" />
                                ) : item.badge ? (
                                    <span className="rounded-full bg-accent px-1.5 text-[11px] font-semibold text-accent-contrast">
                                        {item.badge}
                                    </span>
                                ) : null}
                            </Link>
                        );
                    })}
                </nav>

                <div className="border-t border-default p-3">
                    <Link
                        href="/settings"
                        className={cn(
                            'flex items-center gap-3 rounded-[var(--radius-control)] px-3 py-2 text-[13px] font-medium transition-colors',
                            isActive('/settings')
                                ? 'bg-accent-subtle text-accent'
                                : 'text-secondary hover:bg-surface-hover hover:text-primary',
                        )}
                    >
                        <Settings className="size-[18px]" />
                        Settings
                    </Link>
                </div>
            </aside>

            {/* Main column */}
            <div className="flex min-w-0 flex-1 flex-col">
                {/* Top bar (B2) */}
                <header className="flex h-14 shrink-0 items-center gap-3 border-b border-default bg-surface px-5">
                    <div className="flex items-center gap-2">
                        <h1 className="text-sm font-semibold">{title}</h1>
                    </div>

                    <button
                        onClick={() => {
                            const ev = new KeyboardEvent('keydown', { key: 'k', metaKey: true });
                            document.dispatchEvent(ev);
                        }}
                        className="ms-auto flex h-8 w-64 items-center gap-2 rounded-[var(--radius-control)] border border-default bg-canvas px-3 text-[13px] text-tertiary transition-colors hover:border-strong"
                    >
                        <Search className="size-4" />
                        <span className="flex-1 text-start">Search…</span>
                        <kbd className="rounded border border-default px-1 text-[11px]">⌘K</kbd>
                    </button>

                    {/* Wallet chip — warning tint when low (B2) */}
                    <Link
                        href="/settings/wallet"
                        className={cn(
                            'flex h-8 items-center gap-1.5 rounded-[var(--radius-control)] border px-2.5 text-[13px] font-medium tnum transition-colors',
                            lowWallet
                                ? 'border-warning/30 bg-warning-subtle text-warning'
                                : 'border-default text-secondary hover:bg-surface-hover',
                        )}
                    >
                        <Wallet className="size-4" />
                        {money(workspace?.wallet_balance ?? 0, workspace?.currency)}
                    </Link>

                    <Tooltip label="Notifications">
                        <button className="relative flex size-8 items-center justify-center rounded-[var(--radius-control)] text-secondary hover:bg-surface-hover hover:text-primary">
                            <Bell className="size-[18px]" />
                            <span className="absolute end-1.5 top-1.5 size-1.5 rounded-full bg-danger" />
                        </button>
                    </Tooltip>

                    <Tooltip label={theme === 'dark' ? 'Light mode' : 'Dark mode'}>
                        <button
                            onClick={toggle}
                            aria-label="Toggle theme"
                            className="flex size-8 items-center justify-center rounded-[var(--radius-control)] text-secondary hover:bg-surface-hover hover:text-primary"
                        >
                            {theme === 'dark' ? <Sun className="size-[18px]" /> : <Moon className="size-[18px]" />}
                        </button>
                    </Tooltip>

                    <div className="relative">
                        <button
                            onClick={() => setMenuOpen((o) => !o)}
                            className="flex items-center gap-1.5 rounded-[var(--radius-control)] p-0.5 hover:bg-surface-hover"
                        >
                            <Avatar name={user?.name ?? 'You'} size="sm" />
                            <ChevronDown className="size-3.5 text-tertiary" />
                        </button>
                        {menuOpen && (
                            <>
                                <div className="fixed inset-0 z-40" onClick={() => setMenuOpen(false)} />
                                <div className="animate-in absolute end-0 top-full z-50 mt-1.5 w-52 rounded-[var(--radius-card)] border border-default bg-surface p-1.5 shadow-[var(--shadow-sm)]">
                                    <div className="px-3 py-2">
                                        <p className="text-[13px] font-semibold text-primary">{user?.name}</p>
                                        <p className="text-[12px] text-tertiary">{user?.email}</p>
                                    </div>
                                    <div className="my-1 h-px bg-default" />
                                    <Link href="/settings/profile" className="block rounded-md px-3 py-1.5 text-[13px] text-secondary hover:bg-surface-hover hover:text-primary">
                                        Profile & preferences
                                    </Link>
                                    <button
                                        onClick={() => router.post('/logout')}
                                        className="flex w-full items-center gap-2 rounded-md px-3 py-1.5 text-start text-[13px] text-secondary hover:bg-surface-hover hover:text-primary"
                                    >
                                        <LogOut className="size-4" /> Log out
                                    </button>
                                </div>
                            </>
                        )}
                    </div>
                </header>

                <main className="min-h-0 flex-1 overflow-hidden">{children}</main>
            </div>
        </div>
    );
}
