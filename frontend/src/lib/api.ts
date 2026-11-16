import axios, { AxiosError } from 'axios';

import type {
    AdminOverview,
    ApiEnvelope,
    AuthUser,
    SantriLamaPayload,
    SantriListItem,
    WaliDashboard,
    WaliProfile,
} from '@/types/auth';
import type { PendaftaranDetail, PendaftaranListItem, PendaftaranPayload } from '@/types/pendaftaran';
import type { PembayaranListItem, StatusPembayaranPayload, StatusPendaftaranPayload } from '@/types/pembayaran';
import type { SantriAdmin, SantriAdminListResponse, SantriAdminUpdatePayload } from '@/types/santri';
import type { LaporanFilter, LaporanReport } from '@/types/laporan';
import type {
    FilterSpp,
    GenerateSppPayload,
    HasilGenerateSpp,
    TagihanSpp,
    TahunAjaranOption,
    VerdictSppPayload,
} from '@/types/spp';
import type {
    Pengguna,
    PenggunaFilter,
    PenggunaListResponse,
    PenggunaUpdatePayload,
} from '@/types/pengguna';
import type { AuditLog, AuditLogListResponse, AuditTrailFilter } from '@/types/audit';

export interface HealthPayload {
    service: string;
    environment: string;
    api_prefix: string;
}

/**
 * Resolve a relative API path (as sent inside a resource, e.g. a proof's
 * `url`) against the API's own base. The SPA and the API are different
 * origins, so a bare `/api/...` would resolve against the Vite origin and hit
 * the wrong server.
 */
export function apiUrl(path: string): string {
    const base: string = import.meta.env.VITE_API_URL ?? 'http://localhost:8000';
    return `${base}${path}`;
}

export const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000',
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
    },
    // Sanctum's SPA mode authenticates with cookies, so the session cookie has
    // to travel on every request. `withCredentials` is what makes that legal
    // cross-origin.
    withCredentials: true,
    // The SPA (5173) and the API (8000) are different origins, and Axios only
    // attaches the XSRF header for same-origin requests unless this is set.
    // Cookies ignore the port, so localhost:5173 can read the XSRF-TOKEN cookie
    // that the API set. The session cookie itself is httpOnly and stays
    // unreadable from JavaScript, which is the whole point of this arrangement.
    withXSRFToken: true,
    xsrfCookieName: 'XSRF-TOKEN',
    xsrfHeaderName: 'X-XSRF-TOKEN',
    timeout: 15000,
});

/**
 * Unwrap the standard envelope. Throws whatever Axios throws so callers keep
 * access to `error.response`.
 */
export async function getData<TData>(url: string): Promise<TData> {
    const response = await api.get<ApiEnvelope<TData>>(url);

    return response.data.data;
}

let csrfReady: Promise<void> | null = null;

/**
 * Make sure the XSRF-TOKEN cookie exists before any state changing request.
 *
 * Laravel rejects POST/PUT/DELETE from a stateful origin when the token is
 * missing, so this has to happen once per page load. The in-flight promise is
 * cached so a burst of calls does not issue a burst of cookie requests.
 */
export function ensureCsrfCookie(): Promise<void> {
    csrfReady ??= api.get('/sanctum/csrf-cookie').then(() => undefined);

    return csrfReady;
}

/**
 * POST, but only after the CSRF cookie is in place.
 */
async function postWithCsrf<TData>(url: string, payload?: unknown): Promise<TData> {
    await ensureCsrfCookie();

    const response = await api.post<ApiEnvelope<TData>>(url, payload ?? {});

    return response.data.data;
}

/**
 * PUT, but only after the CSRF cookie is in place.
 *
 * Laravel matches the `X-XSRF-TOKEN` header on any non-idempotent verb, so
 * forgetting this on a profile update is a 419 in production while the same
 * code looks fine locally.
 */
async function putWithCsrf<TData>(url: string, payload?: unknown): Promise<TData> {
    await ensureCsrfCookie();

    const response = await api.put<ApiEnvelope<TData>>(url, payload ?? {});

    return response.data.data;
}

/**
 * Upload a file with a `FormData` body, only after the CSRF cookie is in place.
 *
 * Handing Axios a `FormData` body makes it drop the instance's default JSON
 * `Content-Type` in a browser, so the boundary the browser generates is the
 * one that travels.
 */
