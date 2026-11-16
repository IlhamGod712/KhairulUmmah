import type { InputHTMLAttributes } from 'react';

import { cn } from '@/lib/utils';

interface FormFieldProps extends InputHTMLAttributes<HTMLInputElement> {
    label: string;
    /** Server-side validation message for this field, if any. */
    error?: string;
    /** Static help text, shown when there is no error to show instead. */
    hint?: string;
}

/**
 * Label + input + validation message. The error is wired with
 * `aria-describedby`/`aria-invalid` so a screen reader announces it.
 */
export function FormField({ label, error, hint, id, className, ...inputProps }: FormFieldProps) {
    const inputId = id ?? `field-${inputProps.name ?? label}`;
    const errorId = `${inputId}-error`;
    const hintId = `${inputId}-hint`;

    // The hint is dropped once an error is showing, so the two are never
    // announced together and the reason for the rejection is the last thing a
    // screen reader reaches.
    const describedBy = error !== undefined ? errorId : hint !== undefined ? hintId : undefined;

    return (
        <div>
            <label htmlFor={inputId} className="block text-sm font-medium text-slate-700">
                {label}
            </label>
            <input
                {...inputProps}
                id={inputId}
                aria-invalid={error !== undefined}
                aria-describedby={describedBy}
                className={cn(
                    'mt-1 w-full rounded-md border px-3 py-2 text-sm outline-none focus:ring-2 disabled:bg-slate-50',
                    error !== undefined
                        ? 'border-red-400 focus:border-red-500 focus:ring-red-100'
                        : 'border-slate-300 focus:border-brand-500 focus:ring-brand-200',
                    className,
                )}
            />
            {error !== undefined ? (
                <p id={errorId} className="mt-1 text-xs text-red-600">
                    {error}
                </p>
            ) : hint !== undefined ? (
                <p id={hintId} className="mt-1 text-xs text-slate-500">
                    {hint}
                </p>
            ) : null}
        </div>
    );
}
