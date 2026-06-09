import { useState, type ReactNode } from 'react';
import { cn } from '@/lib/utils';

/** Lightweight CSS-positioned tooltip; icon-only buttons require one (A7). */
export function Tooltip({
    label,
    children,
    side = 'top',
}: {
    label: string;
    children: ReactNode;
    side?: 'top' | 'bottom' | 'right';
}) {
    const [open, setOpen] = useState(false);
    const pos =
        side === 'top'
            ? 'bottom-full mb-1.5 start-1/2 -translate-x-1/2'
            : side === 'bottom'
              ? 'top-full mt-1.5 start-1/2 -translate-x-1/2'
              : 'start-full ms-1.5 top-1/2 -translate-y-1/2';
    return (
        <span
            className="relative inline-flex"
            onMouseEnter={() => setOpen(true)}
            onMouseLeave={() => setOpen(false)}
            onFocus={() => setOpen(true)}
            onBlur={() => setOpen(false)}
        >
            {children}
            {open && (
                <span
                    role="tooltip"
                    className={cn(
                        'pointer-events-none absolute z-50 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1',
                        'text-[12px] font-medium text-white shadow-[var(--shadow-sm)] dark:bg-gray-800',
                        pos,
                    )}
                >
                    {label}
                </span>
            )}
        </span>
    );
}
