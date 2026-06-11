import { useEffect, useState, type ReactNode } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { createPortal } from 'react-dom';
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
    Wallet,
    Moon,
    Sun,
    Lock,
    LogOut,
    ChevronDown,
    Menu,
    X,
    Download,
} from 'lucide-react';
import { cn, money } from '@/lib/utils';
import { useTheme } from '@/hooks/useTheme';
import { useTranslations } from '@/hooks/useTranslations';
import { Tooltip } from '@/components/ui/Tooltip';
import { Avatar } from '@/components/ui/Avatar';
import { Switch } from '@/components/ui/Switch';
import { useToast } from '@/components/ui/Toast';
import { CommandPalette } from './CommandPalette';
import { OfflineBanner } from './OfflineBanner';
import { WorkspaceSwitcher } from './WorkspaceSwitcher';
import { NotificationsBell } from './NotificationsBell';
import { Brand } from '@/components/Brand';
import { onInstallAvailability, promptInstall } from '@/lib/pwa';
import type { PageProps } from '@/types';

interface NavItem {
    key: string;
    href: string;
    icon: React.ComponentType<{ className?: string }>;
    badge?: number;
    feature?: string;
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

const openPalette = () => document.dispatchEvent(new KeyboardEvent('keydown', { key: 'k', metaKey: true }));

export function AppShell({ title, children }: { title?: string; children: ReactNode }) {
    const { props, url } = usePage<PageProps>();
    const { theme, toggle } = useTheme();
    const { t, locale } = useTranslations();
    const { toast } = useToast();
    const [menuOpen, setMenuOpen] = useState(false);
    const [moreOpen, setMoreOpen] = useState(false);
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
            'press group relative flex w-full items-center gap-3 rounded-[var(--radius-control)] px-3 py-2 text-start text-[13px] font-medium transition-colors',
            active
                ? 'bg-accent-subtle text-accent shadow-[var(--shadow-xs)]'
                : 'text-secondary hover:bg-surface-hover hover:text-primary',
        );
        const inner = (
            <>
                {active && (
                    <span className="brand-gradient absolute -start-3 top-1/2 h-5 w-[3px] -translate-y-1/2 rounded-e-full" />
                )}
                <Icon className="icon-pop size-[18px] shrink-0" />
                <span className="flex-1">{t(item.key)}</span>
                {locked ? (
                    <Lock className="size-3.5 text-tertiary" />
                ) : item.badge ? (
                    <span className="brand-gradient rounded-full px-1.5 text-[11px] font-semibold text-accent-contrast">
                        {item.badge}
                    </span>
                ) : null}
            </>
        );

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

            {/* Desktop nav rail (B2) */}
            <aside className="hidden w-[228px] shrink-0 flex-col border-e border-default bg-surface lg:flex">
                <div className="flex h-14 items-center px-3">
                    <WorkspaceSwitcher />
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
                            'press flex items-center gap-3 rounded-[var(--radius-control)] px-3 py-2 text-[13px] font-medium transition-colors',
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

                {/* Top bar — responsive (B2) */}
                <header className="flex h-14 shrink-0 items-center gap-2 border-b border-default bg-surface px-3 sm:gap-3 sm:px-5">
                    {/* Mobile brand mark */}
                    <div className="brand-gradient glow-accent flex size-8 items-center justify-center rounded-[10px] text-sm font-bold text-accent-contrast lg:hidden">
                        A
                    </div>
                    <h1 className="truncate text-sm font-semibold">{title}</h1>

                    {/* Desktop search */}
                    <button
                        onClick={openPalette}
                        className="press ms-auto hidden h-8 w-64 items-center gap-2 rounded-[var(--radius-control)] border border-default bg-canvas px-3 text-[13px] text-tertiary transition-colors hover:border-strong lg:flex"
                    >
                        <Search className="size-4" />
                        <span className="flex-1 text-start">{t('common.search')}</span>
                        <kbd className="rounded border border-default px-1 text-[11px]">⌘K</kbd>
                    </button>

                    {/* Mobile search icon */}
                    <button
                        onClick={openPalette}
                        aria-label={t('common.search')}
                        className="press ms-auto flex size-9 items-center justify-center rounded-[var(--radius-control)] text-secondary hover:bg-surface-hover lg:hidden"
                    >
                        <Search className="size-[18px]" />
                    </button>

