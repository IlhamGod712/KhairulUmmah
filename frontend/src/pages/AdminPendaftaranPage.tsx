import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router';

import { Card } from '@/components/Card';
import { StatusBadge } from '@/components/StatusBadge';
import { adminApi, describeError } from '@/lib/api';
import { JENIS_KELAMIN_LABELS } from '@/types/auth';
import type { PendaftaranListItem } from '@/types/pendaftaran';

/**
 * Every registration submitted to the school.
 *
 * The list is the review queue; the decision itself lives on the detail page,
 * where `PUT /api/admin/pendaftaran/{id}/status` accepts or rejects one
 * submission. Nothing on this table changes a status directly, so a mis-click
 * cannot skip the reason field the server requires for a rejection.
 */
export default function AdminPendaftaranPage() {
    const [pendaftarans, setPendaftaran] = useState<PendaftaranListItem[]>([]);
    const [error, setError] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState(true);

    const muat = useCallback(async () => {
        setIsLoading(true);
        setError(null);

        try {
            setPendaftaran(await adminApi.pendaftaran());
        } catch (cause: unknown) {
            setError(describeError(cause));
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        void muat();
    }, [muat]);

    const menunggu = pendaftarans.filter((p) => p.status === 'diajukan').length;

    return (
        <div className="space-y-6">
            <header>
                <h1 className="text-xl font-semibold text-slate-900">Pendaftaran Santri Baru</h1>
                <p className="text-sm text-slate-500">
                    Seluruh pendaftaran yang diajukan oleh wali. Buka detail untuk menerima atau menolak
                    pendaftaran; alasan wajib diisi saat menolak.
                </p>
            </header>

            {!isLoading && !error && (
                <div className="grid gap-4 sm:grid-cols-3">
                    <Card title="Total pendaftaran">
                        <p className="text-2xl font-semibold text-slate-900">{pendaftarans.length}</p>
                    </Card>
                    <Card title="Menunggu verifikasi">
                        <p className="text-2xl font-semibold text-amber-600">{menunggu}</p>
                    </Card>
                    <Card title="Sudah diproses">
                        <p className="text-2xl font-semibold text-slate-900">{pendaftarans.length - menunggu}</p>
                    </Card>
                </div>
            )}

            {error !== null && (
                <p role="alert" className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                    {error}
                </p>
            )}

            {isLoading && <p className="text-sm text-slate-500">Memuat…</p>}

            {!isLoading && !error && pendaftarans.length === 0 && (
                <Card title="Belum ada pendaftaran" description="Belum ada pendaftaran yang masuk dari wali.">
                    <p className="text-sm text-slate-600">
                        Pendaftaran baru akan muncul di sini begitu seorang wali mengirimkannya.
                    </p>
                </Card>
            )}

            {!isLoading && !error && pendaftarans.length > 0 && (
                <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="w-full text-left text-sm">
                        <caption className="sr-only">Seluruh pendaftaran yang diajukan oleh wali</caption>
                        <thead className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="px-4 py-3 font-medium">No. Pendaftaran</th>
                                <th className="px-4 py-3 font-medium">Calon Santri</th>
                                <th className="px-4 py-3 font-medium">Wali Pengaju</th>
                                <th className="px-4 py-3 font-medium">Tanggal</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">
                                    <span className="sr-only">Aksi</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {pendaftarans.map((pendaftaran) => (
                                <tr key={pendaftaran.id}>
                                    <td className="px-4 py-3 font-mono text-xs text-slate-600">
                                        {pendaftaran.nomor_pendaftaran}
                                    </td>
                                    <td className="px-4 py-3">
                                        <p className="font-medium text-slate-800">{pendaftaran.nama_santri}</p>
                                        <p className="text-xs text-slate-500">
                                            {JENIS_KELAMIN_LABELS[pendaftaran.jenis_kelamin]} ·{' '}
                                            {pendaftaran.tahun_ajaran ?? '—'}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">
                                        {pendaftaran.wali?.name ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">{pendaftaran.tanggal_daftar}</td>
                                    <td className="px-4 py-3">
                                        <StatusBadge status={pendaftaran.status} label={pendaftaran.status_label} />
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Link
                                            to={`/admin/pendaftaran/${pendaftaran.id}`}
                                            className="text-sm font-medium text-brand-700 underline underline-offset-2 hover:text-brand-800"
                                        >
                                            Detail
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
