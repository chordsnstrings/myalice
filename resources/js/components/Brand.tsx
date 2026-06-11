import { cn } from '@/lib/utils';

export const BRAND_NAME = 'ARKS Messages Platform';

/** Brand lockup: accent mark + stacked wordmark. Used in the shell, auth, onboarding. */
export function Brand({ className }: { className?: string }) {
    return (
        <div className={cn('flex items-center gap-2.5', className)}>
            <div className="brand-gradient glow-accent flex size-7 shrink-0 items-center justify-center rounded-[9px] text-accent-contrast">
                <span className="text-sm font-bold">A</span>
            </div>
            <div className="leading-none">
                <span className="block text-[14px] font-semibold tracking-tight text-primary">ARKS</span>
                <span className="block text-[10px] font-medium uppercase tracking-wide text-tertiary">
                    Messages Platform
                </span>
            </div>
        </div>
    );
}
