import type { SelectHTMLAttributes } from 'react';

import { cn } from '@/lib/utils';

interface SelectFieldProps extends SelectHTMLAttributes<HTMLSelectElement> {
    label: string;
    /** Server-side validation message for this field, if any. */
    error?: string;
    /** Static help text, shown when there is no error to show instead. */
    hint?: string;
    /** Shown on the option whose value is `''`, which means "not chosen yet". */
    placeholder?: string;
}

/**
 * Label + select + validation message.
 *
 * Deliberately a sibling of `FormField` rather than a union inside it: the two
 * share the `aria-invalid` / `aria-describedby` wiring, and duplicating that in
 * a component that then had to branch on element type would be worse than two
 * small files that say what they are.
 */
export function SelectField({
    label,
    error,
    hint,
    placeholder,
    id,
    className,
    children,
    ...selectProps
}: SelectFieldProps) {
    const selectId = id ?? `select-${selectProps.name ?? label}`;
    const errorId = `${selectId}-error`;
    const hintId = `${selectId}-hint`;

    const describedBy = error !== undefined ? errorId : hint !== undefined ? hintId : undefined;

    return (
        <div>
            <label htmlFor={selectId} className="block text-sm font-medium text-slate-700">
                {label}
            </label>
            <select
                {...selectProps}
                id={selectId}
                aria-invalid={error !== undefined}
                aria-describedby={describedBy}
                className={cn(
                    'mt-1 w-full rounded-md border bg-white px-3 py-2 text-sm outline-none focus:ring-2 disabled:bg-slate-50',
                    error !== undefined
                        ? 'border-red-400 focus:border-red-500 focus:ring-red-100'
                        : 'border-slate-300 focus:border-brand-500 focus:ring-brand-200',
                    className,
                )}
            >
                {placeholder !== undefined && (
                    <option value="">{placeholder}</option>
                )}
                {children}
            </select>
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
