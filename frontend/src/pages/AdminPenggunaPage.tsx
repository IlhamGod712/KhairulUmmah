import { useCallback, useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router';

import { adminPenggunaApi, describeError } from '@/lib/api';
import { cn } from '@/lib/utils';
import type { Pengguna, PenggunaListResponse } from '@/types/pengguna';
import { USER_ROLE_LABELS } from '@/types/auth';

function PenggunaRow({ pengguna }: { pengguna: Pengguna }) {
    return (
        <tr className="group relative border-b border-slate-200 hover:bg-slate-50">
            <td className="whitespace-nowrap px-3 py-4 text-sm font-medium text-slate-900">
                <Link to={`/admin/pengguna/${pengguna.id}`} className="stretched-link">
                    {pengguna.name}
                </Link>
            </td>
            <td className="px-3 py-4 text-sm text-slate-500">{pengguna.email}</td>
            <td className="px-3 py-4 text-sm text-slate-500">{pengguna.no_hp ?? '-'}</td>
            <td className="px-3 py-4 text-sm text-slate-500">{pengguna.role_label}</td>
            <td className="px-3 py-4 text-sm text-slate-500">
                {pengguna.created_at ? new Date(pengguna.created_at).toLocaleDateString() : '-'}
            </td>
        </tr>
    );
}

export default function AdminPenggunaPage() {
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [penggunaData, setPenggunaData] = useState<PenggunaListResponse | null>(null);

    const [searchParams, setSearchParams] = useSearchParams();
    const search = searchParams.get('search') ?? '';
    const roleParam = searchParams.get('role');
    const role = roleParam === 'wali' || roleParam === 'admin' ? roleParam : '';
    const parsedPage = Number.parseInt(searchParams.get('page') ?? '1', 10);
    const page = Number.isSafeInteger(parsedPage) && parsedPage > 0 ? parsedPage : 1;

    const updateSearchParams = useCallback((key: string, value: string | number) => {
        setSearchParams((prev) => {
            const newParams = new URLSearchParams(prev);
            if (value === '') {
                newParams.delete(key);
            } else {
                newParams.set(key, String(value));
            }
            if (key !== 'page') newParams.set('page', '1');
            return newParams;
        });
    }, [setSearchParams]);

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await adminPenggunaApi.list({ search, role, page });
            setPenggunaData(res);
        } catch (e) {
            console.error(e);
            setError(describeError(e));
        } finally {
            setLoading(false);
        }
    }, [search, role, page]);

    useEffect(() => {
        void load();
    }, [load]);

    const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        updateSearchParams('search', e.target.value);
    };

    const handleRoleChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        updateSearchParams('role', e.target.value);
    };

    const handlePageChange = (newPage: number) => {
        updateSearchParams('page', newPage);
    };

    const renderPagination = () => {
        if (!penggunaData?.meta || penggunaData.meta.last_page <= 1) return null;
        const { current_page, last_page, total } = penggunaData.meta;

        return (
            <div className="flex items-center justify-between border-t border-slate-200 bg-white px-4 py-3 sm:px-6">
                <div className="flex flex-1 justify-between sm:hidden">
                    <button
                        onClick={() => handlePageChange(current_page - 1)}
                        disabled={current_page <= 1}
                        className="relative inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50">
                        Sebelumnya
                    </button>
                    <button
                        onClick={() => handlePageChange(current_page + 1)}
                        disabled={current_page >= last_page}
                        className="relative ml-3 inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50">
                        Berikutnya
                    </button>
                </div>
                <div className="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                    <div>
                        <p className="text-sm text-slate-700">
                            Menampilkan <span className="font-medium">{(current_page - 1) * (penggunaData.meta.per_page ?? 1) + 1}</span> sampai <span className="font-medium">{Math.min(current_page * (penggunaData.meta.per_page ?? 1), total)}</span> dari <span className="font-medium">{total}</span> hasil
                        </p>
                    </div>
                    <div>
                        <nav className="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                            <button
                                onClick={() => handlePageChange(current_page - 1)}
                                disabled={current_page <= 1}
                                className="relative inline-flex items-center rounded-l-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0 disabled:opacity-50">
                                <span className="sr-only">Previous</span>
                                &lt;
                            </button>
                            {[...Array(last_page).keys()].map((p) => (
                                <button
                                    key={p + 1}
                                    onClick={() => handlePageChange(p + 1)}
                                    aria-current={current_page === p + 1 ? 'page' : undefined}
                                    className={cn(
                                        'relative inline-flex items-center px-4 py-2 text-sm font-semibold focus:z-20 focus:outline-offset-0',
                                        current_page === p + 1
                                            ? 'z-10 bg-brand-600 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600'
                                            : 'text-slate-900 ring-1 ring-inset ring-slate-300 hover:bg-slate-50',
                                    )}
                                >
                                    {p + 1}
                                </button>
                            ))}
                            <button
                                onClick={() => handlePageChange(current_page + 1)}
                                disabled={current_page >= last_page}
                                className="relative inline-flex items-center rounded-r-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0 disabled:opacity-50">
                                <span className="sr-only">Next</span>
                                &gt;
                            </button>
                        </nav>
                    </div>
                </div>
            </div>
        );
    };

    return (
        <div className="mx-auto max-w-7xl px-4 py-8">
            <div className="mb-6">
                <h1 className="text-2xl font-semibold text-slate-900">Manajemen Pengguna</h1>
                <p className="mt-1 text-sm text-slate-600">Kelola akun pengguna (admin dan wali) yang terdaftar dalam sistem.</p>
            </div>

            <div className="mb-4 flex flex-col gap-3 sm:flex-row">
                <input
                    type="text"
                    aria-label="Cari pengguna"
                    placeholder="Cari nama atau email..."
                    value={search}
                    onChange={handleSearchChange}
                    className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                />
                <select
                    aria-label="Filter role pengguna"
                    value={role}
                    onChange={handleRoleChange}
                    className="rounded-md border border-slate-300 px-3 py-2 text-sm"
                >
                    <option value="">Semua Role</option>
                    {Object.entries(USER_ROLE_LABELS).map(([key, label]) => (
                        <option key={key} value={key}>
                            {label}
                        </option>
                    ))}
                </select>
            </div>

            {loading ? <p className="text-sm text-slate-600" role="status">Memuat pengguna...</p> : null}
            {error && (
                <div className="flex flex-col gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 sm:flex-row sm:items-center sm:justify-between">
                    <p role="alert">{error}</p>
                    <button
                        type="button"
                        onClick={() => void load()}
                        className="self-start rounded-md border border-red-300 px-3 py-1.5 font-medium hover:bg-red-100 sm:self-auto"
                    >
                        Coba lagi
                    </button>
                </div>
            )}

            {!loading && !error && (
                <>
                    <div className="overflow-x-auto rounded-lg border border-slate-200 shadow-sm">
                        <table className="min-w-full divide-y divide-slate-200">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th scope="col" className="px-3 py-3.5 text-left text-sm font-semibold text-slate-900">Nama</th>
                                    <th scope="col" className="px-3 py-3.5 text-left text-sm font-semibold text-slate-900">Email</th>
                                    <th scope="col" className="px-3 py-3.5 text-left text-sm font-semibold text-slate-900">No. HP</th>
                                    <th scope="col" className="px-3 py-3.5 text-left text-sm font-semibold text-slate-900">Role</th>
                                    <th scope="col" className="px-3 py-3.5 text-left text-sm font-semibold text-slate-900">Terdaftar Sejak</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 bg-white">
                                {penggunaData?.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="whitespace-nowrap px-3 py-4 text-sm text-slate-500 text-center">Tidak ada data pengguna.</td>
                                    </tr>
                                ) : (
                                    penggunaData?.data.map((pengguna) => (
                                        <PenggunaRow key={pengguna.id} pengguna={pengguna} />
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                    {renderPagination()}
                </>
            )}
        </div>
    );
}