async function uploadWithCsrf<TData>(url: string, form: FormData): Promise<TData> {
    await ensureCsrfCookie();

    const response = await api.post<ApiEnvelope<TData>>(url, form);

    return response.data.data;
}

/**
 * `token` is only non-null for a stateless caller. The SPA is a stateful
 * domain, so it authenticates with the session cookie and always sees null here.
 */
export interface AuthPayload {
    user: AuthUser;
    token: string | null;
}

export const authApi = {
    register: (payload: {
        name: string;
        email: string;
        password: string;
        password_confirmation: string;
    }) => postWithCsrf<AuthPayload>('/api/auth/register', payload),

    login: (payload: { email: string; password: string; remember?: boolean }) =>
        postWithCsrf<AuthPayload>('/api/auth/login', payload),

    logout: () => postWithCsrf<null>('/api/auth/logout'),

    forgotPassword: (email: string) => postWithCsrf<{ email: string }>('/api/auth/forgot-password', { email }),

    resetPassword: (payload: {
        token: string;
        email: string;
        password: string;
        password_confirmation: string;
    }) => postWithCsrf<null>('/api/auth/reset-password', payload),

    me: () => getData<AuthUser>('/api/user'),
};

/**
 * Build the multipart body for a proof upload.
 *
 * Shared by the registration fee and the SPP invoice so the two cannot drift:
 * both send the same field names and the same "an empty referensi means none"
 * rule, which is what lets `UploadBuktiPembayaranRequest` reject a submission
 * from either lane with the same message.
 */
function formBukti(bukti: File, referensi?: string): FormData {
    const form = new FormData();
    form.append('bukti', bukti);

    // An empty referensi input means "none", and the API stores null.
    if (referensi !== undefined && referensi.trim() !== '') {
        form.append('referensi', referensi.trim());
    }

    return form;
}

/**
 * The role-scoped Wali surface.
 *
 * None of these take a user id. The backend resolves the subject from the
 * session, so there is no argument here that could point at another account —
 * which is the point: the client cannot express a request it would be refused.
 */
