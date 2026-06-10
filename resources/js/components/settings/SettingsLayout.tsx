import { type ReactNode } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { AppShell } from '@/components/shell/AppShell';
import type { Capabilities, PageProps } from '@/types';

const nav: { label: string; href: string; cap?: keyof Capabilities }[] = [
    { label: 'Workspace', href: '/settings' },
    { label: 'Team & roles', href: '/settings/team', cap: 'manage_team' },
    { label: 'Channels', href: '/settings/channels', cap: 'manage_channels' },
    { label: 'AI agent', href: '/settings/ai-agents', cap: 'manage_bots' },
    { label: 'Business hours', href: '/settings/hours' },
    { label: 'Quick replies & tags', href: '/settings/content' },
    { label: 'Web widget', href: '/settings/widget' },
    { label: 'QR & links', href: '/settings/qr' },
    { label: 'Billing', href: '/settings/billing', cap: 'manage_billing' },
    { label: 'Wallet', href: '/settings/wallet', cap: 'manage_billing' },
    { label: 'Developer', href: '/settings/developer', cap: 'manage_api' },
    { label: 'Profile', href: '/settings/profile' },
];

/** Settings shell: left sub-nav + content (B11). Hides what the role can't use (C-17). */
export function SettingsLayout({ title, children }: { title: string; children: ReactNode }) {
    const { url, props } = usePage<PageProps>();
    const can = props.auth.can ?? {};
    const items = nav.filter((item) => !item.cap || can[item.cap]);

    return (
        <AppShell title="Settings">
            <div className="flex h-full">
                <aside className="w-56 shrink-0 overflow-y-auto border-e border-default bg-surface p-3">
                    <nav className="space-y-0.5">
                        {items.map((item) => {
                            const active = url === item.href || (item.href !== '/settings' && url.startsWith(item.href));
                            return (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className={cn(
                                        'block rounded-[var(--radius-control)] px-3 py-2 text-[13px] font-medium transition-colors',
                                        active
                                            ? 'bg-accent-subtle text-accent'
                                            : 'text-secondary hover:bg-surface-hover hover:text-primary',
                                    )}
                                >
                                    {item.label}
                                </Link>
                            );
                        })}
                    </nav>
                </aside>
                <div className="min-w-0 flex-1 overflow-y-auto">
                    <div className="mx-auto max-w-2xl px-8 py-7">
                        <h2 className="mb-5 text-xl font-semibold tracking-tight">{title}</h2>
                        {children}
                    </div>
                </div>
            </div>
        </AppShell>
    );
}
