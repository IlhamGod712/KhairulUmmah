import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

interface CardProps {
    title?: string;
    description?: string;
    children?: ReactNode;
    className?: string;
}

export function Card({ title, description, children, className }: CardProps) {
    return (
        <section
            className={cn(
                'rounded-xl border border-slate-200 bg-white p-6 shadow-sm',
                className,
            )}
        >
            {title !== undefined && (
                <h2 className="text-lg font-semibold text-slate-900">{title}</h2>
            )}
            {description !== undefined && (
                <p className="mt-1 text-sm text-slate-500">{description}</p>
            )}
            {children !== undefined && <div className="mt-4">{children}</div>}
        </section>
    );
}
