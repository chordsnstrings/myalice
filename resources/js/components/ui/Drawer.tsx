import { useEffect, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { X } from 'lucide-react';

/** Right-side slide-over drawer (A9). Esc + scrim to close; focus returns. */
export function Drawer({
    open,
    onClose,
    title,
    children,
    footer,
}: {
    open: boolean;
    onClose: () => void;
    title: string;
    children: ReactNode;
    footer?: ReactNode;
}) {
    useEffect(() => {
        if (!open) return;
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
        document.addEventListener('keydown', onKey);
        document.body.style.overflow = 'hidden';
        return () => {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = '';
        };
    }, [open, onClose]);

    if (!open) return null;

    return createPortal(
        <div className="fixed inset-0 z-[90]">
            <div className="absolute inset-0 bg-gray-900/40 animate-in" onClick={onClose} aria-hidden="true" />
            <div
                role="dialog"
                aria-modal="true"
                aria-label={title}
                className="absolute end-0 top-0 flex h-full w-full max-w-md flex-col border-s border-default bg-surface shadow-[var(--shadow-md)]"
                style={{ animation: 'fade-in 0.2s var(--ease-out)' }}
            >
                <div className="flex items-center justify-between border-b border-default px-5 py-4">
                    <h2 className="text-base font-semibold text-primary">{title}</h2>
                    <button onClick={onClose} aria-label="Close" className="text-tertiary hover:text-primary">
                        <X className="size-5" />
                    </button>
                </div>
                <div className="min-h-0 flex-1 overflow-y-auto px-5 py-4">{children}</div>
                {footer && <div className="flex justify-end gap-2 border-t border-default px-5 py-3">{footer}</div>}
            </div>
        </div>,
        document.body,
    );
}
