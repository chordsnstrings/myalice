import { forwardRef, type InputHTMLAttributes, type TextareaHTMLAttributes, useId } from 'react';
import { cn } from '@/lib/utils';

const fieldBase =
    'w-full rounded-[var(--radius-control)] bg-surface border border-strong text-primary placeholder:text-tertiary ' +
    'transition-colors focus:border-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 ' +
    'disabled:opacity-50 disabled:cursor-not-allowed';

interface FieldWrapProps {
    label?: string;
    error?: string;
    hint?: string;
    htmlFor?: string;
    children: React.ReactNode;
}

export function Field({ label, error, hint, htmlFor, children }: FieldWrapProps) {
    return (
        <div className="space-y-1.5">
            {label && (
                <label htmlFor={htmlFor} className="block text-[13px] font-medium text-primary">
                    {label}
                </label>
            )}
            {children}
            {error ? (
                <p className="text-[12px] font-medium text-danger" role="alert">
                    {error}
                </p>
            ) : hint ? (
                <p className="text-[12px] text-tertiary">{hint}</p>
            ) : null}
        </div>
    );
}

interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
    label?: string;
    error?: string;
    hint?: string;
}

export const Input = forwardRef<HTMLInputElement, InputProps>(function Input(
    { label, error, hint, className, id, ...props },
    ref,
) {
    const auto = useId();
    const fieldId = id ?? auto;
    return (
        <Field label={label} error={error} hint={hint} htmlFor={fieldId}>
            <input
                ref={ref}
                id={fieldId}
                aria-invalid={!!error}
                className={cn(fieldBase, 'h-9 px-3 text-sm', error && 'border-danger', className)}
                {...props}
            />
        </Field>
    );
});

interface TextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
    label?: string;
    error?: string;
}

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(function Textarea(
    { label, error, className, id, ...props },
    ref,
) {
    const auto = useId();
    const fieldId = id ?? auto;
    return (
        <Field label={label} error={error} htmlFor={fieldId}>
            <textarea
                ref={ref}
                id={fieldId}
                aria-invalid={!!error}
                className={cn(fieldBase, 'px-3 py-2 text-sm resize-none', error && 'border-danger', className)}
                {...props}
            />
        </Field>
    );
});