                    {/* Wallet chip — hidden on the smallest screens */}
                    <Link
                        href="/settings/wallet"
                        className={cn(
                            'hidden h-8 items-center gap-1.5 rounded-[var(--radius-control)] border px-2.5 text-[13px] font-medium tnum transition-colors sm:flex',
                            lowWallet
                                ? 'border-warning/30 bg-warning-subtle text-warning'
                                : 'border-default text-secondary hover:bg-surface-hover',
                        )}
                    >
                        <Wallet className="size-4" />
                        {money(workspace?.wallet_balance ?? 0, workspace?.currency)}
                    </Link>

                    <NotificationsBell />

                    <Tooltip label={theme === 'dark' ? 'Light mode' : 'Dark mode'}>
                        <button
                            onClick={toggle}
                            aria-label="Toggle theme"
                            className="press hidden size-8 items-center justify-center rounded-[var(--radius-control)] text-secondary hover:bg-surface-hover hover:text-primary sm:flex"
                        >
                            {theme === 'dark' ? <Sun className="size-[18px]" /> : <Moon className="size-[18px]" />}
                        </button>
                    </Tooltip>

                    <div className="relative hidden sm:block">
                        <button
                            onClick={() => setMenuOpen((o) => !o)}
                            className="press flex items-center gap-1.5 rounded-[var(--radius-control)] p-0.5 hover:bg-surface-hover"
                        >
                            <Avatar name={user?.name ?? 'You'} size="sm" />
                            <ChevronDown className="size-3.5 text-tertiary" />
                        </button>
                        {menuOpen && (
                            <>
                                <div className="fixed inset-0 z-40" onClick={() => setMenuOpen(false)} />
                                <div className="animate-pop absolute end-0 top-full z-50 mt-1.5 w-52 rounded-[var(--radius-card)] border border-default bg-surface p-1.5 shadow-[var(--shadow-sm)]">
                                    <div className="px-3 py-2">
                                        <p className="text-[13px] font-semibold text-primary">{user?.name}</p>
                                        <p className="text-[12px] text-tertiary">{user?.email}</p>
                                    </div>
                                    <div className="my-1 h-px bg-default" />
                                    <Link href="/settings/profile" className="block rounded-md px-3 py-1.5 text-[13px] text-secondary hover:bg-surface-hover hover:text-primary">
                                        Profile &amp; preferences
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
                                                    router.post('/locale', { locale: l.code }, { onSuccess: () => window.location.reload() })
                                                }
                                                className={cn(
                                                    'press rounded-md px-2 py-1 text-[12px] font-medium',
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

                {/* Content — bottom padding clears the mobile tab bar */}
                <main key={url.split('?')[0]} className="animate-in min-h-0 flex-1 overflow-hidden pb-[calc(56px+env(safe-area-inset-bottom))] lg:pb-0">
                    {children}
                </main>
            </div>

            {/* Mobile bottom tab bar (B13) */}
            <nav className="pb-safe fixed inset-x-0 bottom-0 z-40 border-t border-default bg-surface lg:hidden">
                <div className="flex h-14 items-stretch">
                    <TabLink href="/inbox" icon={Inbox} label={t('nav.inbox')} active={isActive('/inbox')} badge={7} />
                    <TabLink href="/contacts" icon={Users} label={t('nav.contacts')} active={isActive('/contacts')} />
                    <TabLink href="/dashboard" icon={BarChart3} label={t('nav.analytics')} active={isActive('/dashboard') || url.startsWith('/reports')} />
                    <button
                        onClick={() => setMoreOpen(true)}
                        className="press flex flex-1 flex-col items-center justify-center gap-0.5 text-[10px] font-medium text-secondary"
                    >
                        <Menu className="size-[22px]" />
                        {t('nav.more', 'More')}
                    </button>
                </div>
            </nav>

            {moreOpen && (
                <MoreSheet
                    onClose={() => setMoreOpen(false)}
                    renderItem={renderItem}
                    can={can}
                    theme={theme}
                    toggleTheme={toggle}
                    user={user}
                />
            )}
        </div>
    );
}

function TabLink({
    href,
    icon: Icon,
    label,
    active,
    badge,
}: {
    href: string;
    icon: React.ComponentType<{ className?: string }>;
    label: string;
    active: boolean;
    badge?: number;
}) {
    return (
        <Link
            href={href}
            className={cn(
                'press relative flex flex-1 flex-col items-center justify-center gap-0.5 text-[10px] font-medium transition-colors',
                active ? 'text-accent' : 'text-secondary',
            )}
        >
            {active && <span className="absolute top-0 h-0.5 w-8 rounded-b-full bg-accent" />}
            <span className="relative">
                <Icon className="size-[22px]" />
                {badge ? (
                    <span className="absolute -end-2 -top-1 flex min-w-4 items-center justify-center rounded-full bg-accent px-1 text-[9px] font-semibold text-accent-contrast">
                        {badge}
                    </span>
                ) : null}
            </span>
            {label}
        </Link>
    );
}

function MoreSheet({
    onClose,
    renderItem,
    can,
    theme,
    toggleTheme,
    user,
}: {
    onClose: () => void;
    renderItem: (item: NavItem) => ReactNode;
    can: PageProps['auth']['can'];
    theme: string;
    toggleTheme: () => void;
    user: PageProps['auth']['user'];
}) {
    const [canInstallApp, setCanInstallApp] = useState(false);
    useEffect(() => onInstallAvailability(setCanInstallApp), []);

    const secondary = nav.filter((n) => !['/inbox', '/contacts', '/dashboard'].includes(n.href));

    return createPortal(
        <div className="fixed inset-0 z-[80] lg:hidden">
            <div className="absolute inset-0 bg-gray-900/40 animate-in" onClick={onClose} />
            <div className="pb-safe animate-slide-up absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto rounded-t-[18px] border-t border-default bg-surface">
                <div className="sticky top-0 flex items-center justify-between border-b border-default bg-surface px-5 py-3.5">
                    <Brand />
                    <button onClick={onClose} aria-label="Close" className="press text-tertiary hover:text-primary">
                        <X className="size-5" />
                    </button>
                </div>

                <div className="stagger space-y-0.5 p-3" onClick={onClose}>
                    {secondary.map((item, i) => (
                        <div key={item.href} style={{ '--i': i } as React.CSSProperties}>
                            {renderItem(item)}
                        </div>
                    ))}
                    {can.manage_team &&
                        reportsNav.map((item, i) => (
                            <div key={item.href} style={{ '--i': secondary.length + i } as React.CSSProperties}>
                                {renderItem(item)}
                            </div>
                        ))}
                    <div style={{ '--i': 10 } as React.CSSProperties}>{renderItem({ key: 'nav.settings', href: '/settings', icon: Settings })}</div>
                </div>

                <div className="border-t border-default p-3">
                    <button
                        onClick={toggleTheme}
                        className="press flex w-full items-center justify-between rounded-[var(--radius-control)] px-3 py-2.5 text-[13px] font-medium text-secondary hover:bg-surface-hover"
                    >
                        <span className="flex items-center gap-3">
                            {theme === 'dark' ? <Sun className="size-[18px]" /> : <Moon className="size-[18px]" />}
                            {theme === 'dark' ? 'Light mode' : 'Dark mode'}
                        </span>
                        <Switch checked={theme === 'dark'} onChange={toggleTheme} label="Toggle theme" />
                    </button>

                    {canInstallApp && (
                        <button
                            onClick={() => promptInstall()}
                            className="press mt-1 flex w-full items-center gap-3 rounded-[var(--radius-control)] px-3 py-2.5 text-[13px] font-medium text-accent hover:bg-surface-hover"
                        >
                            <Download className="size-[18px]" /> Install app
                        </button>
                    )}

                    <div className="mt-3 flex items-center gap-3 border-t border-default px-3 pt-3">
                        <Avatar name={user?.name ?? 'You'} size="sm" />
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-[13px] font-medium">{user?.name}</p>
                            <p className="truncate text-[12px] text-tertiary">{user?.email}</p>
                        </div>
                        <button
                            onClick={() => router.post('/logout')}
                            aria-label="Log out"
                            className="press flex size-9 items-center justify-center rounded-[var(--radius-control)] text-secondary hover:bg-surface-hover hover:text-danger"
                        >
                            <LogOut className="size-[18px]" />
                        </button>
                    </div>
                </div>
            </div>
        </div>,
        document.body,
    );
}
