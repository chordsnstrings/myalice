import { cn } from '@/lib/utils';

/** Underline tabs (A9). Controlled. */
export function Tabs({
    tabs,
    active,
    onChange,
}: {
    tabs: { id: string; label: string; count?: number }[];
    active: string;
    onChange: (id: string) => void;
}) {
    return (
        <div role="tablist" className="flex gap-1 border-b border-default">
            {tabs.map((t) => (
                <button
                    key={t.id}
                    role="tab"
                    aria-selected={active === t.id}
                    onClick={() => onChange(t.id)}
                    className={cn(
                        'relative -mb-px flex items-center gap-1.5 px-3 py-2.5 text-[13px] font-medium transition-colors',
                        active === t.id
                            ? 'text-primary'
                            : 'text-secondary hover:text-primary',
                    )}
                >
                    {t.label}
                    {typeof t.count === 'number' && (
                        <span className="rounded-full bg-surface-2 px-1.5 text-[11px] text-tertiary">{t.count}</span>
                    )}
                    {active === t.id && <span className="absolute inset-x-0 -bottom-px h-0.5 rounded-full bg-accent" />}
                </button>
            ))}
        </div>
    );
}
