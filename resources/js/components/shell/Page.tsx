import { type ReactNode } from 'react';
import { cn } from '@/lib/utils';

/** Scrollable content region inside the AppShell, with an optional header row. */
export function Page({
    title,
    description,
    actions,
    children,
    width = 'wide',
}: {
    title?: string;
    description?: string;
    actions?: ReactNode;
    children: ReactNode;
    width?: 'wide' | 'narrow';
}) {
    return (
        <div className="h-full overflow-y-auto">
            <div className={cn('mx-auto px-6 py-6', width === 'narrow' ? 'max-w-3xl' : 'max-w-[1200px]')}>
                {(title || actions) && (
                    <div className="mb-5 flex items-start justify-between gap-4">
                        <div>
                            {title && <h2 className="text-xl font-semibold tracking-tight">{title}</h2>}
                            {description && <p className="mt-0.5 text-[13px] text-secondary">{description}</p>}
                        </div>
                        {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
                    </div>
                )}
                {children}
            </div>
        </div>
    );
}
