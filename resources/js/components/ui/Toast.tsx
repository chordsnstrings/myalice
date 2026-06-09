import { createContext, useCallback, useContext, useState, type ReactNode } from 'react';
import { CheckCircle2, AlertTriangle, X, Info } from 'lucide-react';
import { cn } from '@/lib/utils';

type ToastTone = 'success' | 'error' | 'info';
interface Toast {
    id: number;
    tone: ToastTone;
    message: string;
    action?: { label: string; onClick: () => void };
}

interface ToastApi {
    toast: (message: string, opts?: { tone?: ToastTone; action?: Toast['action'] }) => void;
}

const ToastCtx = createContext<ToastApi>({ toast: () => {} });
export const useToast = () => useContext(ToastCtx);

const icons = { success: CheckCircle2, error: AlertTriangle, info: Info };
const toneColor = { success: 'text-success', error: 'text-danger', info: 'text-info' };

/** Bottom-start, stacked, auto-dismiss 5s, action + close (A9 / A10.4). */
export function ToastProvider({ children }: { children: ReactNode }) {
    const [toasts, setToasts] = useState<Toast[]>([]);

    const remove = useCallback((id: number) => setToasts((t) => t.filter((x) => x.id !== id)), []);

    const toast = useCallback<ToastApi['toast']>(
        (message, opts) => {
            const id = Date.now() + Math.random();
            const tone = opts?.tone ?? 'info';
            setToasts((t) => [...t, { id, tone, message, action: opts?.action }]);
            setTimeout(() => remove(id), 5000);
        },
        [remove],
    );

    return (
        <ToastCtx.Provider value={{ toast }}>
            {children}
            <div
                className="fixed bottom-4 start-4 z-[100] flex w-[340px] max-w-[calc(100vw-2rem)] flex-col gap-2"
                role="region"
                aria-live="polite"
            >
                {toasts.map((t) => {
                    const Icon = icons[t.tone];
                    return (
                        <div
                            key={t.id}
                            role="alert"
                            className={cn(
                                'animate-in flex items-start gap-3 rounded-[var(--radius-card)] border border-default',
                                'bg-surface p-3 shadow-[var(--shadow-sm)]',
                            )}
                        >
                            <Icon className={cn('mt-0.5 size-4 shrink-0', toneColor[t.tone])} />
                            <p className="flex-1 text-[13px] text-primary">{t.message}</p>
                            {t.action && (
                                <button
                                    onClick={() => {
                                        t.action!.onClick();
                                        remove(t.id);
                                    }}
                                    className="text-[13px] font-medium text-accent hover:underline"
                                >
                                    {t.action.label}
                                </button>
                            )}
                            <button
                                onClick={() => remove(t.id)}
                                aria-label="Dismiss"
                                className="text-tertiary hover:text-primary"
                            >
                                <X className="size-4" />
                            </button>
                        </div>
                    );
                })}
            </div>
        </ToastCtx.Provider>
    );
}
