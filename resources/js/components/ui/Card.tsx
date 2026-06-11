import { cn } from '@/lib/utils';

interface CardProps extends React.HTMLAttributes<HTMLDivElement> {
    /** Adds a hover-lift + stronger shadow; use for clickable / stat tiles. */
    interactive?: boolean;
}

/** Border-led card with a soft resting elevation; opt into `interactive` to lift. */
export function Card({ className, interactive, ...props }: CardProps) {
    return (
        <div
            className={cn(
                'rounded-[var(--radius-card)] border border-default bg-surface shadow-[var(--shadow-xs)]',
                interactive && 'lift cursor-pointer',
                className,
            )}
            {...props}
        />
    );
}

export function CardHeader({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('px-5 pt-5 pb-3', className)} {...props} />;
}

export function CardBody({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('px-5 py-4', className)} {...props} />;
}
