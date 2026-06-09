import { forwardRef, type ButtonHTMLAttributes } from 'react';
import { Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';
type Size = 'sm' | 'md';

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: Variant;
    size?: Size;
    loading?: boolean;
}

const variants: Record<Variant, string> = {
    primary:
        'bg-accent text-accent-contrast hover:bg-accent-hover active:brightness-95 border border-transparent',
    secondary:
        'bg-surface text-primary border border-strong hover:bg-surface-hover active:bg-surface-2',
    ghost: 'bg-transparent text-secondary hover:bg-surface-hover hover:text-primary border border-transparent',
    danger: 'bg-danger text-white hover:brightness-110 active:brightness-95 border border-transparent',
};

const sizes: Record<Size, string> = {
    sm: 'h-8 px-3 text-[13px] gap-1.5',
    md: 'h-9 px-4 text-sm gap-2',
};

/** Primary/secondary/ghost/danger button with loading + disabled states (A9). */
export const Button = forwardRef<HTMLButtonElement, ButtonProps>(function Button(
    { variant = 'primary', size = 'md', loading, disabled, className, children, ...props },
    ref,
) {
    return (
        <button
            ref={ref}
            disabled={disabled || loading}
            className={cn(
                'inline-flex items-center justify-center rounded-[var(--radius-control)] font-medium',
                'transition-[colors,transform] duration-150 select-none whitespace-nowrap',
                'active:scale-[0.97] disabled:opacity-50 disabled:pointer-events-none disabled:active:scale-100',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 focus-visible:ring-offset-1 focus-visible:ring-offset-canvas',
                variants[variant],
                sizes[size],
                className,
            )}
            {...props}
        >
            {loading && <Loader2 className="size-4 animate-spin" />}
            {children}
        </button>
    );
});
