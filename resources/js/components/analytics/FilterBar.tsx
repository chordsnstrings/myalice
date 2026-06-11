import { router } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export interface AnalyticsFilterState {
    range: string;
    channel: string | null;
    agent: number | null;
}

interface Props {
    routeUrl: string;
    filters: AnalyticsFilterState;
    channels: { channel: string; name: string }[];
    agents?: { id: number; name: string }[];
}

const ranges = [
    { id: '7d', label: '7 days' },
    { id: '30d', label: '30 days' },
    { id: '90d', label: '90 days' },
];

/** Shared, functional date-range + channel + team filter bar (B10). */
export function FilterBar({ routeUrl, filters, channels, agents }: Props) {
    const go = (patch: Partial<AnalyticsFilterState>) => {
        const next = { ...filters, ...patch };
        router.get(
            routeUrl,
            {
                range: next.range,
                channel: next.channel ?? undefined,
                agent: next.agent ?? undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const selectClass =
        'h-8 cursor-pointer rounded-[var(--radius-control)] border border-strong bg-surface px-2.5 text-[13px] text-primary outline-none transition-colors hover:bg-surface-hover focus:border-accent';

    return (
        <div className="flex flex-wrap items-center gap-2">
            <div className="inline-flex rounded-[var(--radius-control)] border border-default bg-surface p-0.5 shadow-[var(--shadow-xs)]">
                {ranges.map((r) => (
                    <button
                        key={r.id}
                        onClick={() => go({ range: r.id })}
                        className={cn(
                            'press rounded-[6px] px-2.5 py-1 text-[13px] font-medium transition-all',
                            filters.range === r.id
                                ? 'brand-gradient text-accent-contrast shadow-[var(--shadow-xs)]'
                                : 'text-secondary hover:text-primary',
                        )}
                    >
                        {r.label}
                    </button>
                ))}
            </div>

            <select
                value={filters.channel ?? ''}
                onChange={(e) => go({ channel: e.target.value || null })}
                className={selectClass}
                aria-label="Channel"
            >
                <option value="">All channels</option>
                {channels.map((c) => (
                    <option key={c.channel} value={c.channel}>
                        {c.name}
                    </option>
                ))}
            </select>

            {agents && (
                <select
                    value={filters.agent ?? ''}
                    onChange={(e) => go({ agent: e.target.value ? Number(e.target.value) : null })}
                    className={selectClass}
                    aria-label="Team member"
                >
                    <option value="">All team</option>
                    {agents.map((a) => (
                        <option key={a.id} value={a.id}>
                            {a.name}
                        </option>
                    ))}
                </select>
            )}
        </div>
    );
}
