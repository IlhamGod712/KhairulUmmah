import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router';

import { Card } from '@/components/Card';
import { StatusBadge } from '@/components/StatusBadge';
import { describeError, waliApi } from '@/lib/api';
import { rupiah } from '@/lib/utils';
import type { PembayaranListItem } from '@/types/pembayaran';

/**
 * The registration-fee payments for this guardian's own students.
 *
 * Every row is the guardian's own: the backend resolves ownership through the
 * session, so a payment of somebody else's child never reaches this list (or
 * the detail endpoint, which answers 404 for it). The list answers "what fees
 * do I owe / what is the school reviewing" without opening each one.
 */
export default function WaliPembayaranPage() {
    const [pembayarans, setPembayaran] = useState<PembayaranListItem[]>([]);
    const [error, setError] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState(true);

    const muat = useCallback(async () => {
        setIsLoading(true);
        setError(null);

        try {
            setPembayaran(await waliApi.pembayaran());
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
                <h1 className="text-xl font-semibold text-slate-900">Pembayaran</h1>
                <p className="text-sm text-slate-500">
                    Tagihan biaya pendaftaran untuk santri yang tertaut ke akun Anda. Tagihan dibuat setelah
                    pendaftaran diterima admin / tata usaha.
                </p>
            </header>

            {!isLoading && !error && (
                <div className="grid gap-4 sm:grid-cols-3">
                    <Card title="Total tagihan">
                        <p className="text-2xl font-semibold text-slate-900">{pembayarans.length}</p>
                    </Card>
                    <Card title="Menunggu verifikasi">
                        <p className="text-2xl font-semibold text-sky-600">{menunggu}</p>
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
                <Card title="Belum ada tagihan" description="Belum ada tagihan untuk akun ini.">
                    <p className="text-sm text-slate-600">
                        Tagihan biaya pendaftaran dibuat otomatis begitu sebuah pendaftaran diterima admin.
                    </p>
                </Card>
            )}

            {!isLoading && !error && pembayarans.length > 0 && (
                <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="w-full text-left text-sm">
                        <caption className="sr-only">Tagihan pembayaran pendaftaran akun ini</caption>
                        <thead className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="px-4 py-3 font-medium">Nama Santri</th>
                                <th className="px-4 py-3 font-medium">Nomor Pendaftaran</th>
                                <th className="px-4 py-3 font-medium">Jumlah</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">Tanggal Bayar</th>
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
                                    <td className="px-4 py-3 text-slate-600">
                                        {pembayaran.tanggal_bayar ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Link
                                            to={`/wali/pembayaran/${pembayaran.id}`}
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