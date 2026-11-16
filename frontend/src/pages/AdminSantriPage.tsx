import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router';

import { adminApi } from '@/lib/api';
import type { SantriAdmin } from '@/types/santri';

export default function AdminSantriPage() {
    const navigate = useNavigate();
    const [items, setItems] = useState<SantriAdmin[]>([]);
    const [pagination, setPagination] = useState({ current_page: 1, per_page: 15, total: 0, last_page: 1 });
    const [q, setQ] = useState('');
    const [status, setStatus] = useState('');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const load = async (page = 1) => {
        setLoading(true);
        setError(null);
        try {
            const res = await adminApi.santri.list({ q: q || undefined, status: status || undefined, page, per_page: pagination.per_page });
            setItems(res.items || []);
            setPagination(res.pagination);
        } catch (e) {
            setError('Gagal memuat data santri.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        void load(1);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const onSearch = (e: React.FormEvent) => {
        e.preventDefault();
        void load(1);
    };

    return (
        <div className="mx-auto max-w-6xl px-4 py-8">
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Manajemen Santri</h1>
                    <p className="mt-1 text-sm text-slate-600">Daftar, cari, filter, dan kelola data santri.</p>
                </div>
            </div>

            <form onSubmit={onSearch} className="mb-4 flex flex-wrap items-center gap-2">
                <input
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    placeholder="Cari NIS/NISN/Nama..."
                    className="rounded-md border border-slate-300 px-3 py-2 text-sm"
                />
                <select value={status} onChange={(e) => setStatus(e.target.value)} className="rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Semua Status</option>
                    <option value="pendaftaran">Pendaftaran</option>
                    <option value="menunggu_pembayaran">Menunggu Pembayaran</option>
                    <option value="menunggu_verifikasi">Menunggu Verifikasi</option>
                    <option value="aktif">Aktif</option>
                    <option value="ditolak">Ditolak</option>
                    <option value="dibatalkan">Dibatalkan</option>
                </select>
                <button type="submit" className="rounded-md bg-brand-600 px-3 py-2 text-sm text-white">Cari</button>
            </form>

            {loading ? <p className="text-sm text-slate-600">Memuat...</p> : error ? <p className="text-sm text-red-600">{error}</p> : null}

            {!loading && !error && (
                <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-2 text-left font-medium text-slate-700">NIS</th>
                                <th className="px-4 py-2 text-left font-medium text-slate-700">Nama</th>
                                <th className="px-4 py-2 text-left font-medium text-slate-700">Wali</th>
                                <th className="px-4 py-2 text-left font-medium text-slate-700">Status</th>
                                <th className="px-4 py-2 text-left font-medium text-slate-700">Tahun Ajaran</th>
                                <th className="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {items.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-4 py-4 text-center text-slate-500">Tidak ada data</td>
                                </tr>
                            ) : (
                                items.map((s) => (
                                    <tr key={s.id}>
                                        <td className="px-4 py-2 text-slate-700">{s.nis}</td>
                                        <td className="px-4 py-2 text-slate-900">{s.nama_lengkap}</td>
                                        <td className="px-4 py-2 text-slate-700">{s.wali?.name || '-'}</td>
                                        <td className="px-4 py-2 text-slate-700">{s.status_label || s.status}</td>
                                        <td className="px-4 py-2 text-slate-700">{s.tahun_ajaran?.nama || '-'}</td>
                                        <td className="px-4 py-2 text-right">
                                            <button onClick={() => navigate(`/admin/santri/${s.id}`)} className="text-brand-700 hover:underline">Detail</button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            )}

            {!loading && pagination.last_page > 1 && (
                <div className="mt-4 flex items-center justify-between text-sm">
                    <span className="text-slate-600">Halaman {pagination.current_page} / {pagination.last_page}</span>
                    <div className="flex gap-2">
                        <button disabled={pagination.current_page <= 1} onClick={() => void load(pagination.current_page - 1)} className="rounded-md border px-3 py-1 disabled:opacity-50">Sebelumnya</button>
                        <button disabled={pagination.current_page >= pagination.last_page} onClick={() => void load(pagination.current_page + 1)} className="rounded-md border px-3 py-1 disabled:opacity-50">Berikutnya</button>
                    </div>
                </div>
            )}
        </div>
    );
}
