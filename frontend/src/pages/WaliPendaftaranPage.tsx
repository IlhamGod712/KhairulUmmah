import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router';

import { Card } from '@/components/Card';
import { PendaftaranForm } from '@/components/PendaftaranForm';
import { StatusBadge } from '@/components/StatusBadge';
import { describeError, waliApi } from '@/lib/api';
import { JENIS_KELAMIN_LABELS } from '@/types/auth';
import type { PendaftaranDetail, PendaftaranListItem } from '@/types/pendaftaran';

/**
 * A guardian's registrations, and the form to add one.
 *
 * List and form share a screen on purpose. A registration is a single submission
 * a guardian makes rarely, and splitting it across a list page and a form page
 * means two round trips and a back button to undo a mistake before it is even
 * visible. The form stays collapsed until asked for, so the list is still the
 * thing that greets them.
 *
 * Everything here comes from the API. There is no seeded or placeholder data:
 * an empty list is rendered as an empty list, which is the honest answer when
 * this account has submitted nothing.
 */
export default function WaliPendaftaranPage() {
    const [pendaftarans, setPendaftaran] = useState<PendaftaranListItem[]>([]);
    const [error, setError] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState(true);

    const [formTerbuka, setFormTerbuka] = useState(false);
    const [sukses, setSukses] = useState<string | null>(null);

    const muat = useCallback(async () => {
        setIsLoading(true);
        setError(null);

        try {
            setPendaftaran(await waliApi.pendaftaran());
        } catch (cause: unknown) {
            setError(describeError(cause));
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        void muat();
    }, [muat]);

    /**
     * A fresh registration is a new row, so the list is reloaded from the API
     * rather than patched locally. The server's number and status are the ones
     * on screen, which matters more here than saving a request: this is the
     * moment the guardian wants to see the receipt they were just given.
     */
    const suksesKirim = useCallback(
        (pendaftaran: PendaftaranDetail) => {
            setSukses(
                `Pendaftaran nomor ${pendaftaran.nomor_pendaftaran} berhasil diajukan. ` +
                    'Status saat ini: Diajukan, menunggu verifikasi admin / tata usaha.',
            );
            setFormTerbuka(false);
            void muat();
        },
        [muat],
    );

    return (
        <div className="space-y-6">
            <header className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 className="text-xl font-semibold text-slate-900">Pendaftaran Santri Baru</h1>
                    <p className="text-sm text-slate-500">
                        Ajukan pendaftaran untuk anak Anda. Pendaftaran yang diajukan akan ditinjau oleh admin /
                        tata usaha.
                    </p>
                </div>
                {!formTerbuka && (
                    <button
                        type="button"
                        onClick={() => {
                            setSukses(null);
                            setFormTerbuka(true);
                        }}
                        className="shrink-0 rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-700"
                    >
                        Daftarkan Santri Baru
                    </button>
                )}
            </header>

            {sukses !== null && (
                <div role="status" className="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {sukses}
                </div>
            )}

            {formTerbuka && (
                <Card
                    title="Formulir Pendaftaran"
                    description="Kolom bertanda bintang wajib diisi. Data yang tidak diketahui boleh dikosongkan."
                >
                    <PendaftaranForm onSuccess={suksesKirim} />
                </Card>
            )}

            {error !== null && (
                <p role="alert" className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                    {error}
                </p>
            )}

            {isLoading && <p className="text-sm text-slate-500">Memuat…</p>}

            {!isLoading && !error && pendaftarans.length === 0 && (
                <Card
                    title="Belum ada pendaftaran"
                    description="Belum ada pendaftaran yang diajukan dari akun ini."
                >
                    <p className="text-sm text-slate-600">
                        Gunakan tombol <strong>Daftarkan Santri Baru</strong> untuk mengajukan pendaftaran
                        pertama.
                    </p>
                </Card>
            )}

            {!isLoading && !error && pendaftarans.length > 0 && (
                <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="w-full text-left text-sm">
                        <caption className="sr-only">Daftar pendaftaran yang diajukan dari akun ini</caption>
                        <thead className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="px-4 py-3 font-medium">No. Pendaftaran</th>
                                <th className="px-4 py-3 font-medium">Nama Santri</th>
                                <th className="px-4 py-3 font-medium">Tanggal</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">Pembayaran</th>
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
                                    <td className="px-4 py-3 text-slate-600">{pendaftaran.tanggal_daftar}</td>
                                    <td className="px-4 py-3">
                                        <StatusBadge
                                            status={pendaftaran.status}
                                            label={pendaftaran.status_label}
                                        />
                                    </td>
                                    <td className="px-4 py-3">
                                        <StatusBadge
                                            status={pendaftaran.ringkasan_pembayaran.status}
                                            label={pendaftaran.ringkasan_pembayaran.status_label}
                                        />
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Link
                                            to={`/wali/pendaftaran/${pendaftaran.id}`}
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

            <p className="text-xs leading-relaxed text-slate-500">
                Status pembayaran di atas adalah posisi seluruh pembayaran Santri pada tahun ajaran yang
                berjalan (lunas dibanding total). Rincian biaya pendaftaran ada di menu Pembayaran, dan
                tagihan SPP bulanan ada di menu SPP Bulanan.
            </p>
        </div>
    );
}
