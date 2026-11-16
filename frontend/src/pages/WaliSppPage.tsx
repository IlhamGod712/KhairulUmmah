import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router';

import { Card } from '@/components/Card';
import { StatusBadge } from '@/components/StatusBadge';
import { describeError, waliApi } from '@/lib/api';
import { rupiah } from '@/lib/utils';
import type { TagihanSpp } from '@/types/spp';

/**
 * The monthly SPP invoices for this guardian's own students.
 *
 * Every row is the guardian's own: ownership is resolved from the session on
 * the server, so another family's invoice never reaches this list — or the
 * detail endpoint, which answers 404 for it. The page answers "which months do I
 * still owe for", newest month first, with the state of each bill.
 *
 * A bill exists only for a student the school has activated, so a newly
 * submitted registration has nothing here until the office has accepted it and
 * confirmed the registration fee.
 */
export default function WaliSppPage() {
    const [tagihan, setTagihan] = useState<TagihanSpp[]>([]);
    const [error, setError] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState(true);

    const muat = useCallback(async () => {
        setIsLoading(true);
        setError(null);

        try {
            setTagihan(await waliApi.tagihanSpp());
        } catch (cause: unknown) {
            setError(describeError(cause));
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        void muat();
    }, [muat]);

    const menunggu = tagihan.filter((t) => t.status === 'menunggu_verifikasi').length;
    const lunas = tagihan.filter((t) => t.status === 'lunas').length;
    const belum = tagihan.filter((t) => t.status === 'belum' || t.status === 'ditolak').length;

    return (
        <div className="space-y-6">
            <header>
                <h1 className="text-xl font-semibold text-slate-900">SPP Bulanan</h1>
                <p className="text-sm text-slate-500">
                    Tagihan SPP bulanan untuk santri yang tertaut ke akun Anda. Setiap bulan ditagih
                    tersendiri dan menyimpan bukti pembayarannya sendiri.
                </p>
            </header>

            {!isLoading && !error && (
                <div className="grid gap-4 sm:grid-cols-4">
                    <Card title="Total tagihan">
                        <p className="text-2xl font-semibold text-slate-900">{tagihan.length}</p>
                    </Card>
                    <Card title="Belum dibayar">
                        <p className="text-2xl font-semibold text-amber-600">{belum}</p>
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

            {!isLoading && !error && tagihan.length === 0 && (
                <Card title="Belum ada tagihan SPP" description="Belum ada tagihan untuk akun ini.">
                    <p className="text-sm text-slate-600">
                        Tagihan SPP dibuat oleh admin / tata usaha untuk setiap santri yang sudah berstatus
                        Aktif.
                    </p>
                </Card>
            )}

            {!isLoading && !error && tagihan.length > 0 && (
                <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="w-full text-left text-sm">
                        <caption className="sr-only">Tagihan SPP akun ini</caption>
                        <thead className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="px-4 py-3 font-medium">Periode</th>
                                <th className="px-4 py-3 font-medium">Nama Santri</th>
                                <th className="px-4 py-3 font-medium">Jumlah</th>
                                <th className="px-4 py-3 font-medium">Jatuh Tempo</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">
                                    <span className="sr-only">Aksi</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {tagihan.map((item) => (
                                <tr key={item.id}>
                                    <td className="px-4 py-3 font-medium text-slate-800">
                                        {item.periode_label}
                                    </td>
                                    <td className="px-4 py-3">
                                        <p className="text-slate-800">{item.nama_santri}</p>
                                        <p className="font-mono text-xs text-slate-500">{item.nis}</p>
                                    </td>
                                    <td className="px-4 py-3 font-medium text-slate-900">
                                        {rupiah(item.jumlah)}
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">{item.jatuh_tempo ?? '—'}</td>
                                    <td className="px-4 py-3">
                                        <StatusBadge status={item.status} label={item.status_label} />
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Link
                                            to={`/wali/spp/${item.id}`}
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
