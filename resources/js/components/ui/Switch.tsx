import { cn } from '@/lib/utils';

/** Accessible toggle switch with default/hover/focus/disabled (A9). */
export function Switch({
    checked,
    onChange,
    label,
    disabled,
}: {
    checked: boolean;
    onChange: (v: boolean) => void;
    label?: string;
    disabled?: boolean;
}) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            aria-label={label}
            disabled={disabled}
            onClick={() => onChange(!checked)}
            className={cn(
                'relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors',
                'disabled:opacity-50 disabled:cursor-not-allowed',
                checked ? 'bg-accent' : 'bg-strong',
            )}
        >
            <span
                className={cn(
                    'inline-block size-4 transform rounded-full bg-white transition-transform',
                    checked ? 'translate-x-[18px]' : 'translate-x-0.5',
                )}
            />
        </button>
    );
}
