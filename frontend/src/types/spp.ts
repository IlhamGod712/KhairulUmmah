/**
 * Tagihan SPP — the Phase 8 monthly-fee lane.
 *
 * Mirrors `backend/app/Http/Resources/TagihanSppResource.php` and the enums it
 * reads. An SPP invoice is a `pembayarans` row (`jenis_pembayaran = 'spp'` with a
 * `periode`), not a second kind of row, so `status`, `jumlah`, and `metode` are
 * the same shapes the Phase 6 payment types already use.
 */

import type { SantriStatus, StatusPembayaran } from '@/types/auth';
import type { MetodePembayaran } from '@/types/pembayaran';

/**
 * The proof attached to one SPP invoice.
 *
 * Unlike the registration fee's proof this is not a `dokumen` row and has no
 * review status of its own: whether the invoice is `menunggu_verifikasi`,
 * `lunas`, or `ditolak` *is* the review state, so there is no second badge to
 * keep in sync. `url` is relative to the API origin; run it through `apiUrl`.
 */
export interface BuktiSpp {
    nama_asli: string;
    mime_type: string | null;
    ukuran_bytes: number | null;
    url: string | null;
}

/** The guardian of the billed student; only ever filled for an admin caller. */
export interface WaliTagihan {
    id: number;
    name: string;
    email: string;
    no_hp: string | null;
}

/**
 * One monthly SPP invoice.
 *
 * `periode` and `periode_label` are non-null even though the resource types them
 * as nullable, because the `tagihanSpp` scope that every SPP surface reads
 * through requires `periode IS NOT NULL` — a row without a period is the
 * Phase 6 default, not an invoice, and never reaches these pages.
 */
export interface TagihanSpp {
    id: number;
    jenis_pembayaran: 'spp';
    jenis_pembayaran_label: string;

    /** The first day of the billed month, e.g. `2026-07-01`. */
    periode: string;
    /** The printed month, e.g. `Juli 2026`. */
    periode_label: string;
    jatuh_tempo: string | null;

    /** A fixed two-decimal money string, never a float. */
    jumlah: string;
    status: StatusPembayaran;
    status_label: string;
    metode: MetodePembayaran | null;
    metode_label: string | null;
    tanggal_bayar: string | null;
    referensi: string | null;
    keterangan: string | null;
    diverifikasi_pada: string | null;

    santri_id: number;
    nama_santri: string;
    nis: string;
    status_santri: SantriStatus;
    status_santri_label: string;
    tahun_ajaran_id: number;
    tahun_ajaran: string;

    wali: WaliTagihan | null;
    bukti: BuktiSpp | null;
}

/** One option in the admin's academic-year filter. */
export interface TahunAjaranOption {
    id: number;
    nama: string;
    is_active: boolean;
}

/** What `POST /api/admin/spp/generate` returns. */
export interface HasilGenerateSpp {
    periode: string;
    periode_label: string;
    nominal: string;
    jatuh_tempo: string;
    total_santri_aktif: number;
    dibuat: number;
    dilewati: number;
}

/**
 * What `POST /api/admin/spp/generate` accepts.
 *
 * There is deliberately no `jumlah`: the price is the school's
 * `biaya_spp_bulanan` setting read by the server. The shape has nowhere to put
 * one, so the client cannot invent what a student owes.
 */
export interface GenerateSppPayload {
    periode: string;
    tahun_ajaran_id?: number;
}

/** The admin's verdict on an invoice's proof. */
export interface VerdictSppPayload {
    status: 'lunas' | 'ditolak';
    catatan?: string | null;
}

/** The optional filters on the admin SPP queue. */
export interface FilterSpp {
    tahun_ajaran_id?: number;
    periode?: string;
    status?: StatusPembayaran;
}
