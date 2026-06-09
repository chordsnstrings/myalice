import { cn } from '@/lib/utils';
import { Button } from './Button';
import { Inbox, SearchX, AlertCircle } from 'lucide-react';

/** Skeleton — match the shape of eventual content; never a full-page spinner (A10.1). */
export function Skeleton({ className }: { className?: string }) {
    return <div className={cn('skeleton', className)} aria-hidden="true" />;
}

/** First-use empty: teaches. Friendly, one value line, one primary CTA (A10.2). */
export function FirstUseEmpty({
    icon: Icon = Inbox,
    title,
    description,
    action,
    learnMore,
}: {
    icon?: React.ComponentType<{ className?: string }>;
    title: string;
    description: string;
    action?: React.ReactNode;
    learnMore?: string;
}) {
    return (
        <div className="flex flex-col items-center justify-center px-6 py-16 text-center">
            <div className="mb-4 flex size-12 items-center justify-center rounded-[var(--radius-card)] bg-accent-subtle">
                <Icon className="size-6 text-accent" />
            </div>
            <h3 className="text-base font-semibold text-primary">{title}</h3>
            <p className="mt-1 max-w-sm text-sm text-secondary">{description}</p>
            {action && <div className="mt-5">{action}</div>}
            {learnMore && (
                <a href={learnMore} className="mt-3 text-[13px] text-accent hover:underline">
                    Learn how
                </a>
            )}
        </div>
    );
}

/** Filtered-empty: reassures and exits (A10.2). */
export function FilteredEmpty({ onClear }: { onClear: () => void }) {
    return (
        <div className="flex flex-col items-center justify-center px-6 py-14 text-center">
            <SearchX className="mb-3 size-6 text-tertiary" />
            <p className="text-sm text-secondary">No results for these filters</p>
            <Button variant="ghost" size="sm" className="mt-3" onClick={onClear}>
                Clear filters
            </Button>
        </div>
    );
}

/** Page/section error: recovers (A10.3). */
export function ErrorState({
    title = "Couldn't load this",
    cause,
    onRetry,
}: {
    title?: string;
    cause?: string;
    onRetry?: () => void;
}) {
    return (
        <div className="flex flex-col items-center justify-center px-6 py-14 text-center">
            <AlertCircle className="mb-3 size-6 text-danger" />
            <h3 className="text-sm font-semibold text-primary">{title}</h3>
            {cause && <p className="mt-1 max-w-sm text-[13px] text-secondary">{cause}</p>}
            {onRetry && (
                <Button variant="secondary" size="sm" className="mt-4" onClick={onRetry}>
                    Retry
                </Button>
            )}
            <a href="#" className="mt-3 text-[12px] text-tertiary hover:underline">
                If this continues, check status
            </a>
        </div>
    );
}
