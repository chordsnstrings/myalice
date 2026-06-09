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
    Gauge,
    DollarSign,
    Star,
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
import { useTranslations } from '@/hooks/useTranslations';
import { Tooltip } from '@/components/ui/Tooltip';
import { Avatar } from '@/components/ui/Avatar';
import { useToast } from '@/components/ui/Toast';
import { CommandPalette } from './CommandPalette';
import { OfflineBanner } from './OfflineBanner';
import { Brand } from '@/components/Brand';
import type { PageProps } from '@/types';

interface NavItem {
    key: string;
    href: string;
    icon: React.ComponentType<{ className?: string }>;
    badge?: number;
    /** Plan feature this item requires; absent = always available. */
    feature?: string;
    /** Capability required to see this item (e.g. manager reports). */
    managerOnly?: boolean;
}

const nav: NavItem[] = [
    { key: 'nav.inbox', href: '/inbox', icon: Inbox, badge: 7 },
    { key: 'nav.contacts', href: '/contacts', icon: Users },
    { key: 'nav.chatbots', href: '/chatbots', icon: Bot },
    { key: 'nav.broadcasts', href: '/broadcasts', icon: Megaphone },
    { key: 'nav.automations', href: '/automations', icon: Workflow, feature: 'automation' },
    { key: 'nav.commerce', href: '/orders', icon: ShoppingBag },
    { key: 'nav.analytics', href: '/dashboard', icon: BarChart3 },
];

const reportsNav: NavItem[] = [
    { key: 'nav.agents', href: '/reports/agents', icon: Gauge, managerOnly: true },
    { key: 'nav.sales', href: '/reports/sales', icon: DollarSign, managerOnly: true },
    { key: 'nav.csat', href: '/reports/csat', icon: Star, managerOnly: true },
];

const languages = [
    { code: 'en', label: 'English' },
    { code: 'ar', label: 'العربية' },
    { code: 'es', label: 'Español' },
    { code: 'pt', label: 'Português' },
];

export function AppShell({ title, children }: { title?: string; children: ReactNode }) {
    const { props, url } = usePage<PageProps>();
    const { theme, toggle } = useTheme();
    const { t, locale } = useTranslations();
    const { toast } = useToast();
    const [menuOpen, setMenuOpen] = useState(false);
    const workspace = props.auth.workspace;
    const user = props.auth.user;
    const features = props.auth.features ?? [];
    const can = props.auth.can ?? {};
    const lowWallet = (workspace?.wallet_balance ?? 0) < 25;

    const isActive = (href: string) => url.startsWith(href);

    const renderItem = (item: NavItem) => {
        const active = isActive(item.href);
        const Icon = item.icon;
        const locked = !!item.feature && !features.includes(item.feature);
        const className = cn(
            'group relative flex w-full items-center gap-3 rounded-[var(--radius-control)] px-3 py-2 text-start text-[13px] font-medium transition-colors',
            active ? 'bg-accent-subtle text-accent' : 'text-secondary hover:bg-surface-hover hover:text-primary',
        );
        const inner = (
            <>
                {active && (
                    <span className="absolute -start-3 top-1/2 h-5 w-[3px] -translate-y-1/2 rounded-e-full bg-accent" />
                )}
                <Icon className="size-[18px] shrink-0" />
                <span className="flex-1">{t(item.key)}</span>
                {locked ? (
                    <Lock className="size-3.5 text-tertiary" />
                ) : item.badge ? (
                    <span className="rounded-full bg-accent px-1.5 text-[11px] font-semibold text-accent-contrast">
                        {item.badge}
                    </span>
                ) : null}
            </>
        );

        // Locked → upgrade explainer, never a dead end (B2 / C-17).
        return locked ? (
            <button
                key={item.href}
                className={className}
                onClick={() =>
                    toast('Automations is on the Business plan. Upgrade to enable it.', {
                        tone: 'info',
                        action: { label: 'Upgrade', onClick: () => router.visit('/settings/billing') },
                    })
                }
            >
                {inner}
            </button>
        ) : (
            <Link key={item.href} href={item.href} className={className}>
                {inner}
            </Link>
        );
    };

    return (
        <div className="flex h-screen overflow-hidden bg-canvas text-primary">
            <CommandPalette />

            {/* Nav rail (B2) */}
            <aside className="flex w-[228px] shrink-0 flex-col border-e border-default bg-surface">
                <div className="flex h-14 items-center px-5">
                    <Brand />
                </div>

                <nav className="flex-1 space-y-0.5 px-3 py-2">
                    {nav.map(renderItem)}

                    {can.manage_team && (
                        <>
                            <p className="px-3 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-wide text-tertiary">
                                {t('nav.reports')}
                            </p>
                            {reportsNav.map(renderItem)}
                        </>
                    )}
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
                        {t('nav.settings')}
                    </Link>
                </div>
            </aside>

            {/* Main column */}
            <div className="flex min-w-0 flex-1 flex-col">
                <OfflineBanner />
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
                        <span className="flex-1 text-start">{t('common.search')}</span>
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
                                    <div className="my-1 h-px bg-default" />
                                    <p className="px-3 pb-1 pt-1 text-[11px] font-medium uppercase tracking-wide text-tertiary">
                                        {t('common.language')}
                                    </p>
                                    <div className="flex flex-wrap gap-1 px-2 pb-1.5">
                                        {languages.map((l) => (
                                            <button
                                                key={l.code}
                                                onClick={() =>
                                                    router.post(
                                                        '/locale',
                                                        { locale: l.code },
                                                        { onSuccess: () => window.location.reload() },
                                                    )
                                                }
                                                className={cn(
                                                    'rounded-md px-2 py-1 text-[12px] font-medium',
                                                    locale === l.code ? 'bg-accent-subtle text-accent' : 'text-secondary hover:bg-surface-hover',
                                                )}
                                            >
                                                {l.label}
                                            </button>
                                        ))}
                                    </div>
                                    <div className="my-1 h-px bg-default" />
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
