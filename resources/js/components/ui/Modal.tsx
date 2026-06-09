import { useEffect, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { X } from 'lucide-react';
import { Button } from './Button';

interface ModalProps {
    open: boolean;
    onClose: () => void;
    title: string;
    children: ReactNode;
    footer?: ReactNode;
}

/** Modal with scrim, focus return, Esc to close (A6 / A12). */
export function Modal({ open, onClose, title, children, footer }: ModalProps) {
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
        <div className="fixed inset-0 z-[90] flex items-center justify-center p-4">
            <div
                className="absolute inset-0 bg-gray-900/40 animate-in"
                onClick={onClose}
                aria-hidden="true"
            />
            <div
                role="dialog"
                aria-modal="true"
                aria-label={title}
                className="animate-in relative w-full max-w-md rounded-[var(--radius-card)] border border-default bg-surface shadow-[var(--shadow-md)]"
            >
                <div className="flex items-center justify-between border-b border-default px-5 py-4">
                    <h2 className="text-base font-semibold text-primary">{title}</h2>
                    <button onClick={onClose} aria-label="Close" className="text-tertiary hover:text-primary">
                        <X className="size-5" />
                    </button>
                </div>
                <div className="px-5 py-4 text-sm text-secondary">{children}</div>
                {footer && (
                    <div className="flex justify-end gap-2 border-t border-default px-5 py-3">{footer}</div>
                )}
            </div>
        </div>,
        document.body,
    );
}

/** Scale-aware destructive confirm (A10.5): names consequence + scale; danger not default focus. */
export function ConfirmModal({
    open,
    onClose,
    onConfirm,
    title,
    consequence,
    confirmLabel = 'Delete',
}: {
    open: boolean;
    onClose: () => void;
    onConfirm: () => void;
    title: string;
    consequence: string;
    confirmLabel?: string;
}) {
    return (
        <Modal
            open={open}
            onClose={onClose}
            title={title}
            footer={
                <>
                    <Button variant="secondary" onClick={onClose} autoFocus>
                        Cancel
                    </Button>
                    <Button
                        variant="danger"
                        onClick={() => {
                            onConfirm();
                            onClose();
                        }}
                    >
                        {confirmLabel}
                    </Button>
                </>
            }
        >
            {consequence}
        </Modal>
    );
}
