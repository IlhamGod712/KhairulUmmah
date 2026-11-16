/**
 * Must stay in sync with `backend/app/Enums/UserRole.php` and
 * `backend/app/Http/Resources/UserResource.php`.
 */
export type UserRole = 'wali' | 'admin';

export const USER_ROLE_LABELS: Record<UserRole, string> = {
    wali: 'Wali Santri',
    admin: 'Admin / Tata Usaha',
};

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    role_label: string;
    email_verified_at: string | null;
    created_at: string | null;
}

/**
 * Shape returned by every backend endpoint.
 */
export interface ApiEnvelope<TData> {
    success: boolean;
    message: string;
    data: TData;
}

/**
 * Mirrors `backend/app/Enums/JenisKelamin.php`. `label()` returns 'Laki-laki'
 * and 'Perempuan'.
 */
export type JenisKelamin = 'laki_laki' | 'perempuan';

export const JENIS_KELAMIN_LABELS: Record<JenisKelamin, string> = {
    laki_laki: 'Laki-laki',
    perempuan: 'Perempuan',
};

/**
 * Mirrors `backend/app/Http/Resources/SantriResource.php`.
 */
export interface SantriListItem {
    id: number;
    nis: string;
    nisn: string | null;
    nama_lengkap: string;
    jenis_kelamin: JenisKelamin;
    tempat_lahir: string;
    tanggal_lahir: string;
    status: string;
    status_label: string;
    tahun_ajaran: string | null;
}

/**
 * The identification a guardian types to find an existing, unlinked student.
 *
 * Mirrors `backend/app/Http/Requests/Wali/MatchSantriLamaRequest.php`. The
 * register call repeats the same three fields so the server can re-derive the
 * student deterministically; the shape has no `santri_id` and no `wali_id`,
 * so the client has nothing to point at another row or another owner even
 * before the server refuses it.
 */
export interface SantriLamaPayload {
    nis_atau_nisn: string;
    nama_lengkap: string;
    tanggal_lahir: string;
}

/**
 * Mirrors `backend/app/Http/Controllers/Api/Admin/AdminOverviewController.php`.
 */
export interface AdminOverview {
    tahun_ajaran_aktif: string | null;
    total_santri: number;
    total_pendaftaran: number;
    total_pembayaran: number;
}

/*
|--------------------------------------------------------------------------
| Wali
|--------------------------------------------------------------------------
*/

/**
 * Mirrors `backend/app/Enums/SantriStatus.php`. Every case is a key in the
 * dashboard's `per_status` map, including the ones that count zero, so the SPA
 * can render a fixed set of tiles.
 */
export type SantriStatus =
    | 'pendaftaran'
    | 'verifikasi'
    | 'menunggu_pembayaran'
    | 'aktif'
    | 'ditolak'
    | 'dibatalkan';

export const SANTRI_STATUS_LABELS: Record<SantriStatus, string> = {
    pendaftaran: 'Pendaftaran',
    verifikasi: 'Verifikasi Berkas',
    menunggu_pembayaran: 'Menunggu Pembayaran',
    aktif: 'Aktif',
    ditolak: 'Ditolak',
    dibatalkan: 'Dibatalkan',
};

export type StatusPendaftaran = 'diajukan' | 'diverifikasi' | 'diterima' | 'ditolak';

export const STATUS_PENDAFTARAN_LABELS: Record<StatusPendaftaran, string> = {
    diajukan: 'Diajukan',
    diverifikasi: 'Sedang Diverifikasi',
    diterima: 'Diterima',
    ditolak: 'Ditolak',
};

/**
 * Mirrors `backend/app/Enums/StatusPembayaran.php`. Phase 6 added the two states
 * of the accept -> pay -> activate pipeline: the guardian has uploaded proof
 * (`menunggu_verifikasi`), and the admin has turned it down (`ditolak`).
 */
export type StatusPembayaran = 'belum' | 'menunggu_verifikasi' | 'lunas' | 'ditolak';

export const STATUS_PEMBAYARAN_LABELS: Record<StatusPembayaran, string> = {
    belum: 'Belum Bayar',
    menunggu_verifikasi: 'Menunggu Verifikasi',
    lunas: 'Lunas',
    ditolak: 'Ditolak',
};

/**
 * Mirrors `backend/app/Http/Resources/WaliProfileResource.php`.
 *
 * `email` and `role` are here because the API returns them, not because the
 * profile form can change them: the only editable fields are `name` and `no_hp`.
 */
export interface WaliProfile {
    id: number;
    name: string;
    email: string;
    no_hp: string | null;
    role: UserRole;
    role_label: string;
    email_verified: boolean;
    email_verified_at: string | null;
    created_at: string | null;
}

/**
 * Mirrors `GET /api/wali/dashboard`.
 *
 * The money fields are strings on purpose. The API returns
 * `decimal(15,2)` totals as fixed two-decimal strings, and passing them through
 * `Number()` in the SPA would reintroduce the floating point rounding the
 * backend avoided.
 */
export interface WaliDashboard {
    wali: WaliProfile;
    ringkasan_santri: {
        total: number;
        per_status: Record<SantriStatus, number>;
    };
    ringkasan_pendaftaran: {
        total: number;
        per_status: Record<StatusPendaftaran, number>;
    };
    ringkasan_tagihan: {
        total_tagihan: string;
        total_lunas: string;
        per_status: Record<StatusPembayaran, number>;
    };
}
