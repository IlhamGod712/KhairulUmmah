/**
 * Pendaftaran — the guardian's submission of a new student.
 *
 * Mirrors `backend/app/Http/Resources/PendaftaranResource.php` and
 * `PendaftaranDetailResource.php`, and
 * `backend/app/Http/Requests/Wali/StorePendaftaranRequest.php`.
 */

import type { JenisKelamin, SantriStatus, StatusPendaftaran, StatusPembayaran } from '@/types/auth';
import type { BiayaPendaftaran } from '@/types/pembayaran';

/**
 * What `POST /api/wali/pendaftaran` accepts.
 *
 * Every field here maps to a column on `santris` that the guardian may fill in.
 * Note what is absent, and stay that way: there is no `wali_id`, no `user_id`,
 * no `role`, no `status`, no `nis`, and no `tahun_ajaran_id`. The server derives
 * the guardian from the session, generates the numbers, and picks the academic
 * year that is currently in force. Adding any of them to this interface would
 * be asking the server for something it already refuses to take.
 */
export interface PendaftaranPayload {
    nama_lengkap: string;
    nisn: string | null;
    jenis_kelamin: JenisKelamin;
    tempat_lahir: string;
    tanggal_lahir: string;
    alamat: string;
    kelurahan: string | null;
    kecamatan: string | null;
    kabupaten: string | null;
    provinsi: string | null;
    no_hp: string | null;
    nama_ayah: string | null;
    nama_ibu: string | null;
    pekerjaan_ayah: string | null;
    pekerjaan_ibu: string | null;
    penghasilan_bulanan: number | null;
    sekolah_asal: string | null;
}

/**
 * `pembayarans` has no `jenis` column, so nothing in the schema tells a
 * registration fee apart from an SPP instalment. This is therefore a position
 * over the student's payments for the registration's academic year, and it is
 * deliberately not labelled as "the registration fee is paid" — that is a Phase
 * 6 claim.
 */
export interface RingkasanPembayaran {
    total: number;
    lunas: number;
    status: StatusPembayaran;
    status_label: string;
}

/** The guardian on a list row: identified, not described. */
export interface WaliRingkas {
    id: number;
    name: string;
}

/** The guardian on a detail row, including how to reach them. */
export interface WaliDetail extends WaliRingkas {
    email: string;
    no_hp: string | null;
}

/** Fields both the list and the detail carry. */
export interface PendaftaranBase {
    id: number;
    nomor_pendaftaran: string;
    tanggal_daftar: string;
    status: StatusPendaftaran;
    status_label: string;
    santri_id: number;
    nama_santri: string;
    jenis_kelamin: JenisKelamin;
    santri_status: SantriStatus;
    santri_status_label: string;
    tahun_ajaran: string | null;
    ringkasan_pembayaran: RingkasanPembayaran;
}

export interface PendaftaranListItem extends PendaftaranBase {
    wali: WaliRingkas | null;
}

/** Everything the guardian submitted, read back. */
export interface CalonSantri {
    id: number;
    nis: string;
    nisn: string | null;
    nama_lengkap: string;
    jenis_kelamin: JenisKelamin;
    jenis_kelamin_label: string;
    tempat_lahir: string;
    tanggal_lahir: string;
    alamat: string;
    kelurahan: string | null;
    kecamatan: string | null;
    kabupaten: string | null;
    provinsi: string | null;
    no_hp: string | null;
    nama_ayah: string | null;
    nama_ibu: string | null;
    pekerjaan_ayah: string | null;
    pekerjaan_ibu: string | null;
    penghasilan_bulanan: number | null;
    sekolah_asal: string | null;
    status: SantriStatus;
    status_label: string;
}

export interface PendaftaranDetail extends PendaftaranBase {
    catatan: string | null;
    /** Set only when the registration was rejected; the guardian reads it back. */
    alasan_ditolak: string | null;
    diproses_oleh: string | null;
    /** The registration-fee for this registration, or null before the admin decides. */
    biaya_pendaftaran: BiayaPendaftaran | null;
    calon_santri: CalonSantri;
    wali: WaliDetail | null;
}