export const waliApi = {
    dashboard: () => getData<WaliDashboard>('/api/wali/dashboard'),

    profile: () => getData<WaliProfile>('/api/wali/profile'),

    updateProfile: (payload: { name: string; no_hp: string | null }) =>
        putWithCsrf<WaliProfile>('/api/wali/profile', payload),

    updatePassword: (payload: {
        current_password: string;
        password: string;
        password_confirmation: string;
    }) => putWithCsrf<null>('/api/wali/password', payload),

    /**
     * The controller hands the resource collection straight to the envelope, so
     * `data` is already the list — there is no second `data` wrapper.
     */
    santri: () => getData<SantriListItem[]>('/api/wali/santri'),

    /**
     * Find an existing (unlinked) student from NIS/NISN + name + birth date.
     * A 404 is the backend's "Data Santri Tidak Ditemukan.", which is a normal
     * outcome for the form, not an error: it is folded into `null` so the page
     * can render the empty result. Anything else is re-thrown.
     */
    matchSantriLama: async (payload: SantriLamaPayload): Promise<SantriListItem | null> => {
        await ensureCsrfCookie();

        try {
            const response = await api.post<ApiEnvelope<SantriListItem>>('/api/wali/santri-lama/match', payload);
            return response.data.data;
        } catch (cause: unknown) {
            if (axios.isAxiosError(cause) && cause.response?.status === 404) {
                return null;
            }

            throw cause;
        }
    },

    /**
     * Link the student identified by the identification fields to this account.
     * The server re-runs the match and takes the guardian from the session, so
     * there is no id in `payload` that could be pointed elsewhere. Refusals
     * arrive as 409 messages the page can show verbatim.
     */
    registerSantriLama: (payload: SantriLamaPayload) =>
        postWithCsrf<SantriListItem>('/api/wali/santri-lama/register', payload),

    /**
     * The registrations this guardian has submitted.
     */
    pendaftaran: () => getData<PendaftaranListItem[]>('/api/wali/pendaftaran'),

    /**
     * One of this guardian's own registrations. Another guardian's id answers
     * 404 rather than 403, so there is nothing to branch on beyond the error.
     */
    pendaftaranDetail: (id: number) => getData<PendaftaranDetail>(`/api/wali/pendaftaran/${id}`),

    /**
     * Submit a new student. `postWithCsrf` because this is the first state
     * changing request the wali surface makes, and without the XSRF cookie
     * Laravel answers 419.
     *
     * The payload carries no owner and no status. The server takes the guardian
     * from the session, assigns the numbers, and decides both statuses — so
     * the type itself has nowhere to put a `wali_id` to tamper with.
     */
    daftarPendaftaran: (payload: PendaftaranPayload) =>
        postWithCsrf<PendaftaranDetail>('/api/wali/pendaftaran', payload),

    /**
     * The registration-fee payments for this guardian's own students, newest
     * first. The fee lane opens only once a registration is accepted.
     */
    pembayaran: () => getData<PembayaranListItem[]>('/api/wali/pembayaran'),

    /**
     * One of the guardian's own fees, or 404. The same ownership rule as the
     * pendaftaran detail: someone else's id is "not found", never "forbidden".
     */
    pembayaranDetail: (id: number) => getData<PembayaranListItem>(`/api/wali/pembayaran/${id}`),

    /**
     * Upload (or replace) the proof of one fee. The server sniffs the real
     * bytes before accepting the file, and only moves the payment to
     * `menunggu_verifikasi` if the registration was accepted.
     */
    unggahBukti: (id: number, payload: { bukti: File; referensi?: string }) =>
        uploadWithCsrf<PembayaranListItem>(
            `/api/wali/pembayaran/${id}/bukti`,
            formBukti(payload.bukti, payload.referensi),
        ),

    /**
     * The monthly SPP invoices for this guardian's own students, newest month
     * first. Same ownership rule as the rest of this surface: another
     * guardian's invoice is a 404, never a 403.
     */
    tagihanSpp: () => getData<TagihanSpp[]>('/api/wali/spp'),

    /** One of the guardian's own SPP invoices, or 404. */
    tagihanSppDetail: (id: number) => getData<TagihanSpp>(`/api/wali/spp/${id}`),

    /**
     * Upload (or replace) the proof that one month's SPP was paid.
     *
     * The file handling is the same Phase 6 pipeline as the registration fee,
     * and only a bill in `belum` or `ditolak` is accepted; the server re-checks
     * the state machine under a row lock, so the button cannot talk it into a
     * `lunas` or a second upload for a bill already under review.
     */
    unggahBuktiSpp: (id: number, payload: { bukti: File; referensi?: string }) =>
        uploadWithCsrf<TagihanSpp>(`/api/wali/spp/${id}/bukti`, formBukti(payload.bukti, payload.referensi)),
};

