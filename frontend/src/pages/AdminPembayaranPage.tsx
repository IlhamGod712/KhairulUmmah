import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router';

import { Card } from '@/components/Card';
import { StatusBadge } from '@/components/StatusBadge';
import { adminApi, describeError } from '@/lib/api';
import { rupiah } from '@/lib/utils';
import type { PembayaranListItem } from '@/types/pembayaran';

/**
 * Every registration-fee payment, newest first — the admin's review queue.
 *
 * Unlike the wali surface there is no ownership filter: reviewing all of them
 * is the job. The `role:admin` middleware is the only gate, so a guardian gets
 * a 403 here, not a 404. The queue highlights proofs waiting for a verdict
 * (`menunggu_verifikasi`), which is what this page exists to drain.
 */
export default function AdminPembayaranPage() {
    const [pembayarans, setPembayaran] = useState<PembayaranListItem[]>([]);
    const [error, setError] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState(true);

    const muat = useCallback(async () => {
        setIsLoading(true);
        setError(null);

        try {
            setPembayaran(await adminApi.pembayaran());
        } catch (cause: unknown) {
            setError(describeError(cause));
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        void muat();
    }, [muat]);

    const menunggu = pembayarans.filter((p) => p.status === 'menunggu_verifikasi').length;
    const lunas = pembayarans.filter((p) => p.status === 'lunas').length;

    return (
        <div className="space-y-6">
            <header>
                <h1 className="text-xl font-semibold text-slate-900">Pembayaran Pendaftaran</h1>
                <p className="text-sm text-slate-500">
                    Tagihan biaya pendaftaran seluruh santri. Bukti yang menunggu verifikasi perlu
                    diperiksa dan diputuskan oleh admin / tata usaha.
                </p>
            </header>

            {!isLoading && !error && (
                <div className="grid gap-4 sm:grid-cols-3">
                    <Card title="Total tagihan">
                        <p className="text-2xl font-semibold text-slate-900">{pembayarans.length}</p>
                    </Card>
                    <Card title="Menunggu verifikasi">
                        <p className="text-2xl font-semibold text-amber-600">{menunggu}</p>
                    </Card>
                    <Card title="Lunas">
                        <p className="text-2xl font-semibold text-emerald-600">{lunas}</p>
                    </Card>
                </div>
            )}

            {error !== null && (
                <p role="alert" className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                    {error}
                </p>
            )}

            {isLoading && <p className="text-sm text-slate-500">Memuat…</p>}

            {!isLoading && !error && pembayarans.length === 0 && (
                <Card title="Belum ada tagihan" description="Belum ada tagihan biaya pendaftaran.">
                    <p className="text-sm text-slate-600">
                        Tagihan dibuat otomatis saat sebuah pendaftaran diterima.
                    </p>
                </Card>
            )}

            {!isLoading && !error && pembayarans.length > 0 && (
                <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="w-full text-left text-sm">
                        <caption className="sr-only">Seluruh tagihan pembayaran pendaftaran</caption>
                        <thead className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="px-4 py-3 font-medium">Nama Santri</th>
                                <th className="px-4 py-3 font-medium">Wali Pengaju</th>
                                <th className="px-4 py-3 font-medium">Nomor Pendaftaran</th>
                                <th className="px-4 py-3 font-medium">Jumlah</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">
                                    <span className="sr-only">Aksi</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {pembayarans.map((pembayaran) => (
                                <tr key={pembayaran.id}>
                                    <td className="px-4 py-3">
                                        <p className="font-medium text-slate-800">{pembayaran.nama_santri}</p>
                                        <p className="text-xs text-slate-500">{pembayaran.tahun_ajaran}</p>
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">
                                        {pembayaran.wali?.name ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-slate-600">
                                        {pembayaran.nomor_pendaftaran ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 font-medium text-slate-900">
                                        {rupiah(pembayaran.jumlah)}
                                    </td>
                                    <td className="px-4 py-3">
                                        <StatusBadge
                                            status={pembayaran.status}
                                            label={pembayaran.status_label}
                                        />
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Link
                                            to={`/admin/pembayaran/${pembayaran.id}`}
                                            className="text-sm font-medium text-brand-700 underline underline-offset-2 hover:text-brand-800"
                                        >
                                            Periksa
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