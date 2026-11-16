import type { SantriStatus, StatusPendaftaran, StatusPembayaran } from '@/types/auth';
import type { StatusDokumen } from '@/types/pembayaran';
import { cn } from '@/lib/utils';

/**
 * Colour for one state, shared by every badge so the same word is never two
 * different colours in two different tables.
 *
 * `diajukan` and `diverifikasi` are both "waiting on the office" and share one
 * amber. The pipeline is deliberately not a gradient of three increasingly warm
 * colours: a guardian reading this table cares about two things — has anybody
 * looked at it, and is it finished — and a second shade of amber would suggest a
 * distinction the business has not actually drawn.
 */
const WARNA: Record<StatusPendaftaran | StatusPembayaran | SantriStatus | StatusDokumen, string> = {
    // Pendaftaran
    diajukan: 'bg-amber-50 text-amber-700 ring-amber-200',
    diverifikasi: 'bg-amber-50 text-amber-700 ring-amber-200',
    diterima: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    ditolak: 'bg-rose-50 text-rose-700 ring-rose-200',
    // Pembayaran
    belum: 'bg-amber-50 text-amber-700 ring-amber-200',
    menunggu_verifikasi: 'bg-sky-50 text-sky-700 ring-sky-200',
    lunas: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    // Dokumen
    menunggu: 'bg-sky-50 text-sky-700 ring-sky-200',
    // Santri
    pendaftaran: 'bg-amber-50 text-amber-700 ring-amber-200',
    verifikasi: 'bg-sky-50 text-sky-700 ring-sky-200',
    menunggu_pembayaran: 'bg-indigo-50 text-indigo-700 ring-indigo-200',
    aktif: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    dibatalkan: 'bg-slate-100 text-slate-600 ring-slate-200',
};

interface StatusBadgeProps {
    status: StatusPendaftaran | StatusPembayaran | SantriStatus | StatusDokumen;
    label: string;
    className?: string;
}

/**
 * A small pill carrying a status and its server-provided label.
 *
 * The label is passed in rather than looked up here, because the backend owns
 * the wording: `StatusPendaftaran::label()` is the single place "Sedang
 * Diverifikasi" is decided, and a second copy of that map in the SPA would be
 * one more thing to forget to update.
 */
export function StatusBadge({ status, label, className }: StatusBadgeProps) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset',
                WARNA[status],
                className,
            )}
        >
            {label}
        </span>
    );
}