export const adminApi = {
    overview: () => getData<AdminOverview>('/api/admin/overview'),

    /**
     * Every registration, regardless of who submitted it. That is the job.
     */
    pendaftaran: () => getData<PendaftaranListItem[]>('/api/admin/pendaftaran'),

    pendaftaranDetail: (id: number) => getData<PendaftaranDetail>(`/api/admin/pendaftaran/${id}`),

    /**
     * Accept or reject a registration in one server-side transaction.
     * Accepting opens the payment lane and moves the student to
     * `menunggu_pembayaran` — it does not activate them.
     */
    putusPendaftaran: (id: number, payload: StatusPendaftaranPayload) =>
        putWithCsrf<PendaftaranDetail>(`/api/admin/pendaftaran/${id}/status`, payload),

    /**
     * Every registration-fee payment, newest first. Reviewing all of them is
     * the job, so there is no ownership filter — but the route itself is
     * `role:admin`, so a guardian gets a 403.
     */
    pembayaran: () => getData<PembayaranListItem[]>('/api/admin/pembayaran'),

    pembayaranDetail: (id: number) => getData<PembayaranListItem>(`/api/admin/pembayaran/${id}`),

    /**
     * The admin's verdict on an uploaded proof: `lunas` settles the fee and
     * activates the student in one transaction; `ditolak` reopens the upload
     * lane for a corrected proof.
     */
    putusPembayaran: (id: number, payload: StatusPembayaranPayload) =>
        putWithCsrf<PembayaranListItem>(`/api/admin/pembayaran/${id}/status`, payload),

    santri: {
        list: (params?: Record<string, string | number | undefined>) => {
            const search = new URLSearchParams();
            if (params) {
                for (const [k, v] of Object.entries(params)) {
                    if (v !== undefined && v !== null && v !== '') search.append(k, String(v));
                }
            }
            const qs = search.toString();
            return getData<SantriAdminListResponse>(`/api/admin/santri${qs ? `?${qs}` : ''}`);
        },
        show: (id: number) => getData<SantriAdmin>(`/api/admin/santri/${id}`),
        update: (id: number, payload: SantriAdminUpdatePayload) => putWithCsrf<SantriAdmin>(`/api/admin/santri/${id}`, payload),
    },

    pengaturan: {
        get: () => getData<any>('/api/admin/pengaturan'),
        update: (payload: { pengaturan: Record<string, string | number> }) => putWithCsrf<any>('/api/admin/pengaturan', payload),
    },

    /**
     * The academic years the SPP queue can be filtered by, so the picker does
     * not hardcode them.
     */
    tahunAjaranSpp: () => getData<TahunAjaranOption[]>('/api/admin/spp/tahun-ajaran'),

    /**
     * Raise one month of SPP for every active student. Idempotent: running it
     * twice for the same month reports how many already existed rather than
     * billing them again, so pressing the button twice is safe. The payload
     * carries no price — the amount is the server's setting.
     */
    generateSpp: (payload: GenerateSppPayload) =>
        postWithCsrf<HasilGenerateSpp>('/api/admin/spp/generate', payload),

    /**
     * Every SPP invoice, newest month first, optionally narrowed. `periode` is
     * `YYYY-MM` and `status` is the wire value, not the printed label; the
     * server rejects anything else with a 422 rather than answering with a
     * silently empty queue.
     */
    tagihanSpp: (filter: FilterSpp = {}) => {
        const params = new URLSearchParams();

        if (filter.tahun_ajaran_id !== undefined) {
            params.set('tahun_ajaran_id', String(filter.tahun_ajaran_id));
        }

        if (filter.periode !== undefined && filter.periode !== '') {
            params.set('periode', filter.periode);
        }

        if (filter.status !== undefined) {
            params.set('status', filter.status);
        }

        const query = params.toString();

        return getData<TagihanSpp[]>(query === '' ? '/api/admin/spp' : `/api/admin/spp?${query}`);
    },

    tagihanSppDetail: (id: number) => getData<TagihanSpp>(`/api/admin/spp/${id}`),

    /**
     * The admin's verdict on one invoice's proof: `lunas` settles it;
     * `ditolak` reopens the upload lane for a corrected proof. Unlike the
     * registration-fee verdict this does **not** touch the student — they were
     * already active when the bill was raised.
     */
    putusTagihanSpp: (id: number, payload: VerdictSppPayload) =>
        putWithCsrf<TagihanSpp>(`/api/admin/spp/${id}/status`, payload),

    /**
     * The financial report: settled vs pending vs rejected totals, filtered the
     * same way the table is.
     */
    laporan: (filter: LaporanFilter = {}) => {
        const params = new URLSearchParams();

        if (filter.tanggal_awal) params.set('tanggal_awal', filter.tanggal_awal);
        if (filter.tanggal_akhir) params.set('tanggal_akhir', filter.tanggal_akhir);
        if (filter.jenis_pembayaran) params.set('jenis_pembayaran', filter.jenis_pembayaran);
        if (filter.status) params.set('status', filter.status);
        if (filter.per_page) params.set('per_page', String(filter.per_page));

        const query = params.toString();

        return getData<LaporanReport>(query === '' ? '/api/admin/laporan' : `/api/admin/laporan?${query}`);
    },

    /**
     * Stream the same filtered report out as a CSV download.
     */
    laporanExport: async (filter: LaporanFilter = {}) => {
        const params = new URLSearchParams();

        if (filter.tanggal_awal) params.set('tanggal_awal', filter.tanggal_awal);
        if (filter.tanggal_akhir) params.set('tanggal_akhir', filter.tanggal_akhir);
        if (filter.jenis_pembayaran) params.set('jenis_pembayaran', filter.jenis_pembayaran);
        if (filter.status) params.set('status', filter.status);
        if (filter.per_page) params.set('per_page', String(filter.per_page));

        const query = params.toString();

        const response = await api.get(`/api/admin/laporan/ekspor${query === '' ? '' : `?${query}`}`, {
            responseType: 'blob',
        });

        const blob = new Blob([response.data], { type: 'text/csv;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `laporan-keuangan-${new Date().toISOString().slice(0, 10)}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);

        return response.data;
    },
};

export const adminPenggunaApi = {
    list: async (filter: PenggunaFilter = {}): Promise<PenggunaListResponse> => {
        const params = new URLSearchParams();

        if (filter.search) params.set('search', filter.search);
        if (filter.role) params.set('role', filter.role);
        if (filter.page) params.set('page', String(filter.page));
        if (filter.per_page) params.set('per_page', String(filter.per_page));

        const query = params.toString();
        const response = await api.get<ApiEnvelope<Pengguna[]> & { meta?: PenggunaListResponse['meta'] }>(
            `/api/admin/pengguna${query === '' ? '' : `?${query}`}`,
        );

        return {
            data: response.data.data,
            meta: response.data.meta,
        };
    },

    get: (id: number | string) => getData<Pengguna>(`/api/admin/pengguna/${id}`),

    update: (id: number | string, payload: PenggunaUpdatePayload) =>
        putWithCsrf<Pengguna>(`/api/admin/pengguna/${id}`, payload),
};

export const adminAuditTrailApi = {
    list: async (filter: AuditTrailFilter = {}): Promise<AuditLogListResponse> => {
        const params = new URLSearchParams();

        if (filter.user_id !== undefined) params.set('user_id', String(filter.user_id));
        if (filter.action) params.set('action', filter.action);
        if (filter.from) params.set('from', filter.from);
        if (filter.to) params.set('to', filter.to);
        if (filter.page) params.set('page', String(filter.page));
        if (filter.per_page) params.set('per_page', String(filter.per_page));

        const query = params.toString();
        const response = await api.get<ApiEnvelope<AuditLog[]> & { meta: AuditLogListResponse['meta'] }>(
            `/api/admin/audit-trail${query === '' ? '' : `?${query}`}`,
        );

        return {
            data: response.data.data,
            meta: response.data.meta,
        };
    },

    get: (id: number) => getData<AuditLog>(`/api/admin/audit-trail/${id}`),
};

api.interceptors.response.use(
    (response) => response,
    (error: AxiosError<ApiEnvelope<unknown>>) => {
        if (error.response?.status === 401) {
            // DashboardLayout listens for this and drops the local session.
            window.dispatchEvent(new CustomEvent('auth:unauthorized'));
        }

        return Promise.reject(error);
    },
);

/**
 * Turn any Axios failure into a message that is safe to show in the UI.
 */
export function describeError(error: unknown): string {
    if (axios.isAxiosError(error)) {
        if (error.code === 'ECONNABORTED') {
            return 'Permintaan terlalu lama. Periksa koneksi Anda.';
        }

        if (error.response?.status === 422) {
            const fields = error.response.data?.data?.errors as
                | Record<string, string[]>
                | undefined;
            const first = fields ? Object.values(fields).flat()[0] : undefined;

            if (first !== undefined) {
                return first;
            }
        }

        const body = error.response?.data;

        if (body?.message) {
            return body.message;
        }

        if (!error.response) {
            return 'Tidak dapat terhubung ke server. Pastikan API sedang berjalan.';
        }

        return `Permintaan gagal (HTTP ${error.response.status}).`;
    }

    return 'Terjadi kesalahan yang tidak terduga.';
}

/**
 * Per-field validation messages from a 422, keyed by field name, so a form can
 * put each message next to its own input.
 */
export const waliNotifikasiApi = {
    list: (page = 1) => getData<any>(`/api/wali/notifikasi?page=${page}`),
    unreadCount: () => getData<{ count: number }>('/api/wali/notifikasi/unread-count'),
    markRead: (id: number) => putWithCsrf<any>(`/api/wali/notifikasi/${id}/read`, {}),
    markAllRead: () => putWithCsrf<any>('/api/wali/notifikasi/read-all', {}),
};

export function fieldErrors(error: unknown): Record<string, string[]> {
    if (!axios.isAxiosError(error) || error.response?.status !== 422) {
        return {};
    }

    return (error.response.data?.data?.errors as Record<string, string[]> | undefined) ?? {};
}
