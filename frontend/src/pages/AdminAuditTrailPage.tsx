import { useCallback, useEffect, useState } from 'react';
import { useSearchParams } from 'react-router';

import { adminAuditTrailApi, adminPenggunaApi, describeError } from '@/lib/api';
import type { AuditLog, AuditLogListResponse } from '@/types/audit';
import type { PenggunaListResponse } from '@/types/pengguna';

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '-';
}

function AuditValues({ title, values }: { title: string; values: Record<string, unknown> | null }) {
    return (
        <section className="min-w-0">
            <h3 className="mb-2 text-sm font-semibold text-slate-800">{title}</h3>
            {values && Object.keys(values).length > 0 ? (
                <pre className="max-h-72 overflow-auto whitespace-pre-wrap break-words rounded-md bg-slate-50 p-3 text-xs text-slate-700">
                    {JSON.stringify(values, null, 2)}
                </pre>
            ) : (
                <p className="text-sm text-slate-500">Tidak ada perubahan tercatat.</p>
            )}
        </section>
    );
}

export default function AdminAuditTrailPage() {
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [auditLogs, setAuditLogs] = useState<AuditLogListResponse | null>(null);
    const [users, setUsers] = useState<PenggunaListResponse | null>(null);
    const [usersLoading, setUsersLoading] = useState(true);
    const [usersError, setUsersError] = useState<string | null>(null);
    const [usersPage, setUsersPage] = useState(1);
    const [selectedLog, setSelectedLog] = useState<AuditLog | null>(null);
    const [selectedLogId, setSelectedLogId] = useState<number | null>(null);
    const [detailLoading, setDetailLoading] = useState(false);
    const [detailError, setDetailError] = useState<string | null>(null);

    const [searchParams, setSearchParams] = useSearchParams();
    const userId = searchParams.get('user_id') ?? '';
    const action = searchParams.get('action') ?? '';
    const from = searchParams.get('from') ?? '';
    const to = searchParams.get('to') ?? '';
    const parsedPage = Number.parseInt(searchParams.get('page') ?? '1', 10);
    const page = Number.isSafeInteger(parsedPage) && parsedPage > 0 ? parsedPage : 1;
    const numericUserId = Number.parseInt(userId, 10);

    const updateSearchParams = useCallback((key: string, value: string | number) => {
        setSearchParams((previous) => {
            const next = new URLSearchParams(previous);
            if (value === '') {
                next.delete(key);
            } else {
                next.set(key, String(value));
            }
            if (key !== 'page') {
                next.set('page', '1');
            }

            return next;
        });
    }, [setSearchParams]);

    const loadAuditLogs = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await adminAuditTrailApi.list({
                user_id: Number.isSafeInteger(numericUserId) && numericUserId > 0 ? numericUserId : undefined,
                action,
                from,
                to,
                page,
                per_page: 20,
            });
            setAuditLogs(response);
        } catch (cause: unknown) {
            console.error(cause);
            setError(describeError(cause));
            setAuditLogs(null);
        } finally {
            setLoading(false);
        }
    }, [action, from, numericUserId, page, to]);

    const loadUsers = useCallback(async () => {
        setUsersLoading(true);
        setUsersError(null);
        try {
            const response = await adminPenggunaApi.list({ page: usersPage, per_page: 100 });
            setUsers(response);
        } catch (cause: unknown) {
            console.error(cause);
            setUsersError(describeError(cause));
            setUsers(null);
        } finally {
            setUsersLoading(false);
        }
    }, [usersPage]);

    useEffect(() => {
        void loadAuditLogs();
    }, [loadAuditLogs]);

    useEffect(() => {
        void loadUsers();
    }, [loadUsers]);

    const openDetails = async (id: number) => {
        setSelectedLogId(id);
        setSelectedLog(null);
        setDetailError(null);
        setDetailLoading(true);

        try {
            setSelectedLog(await adminAuditTrailApi.get(id));
        } catch (cause: unknown) {
            console.error(cause);
            setDetailError(describeError(cause));
        } finally {
            setDetailLoading(false);
        }
    };

    const closeDetails = () => {
        setSelectedLog(null);
        setSelectedLogId(null);
        setDetailError(null);
    };

    const pagination = auditLogs?.meta;
    const selectedUserAvailable = users?.data.some((user) => String(user.id) === userId) ?? false;

    return (
        <div className="mx-auto max-w-7xl px-4 py-8">
            <header className="mb-6">
                <h1 className="text-2xl font-semibold text-slate-900">Audit Trail</h1>
                <p className="mt-1 text-sm text-slate-600">Riwayat aktivitas dan perubahan yang tercatat dalam sistem.</p>
            </header>

            <section className="mb-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h2 className="mb-4 text-base font-semibold text-slate-900">Filter aktivitas</h2>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <label className="flex flex-col gap-1 text-sm font-medium text-slate-700">
                        Pelaku
                        <select
                            aria-label="Filter pengguna"
                            value={userId}
                            onChange={(event) => updateSearchParams('user_id', event.target.value)}
                            disabled={usersLoading || usersError !== null}
                            className="rounded-md border border-slate-300 bg-white px-3 py-2 font-normal disabled:bg-slate-100"
                        >
                            <option value="">Semua pengguna</option>
                            {userId !== '' && !selectedUserAvailable && <option value={userId}>Pengguna #{userId}</option>}
                            {users?.data.map((user) => (
                                <option key={user.id} value={user.id}>
                                    {user.name}
                                </option>
                            ))}
                        </select>
                        {usersLoading && <span className="text-xs text-slate-500">Memuat daftar pengguna...</span>}
                        {usersError && (
                            <span className="text-xs text-red-700" role="alert">
                                Daftar pengguna gagal dimuat.{' '}
                                <button type="button" onClick={() => void loadUsers()} className="font-semibold underline">
                                    Coba lagi
                                </button>
                            </span>
                        )}
                        {users?.meta && users.meta.last_page > 1 && (
                            <span className="flex items-center gap-2 text-xs font-normal text-slate-600">
                                <button
                                    type="button"
                                    disabled={usersPage <= 1 || usersLoading}
                                    onClick={() => setUsersPage((current) => current - 1)}
                                    className="underline disabled:opacity-50"
                                >
                                    Sebelumnya
                                </button>
                                Halaman {users.meta.current_page} dari {users.meta.last_page}
                                <button
                                    type="button"
                                    disabled={usersPage >= users.meta.last_page || usersLoading}
                                    onClick={() => setUsersPage((current) => current + 1)}
                                    className="underline disabled:opacity-50"
                                >
                                    Berikutnya
                                </button>
                            </span>
                        )}
                    </label>

                    <label className="flex flex-col gap-1 text-sm font-medium text-slate-700">
                        Jenis aktivitas
                        <input
                            type="search"
                            value={action}
                            onChange={(event) => updateSearchParams('action', event.target.value)}
                            placeholder="Contoh: pengguna.update"
                            className="rounded-md border border-slate-300 px-3 py-2 font-normal"
                        />
                    </label>

                    <label className="flex flex-col gap-1 text-sm font-medium text-slate-700">
                        Tanggal awal
                        <input
                            type="date"
                            value={from}
                            onChange={(event) => updateSearchParams('from', event.target.value)}
                            className="rounded-md border border-slate-300 px-3 py-2 font-normal"
                        />
                    </label>

                    <label className="flex flex-col gap-1 text-sm font-medium text-slate-700">
                        Tanggal akhir
                        <input
                            type="date"
                            value={to}
                            onChange={(event) => updateSearchParams('to', event.target.value)}
                            className="rounded-md border border-slate-300 px-3 py-2 font-normal"
                        />
                    </label>
                </div>
            </section>

            {loading && <p className="py-8 text-center text-sm text-slate-600" role="status">Memuat audit trail...</p>}

            {error && (
                <div className="flex flex-col gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 sm:flex-row sm:items-center sm:justify-between">
                    <p role="alert">{error}</p>
                    <button
                        type="button"
                        onClick={() => void loadAuditLogs()}
                        className="self-start rounded-md border border-red-300 px-3 py-1.5 font-medium hover:bg-red-100 sm:self-auto"
                    >
                        Coba lagi
                    </button>
                </div>
            )}

            {!loading && !error && (
                <>
                    <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
                        <table className="min-w-full divide-y divide-slate-200">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th scope="col" className="px-4 py-3 text-left text-sm font-semibold text-slate-900">Waktu</th>
                                    <th scope="col" className="px-4 py-3 text-left text-sm font-semibold text-slate-900">Pelaku</th>
                                    <th scope="col" className="px-4 py-3 text-left text-sm font-semibold text-slate-900">Aktivitas</th>
                                    <th scope="col" className="px-4 py-3 text-left text-sm font-semibold text-slate-900">Objek</th>
                                    <th scope="col" className="px-4 py-3 text-right text-sm font-semibold text-slate-900">Detail</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200">
                                {auditLogs?.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="px-4 py-10 text-center text-sm text-slate-500">
                                            Tidak ada aktivitas yang cocok dengan filter.
                                        </td>
                                    </tr>
                                ) : auditLogs?.data.map((log) => (
                                    <tr key={log.id} className="hover:bg-slate-50">
                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-slate-600">{formatDate(log.created_at)}</td>
                                        <td className="px-4 py-3 text-sm text-slate-700">
                                            <span className="font-medium">{log.pelaku?.name ?? 'Pengguna tidak tersedia'}</span>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-slate-700">
                                            <span className="font-medium">{log.action}</span>
                                            {log.description && <span className="block text-xs text-slate-500">{log.description}</span>}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-slate-600">
                                            {log.subject_type?.split('\\').pop() ?? '-'}
                                            {log.subject_id !== null && ` #${log.subject_id}`}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <button
                                                type="button"
                                                onClick={() => void openDetails(log.id)}
                                                className="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100"
                                            >
                                                Lihat detail
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {pagination && pagination.last_page > 1 && (
                        <nav aria-label="Pagination audit trail" className="mt-4 flex items-center justify-between gap-4">
                            <p className="text-sm text-slate-600">
                                Halaman {pagination.current_page} dari {pagination.last_page} · {pagination.total} aktivitas
                            </p>
                            <div className="flex gap-2">
                                <button
                                    type="button"
                                    disabled={pagination.current_page <= 1}
                                    onClick={() => updateSearchParams('page', pagination.current_page - 1)}
                                    className="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 disabled:opacity-50"
                                >
                                    Sebelumnya
                                </button>
                                <button
                                    type="button"
                                    disabled={pagination.current_page >= pagination.last_page}
                                    onClick={() => updateSearchParams('page', pagination.current_page + 1)}
                                    className="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 disabled:opacity-50"
                                >
                                    Berikutnya
                                </button>
                            </div>
                        </nav>
                    )}
                </>
            )}

            {selectedLogId !== null && (
                <div
                    className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-950/40 p-4 sm:p-8"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="audit-detail-title"
                >
                    <section className="my-auto w-full max-w-3xl rounded-lg bg-white p-5 shadow-xl">
                        <header className="mb-5 flex items-start justify-between gap-4">
                            <div>
                                <h2 id="audit-detail-title" className="text-lg font-semibold text-slate-900">Detail aktivitas</h2>
                                {selectedLog && <p className="mt-1 text-sm text-slate-500">ID audit #{selectedLog.id}</p>}
                            </div>
                            <button
                                type="button"
                                onClick={closeDetails}
                                className="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
                            >
                                Tutup
                            </button>
                        </header>

                        {detailLoading && <p className="py-8 text-center text-sm text-slate-600" role="status">Memuat detail...</p>}
                        {detailError && (
                            <div className="flex items-center justify-between gap-3 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                                <p role="alert">{detailError}</p>
                                <button
                                    type="button"
                                    onClick={() => void openDetails(selectedLogId)}
                                    className="font-semibold underline"
                                >
                                    Coba lagi
                                </button>
                            </div>
                        )}
                        {selectedLog && !detailLoading && (
                            <div className="space-y-5">
                                <dl className="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <dt className="text-xs font-semibold uppercase text-slate-500">Aktivitas</dt>
                                        <dd className="mt-1 text-sm text-slate-800">{selectedLog.action}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs font-semibold uppercase text-slate-500">Waktu</dt>
                                        <dd className="mt-1 text-sm text-slate-800">{formatDate(selectedLog.created_at)}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs font-semibold uppercase text-slate-500">Pelaku</dt>
                                        <dd className="mt-1 text-sm text-slate-800">
                                            {selectedLog.pelaku?.name ?? 'Pengguna tidak tersedia'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs font-semibold uppercase text-slate-500">Objek</dt>
                                        <dd className="mt-1 text-sm text-slate-800">
                                            {selectedLog.subject_type ?? '-'}
                                            {selectedLog.subject_id !== null && ` #${selectedLog.subject_id}`}
                                        </dd>
                                    </div>
                                    <div className="sm:col-span-2">
                                        <dt className="text-xs font-semibold uppercase text-slate-500">Keterangan</dt>
                                        <dd className="mt-1 whitespace-pre-wrap text-sm text-slate-800">{selectedLog.description ?? '-'}</dd>
                                    </div>
                                </dl>

                                <div className="grid gap-5 md:grid-cols-2">
                                    <AuditValues title="Nilai sebelumnya" values={selectedLog.old_values} />
                                    <AuditValues title="Nilai terbaru" values={selectedLog.new_values} />
                                </div>

                                <section className="border-t border-slate-200 pt-4">
                                    <h3 className="mb-2 text-sm font-semibold text-slate-800">Informasi permintaan</h3>
                                    <dl className="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <dt className="text-xs font-semibold uppercase text-slate-500">Alamat IP</dt>
                                            <dd className="mt-1 break-all text-sm text-slate-700">{selectedLog.ip_address ?? '-'}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-xs font-semibold uppercase text-slate-500">User agent</dt>
                                            <dd className="mt-1 break-words text-sm text-slate-700">{selectedLog.user_agent ?? '-'}</dd>
                                        </div>
                                    </dl>
                                </section>
                            </div>
                        )}
                    </section>
                </div>
            )}
        </div>
    );
}
