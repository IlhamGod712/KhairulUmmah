import type { ReactNode } from 'react';

/**
 * Small yellow callout used to flag work that is intentionally not built yet,
 * so a placeholder screen is never mistaken for a finished feature.
 */
export function PhaseNotice({ phase, children }: { phase: string; children: ReactNode }) {
    return (
        <div className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <p className="font-semibold">{phase}</p>
            <div className="mt-1 leading-relaxed">{children}</div>
        </div>
    );
}
