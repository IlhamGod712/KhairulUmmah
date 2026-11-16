import type { UserRole } from './auth';

export interface PenggunaFilter {
    search?: string;
    role?: UserRole | '';
    page?: number;
    per_page?: number;
}

export interface Pengguna {
    id: number;
    name: string;
    email: string;
    no_hp: string | null;
    role: UserRole;
    role_label: string;
    email_verified_at: string | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface PenggunaUpdatePayload {
    name: string;
    email: string;
    no_hp: string | null;
    role: UserRole;
    password?: string;
}

export interface PenggunaListResponse {
    data: Pengguna[];
    meta?: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}
