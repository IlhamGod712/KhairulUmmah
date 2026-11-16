import type { TextareaHTMLAttributes } from 'react';

import { cn } from '@/lib/utils';

interface TextAreaFieldProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
    label: string;
    /** Server-side validation message for this field, if any. */
    error?: string;
    /** Static help text, shown when there is no error to show instead. */
    hint?: string;
}

/**
 * Label + textarea + validation message.
 *
 * The sibling of `FormField` and `SelectField`, with the same accessibility
 * wiring. Addresses are the one field in the registration form that genuinely
 * needs more than one line, and a one-line input is a poor way to collect one.
 */
export function TextAreaField({ label, error, hint, id, className, ...textareaProps }: TextAreaFieldProps) {
    const textareaId = id ?? `textarea-${textareaProps.name ?? label}`;
    const errorId = `${textareaId}-error`;
    const hintId = `${textareaId}-hint`;

    const describedBy = error !== undefined ? errorId : hint !== undefined ? hintId : undefined;

    return (
        <div>
            <label htmlFor={textareaId} className="block text-sm font-medium text-slate-700">
                {label}
            </label>
            <textarea
                {...textareaProps}
                id={textareaId}
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
