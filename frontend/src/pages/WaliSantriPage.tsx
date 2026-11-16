import { useEffect, useState } from 'react';
import { Link } from 'react-router';

import { Card } from '@/components/Card';
import { describeError, waliApi } from '@/lib/api';
import {
    JENIS_KELAMIN_LABELS,
    type SantriListItem,
} from '@/types/auth';

/**
 * The students linked to the signed-in guardian's account.
 *
 * Read only. Submitting a registration, paying the registration fee, and paying
 * monthly SPP each have their own section under the guardian portal, and this
 * page deliberately offers no write action rather than a button that would
 * duplicate them. Uploading the other required documents is not available yet.
 */
export default function WaliSantriPage() {
    const [santris, setSantris] = useState<SantriListItem[]>([]);
    const [error, setError] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        let active = true;

        waliApi
            .santri()
            .then((result) => {
                if (active) {
                    setSantris(result);
                }
            })
            .catch((cause: unknown) => {
                if (active) {
                    setError(describeError(cause));
                }
            })
            .finally(() => {
                if (active) {
                    setIsLoading(false);
                }
            });

        return () => {
            active = false;
        };
    }, []);

    return (
        <div className="space-y-6">
            <header>
                <h1 className="text-xl font-semibold text-slate-900">Data Santri Saya</h1>
                <p className="text-sm text-slate-500">
                    Hanya menampilkan santri yang tertaut ke akun ini.
                </p>
            </header>

            {error !== null && (
                <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>
            )}

            {!error && isLoading && <p className="text-sm text-slate-500">Memuat…</p>}

            {!error && !isLoading && santris.length === 0 && (
                <Card
                    title="Belum ada data"
                    description="Daftar ini terisi otomatis dari data Santri yang ditautkan ke akun Anda."
                >
                    <p className="text-sm text-slate-600">
                        Gunakan menu <Link className="text-brand-700 underline underline-offset-2" to="/wali/santri-lama">Registrasi Santri Lama</Link>{' '}
                        untuk menghubungkan data santri Anda yang sudah terdaftar di pondok ke akun ini.
                    </p>
                </Card>
            )}

            {!error && santris.length > 0 && (
                <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="px-4 py-3 font-medium">NIS</th>
                                <th className="px-4 py-3 font-medium">Nama</th>
                                <th className="px-4 py-3 font-medium">Jenis Kelamin</th>
                                <th className="px-4 py-3 font-medium">Asal</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">Tahun Ajaran</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {santris.map((santri) => (
                                <tr key={santri.id}>
                                    <td className="px-4 py-3 text-slate-500">{santri.nis}</td>
                                    <td className="px-4 py-3 font-medium text-slate-800">
                                        {santri.nama_lengkap}
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">
                                        {JENIS_KELAMIN_LABELS[santri.jenis_kelamin]}
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">
                                        {santri.tempat_lahir}, {santri.tanggal_lahir}
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">
                                        {santri.status_label}
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">
                                        {santri.tahun_ajaran ?? '—'}
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
