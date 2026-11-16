/**
 * Pembayaran — a payment record, and the Phase 6 registration-fee lane.
 *
 * Mirrors `backend/app/Http/Resources/PembayaranResource.php` and the enums it
 * reads. The list resource and the detail resource are the same shape, so one
 * interface serves both the wali and admin payment surfaces.
 */

import type { StatusPembayaran, StatusPendaftaran } from '@/types/auth';
import type { WaliDetail } from '@/types/pendaftaran';

export type MetodePembayaran = 'dana' | 'transfer_bank' | 'tunai';

export const METODE_PEMBAYARAN_LABELS: Record<MetodePembayaran, string> = {
    dana: 'DANA',
    transfer_bank: 'Transfer Bank',
    tunai: 'Tunai',
};

/**
 * Mirrors `backend/app/Enums/JenisPembayaran.php`. The discriminator Phase 6
 * added so a registration fee can be told apart from an SPP instalment.
 */
export type JenisPembayaran = 'pendaftaran' | 'spp';

export const JENIS_PEMBAYARAN_LABELS: Record<JenisPembayaran, string> = {
    pendaftaran: 'Biaya Pendaftaran',
    spp: 'SPP Bulanan',
};

/**
 * The review result of an uploaded document
 * (`backend/app/Enums/StatusDokumen.php`). No mirror existed before Phase 6
 * because no frontend rendered a document row yet; the payment proof is the
 * first one.
 */
export type StatusDokumen = 'menunggu' | 'diterima' | 'ditolak';

export const STATUS_DOKUMEN_LABELS: Record<StatusDokumen, string> = {
    menunggu: 'Menunggu Verifikasi',
    diterima: 'Diterima',
    ditolak: 'Ditolak',
};

/** The uploaded proof of a fee, and where to stream it. */
export interface BuktiPembayaran {
    id: number;
    nama_asli: string;
    mime_type: string | null;
    ukuran_bytes: number | null;
    status: StatusDokumen;
    status_label: string;
    catatan: string | null;
    url: string | null;
}

/**
 * The registration-fee block on a pendaftaran detail
 * (`PendaftaranDetailResource`). Unlike the list's `ringkasan_pembayaran`, this
 * is the honest "is the fee paid" answer: it points at the single fee for this
 * registration.
 */
export interface BiayaPendaftaran {
    id: number;
    jumlah: string;
    status: StatusPembayaran;
    status_label: string;
    bukti: BuktiPembayaran | null;
}

/**
 * One payment row on the wali and admin payment surfaces
 * (`PembayaranResource`). `jumlah` is a fixed two-decimal money string on
 * purpose, never a float.
 */
export interface PembayaranListItem {
    id: number;
    pendaftaran_id: number | null;
    jenis_pembayaran: JenisPembayaran;
    jenis_pembayaran_label: string;
    jumlah: string;
    status: StatusPembayaran;
    status_label: string;
    referensi: string | null;
    tanggal_bayar: string | null;
    keterangan: string | null;
    metode: MetodePembayaran | null;
    metode_label: string | null;
    santri_id: number;
    nama_santri: string;
    tahun_ajaran: string;
    nomor_pendaftaran: string | null;
    pendaftaran_status: StatusPendaftaran | null;
    pendaftaran_status_label: string | null;
    wali: WaliDetail | null;
    bukti: BuktiPembayaran | null;
}

/** What `PUT /api/admin/pembayaran/{id}/status` accepts. */
export interface StatusPembayaranPayload {
    status: 'lunas' | 'ditolak';
    catatan?: string | null;
}

/** What `PUT /api/admin/pendaftaran/{id}/status` accepts. */
export interface StatusPendaftaranPayload {
    status: 'diterima' | 'ditolak';
    alasan_ditolak?: string | null;
}