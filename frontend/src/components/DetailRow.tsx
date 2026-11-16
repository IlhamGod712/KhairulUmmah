import type { ReactNode } from 'react';

/**
 * A label/value row for the detail views.
 *
 * Shared rather than re-declared per view: `PendaftaranDetailView`,
 * `PembayaranDetailView`, and the SPP detail each render the same definition
 * list, and a private copy per file had already drifted into two identical
 * copies before Phase 8 needed a third. Collapsing to a dash on `null`,
 * `undefined`, or `''` is the one behaviour they all relied on.
 */
export function Baris({ label, value }: { label: string; value: ReactNode }) {
    const kosong = value === null || value === undefined || value === '';

    return (
        <div className="flex flex-col gap-0.5 border-b border-slate-100 py-2 last:border-b-0 sm:flex-row sm:gap-4">
            <dt className="text-sm text-slate-500 sm:w-56 sm:shrink-0">{label}</dt>
            <dd className="text-sm text-slate-800">{kosong ? <span className="text-slate-400">—</span> : value}</dd>
        </div>
    );
}

/**
 * A byte count as a human-readable size, or `null` when there is no file.
 */
export function formatBytes(byte: number | null): string | null {
    if (byte === null) {
        return null;
    }

    if (byte < 1024) {
        return `${byte} B`;
    }

    if (byte < 1024 * 1024) {
        return `${(byte / 1024).toFixed(1)} KB`;
    }

    return `${(byte / (1024 * 1024)).toFixed(1)} MB`;
}
