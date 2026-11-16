import type { JenisKelamin } from '@/types/auth';

export type SantriStatus = 'pendaftaran' | 'menunggu_pembayaran' | 'menunggu_verifikasi' | 'aktif' | 'ditolak' | 'dibatalkan';

export interface SantriWaliInfo {
    id: number;
    name: string;
    email: string;
    no_hp: string | null;
}

export interface SantriTahunAjaranInfo {
    id: number;
    nama: string;
    aktif: boolean | null;
}

export interface SantriAdmin {
    id: number;
    nis: string;
    nisn: string | null;
    nama_lengkap: string;
    jenis_kelamin: JenisKelamin;
    jenis_kelamin_label: string | null;
    tempat_lahir: string;
    tanggal_lahir: string | null;
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
    foto_path: string | null;
    status: SantriStatus;
    status_label: string | null;
    wali_id: number | null;
    tahun_ajaran_id: number;
    wali: SantriWaliInfo | null;
    tahun_ajaran: SantriTahunAjaranInfo | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface SantriAdminListResponse {
    items: SantriAdmin[];
    pagination: {
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
    };
}

export interface SantriAdminUpdatePayload {
    nama_lengkap?: string;
    jenis_kelamin?: JenisKelamin;
    tempat_lahir?: string;
    tanggal_lahir?: string;
    alamat?: string;
    kelurahan?: string | null;
    kecamatan?: string | null;
    kabupaten?: string | null;
    provinsi?: string | null;
    no_hp?: string | null;
    nama_ayah?: string | null;
    nama_ibu?: string | null;
    pekerjaan_ayah?: string | null;
    pekerjaan_ibu?: string | null;
    penghasilan_bulanan?: number | null;
    sekolah_asal?: string | null;
    nisn?: string | null;
}
