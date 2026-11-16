/**
 * The financial report row for `GET /api/admin/laporan`.
 *
 * Mirrors `AdminLaporanKeuanganController` + `LaporanKeuanganResource`.
 * `jumlah` is a fixed two-decimal money string on purpose, never a float.
 */
export type LaporanJenisPembayaran = 'pendaftaran' | 'spp';

export type LaporanStatusPembayaran = 'belum' | 'menunggu_verifikasi' | 'lunas' | 'ditolak';

export interface LaporanItem {
    id: number;
    tanggal_transaksi: string | null;
    jenis_pembayaran: LaporanJenisPembayaran;
    jenis_pembayaran_label: string;
    periode: string | null;
    periode_label: string | null;
    santri_id: number | null;
    nama_santri: string | null;
    nis: string | null;
    jumlah: string;
    status: LaporanStatusPembayaran;
    status_label: string;
    metode: string | null;
    metode_label: string | null;
    keterangan: string | null;
    divergisisasi_pada: string | null;
}

export interface LaporanRingkasan {
    total_transaksi: number;
    total_nominal: string;
    terverifikasi: { jumlah: number; nominal: string };
    menunggu_verifikasi: { jumlah: number; nominal: string };
    ditolak: { jumlah: number; nominal: string };
}

export interface LaporanPagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface LaporanReport {
    items: LaporanItem[];
    pagination: LaporanPagination;
    ringkasan: LaporanRingkasan;
}

export interface LaporanFilter {
    tanggal_awal?: string;
    tanggal_akhir?: string;
    jenis_pembayaran?: LaporanJenisPembayaran;
    status?: LaporanStatusPembayaran;
    per_page?: number;
}
