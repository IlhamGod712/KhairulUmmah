import { useCallback, useState, type FormEvent } from 'react';
import { Link } from 'react-router';

import { Card } from '@/components/Card';
import { FormField } from '@/components/FormField';
import { describeError, fieldErrors, waliApi } from '@/lib/api';
import { cn } from '@/lib/utils';
import {
    JENIS_KELAMIN_LABELS,
    type SantriLamaPayload,
    type SantriListItem,
} from '@/types/auth';

type Alur =
    | { kind: 'form' }
    | { kind: 'mencari' }
    | { kind: 'tidak_ditemukan' }
    | { kind: 'ditemukan' }
    | { kind: 'menyimpan' }
    | { kind: 'tersimpan' }
    | { kind: 'error'; message: string };

const KOSONG: SantriLamaPayload = { nis_atau_nisn: '', nama_lengkap: '', tanggal_lahir: '' };

/**
 * Claim an existing, previously unlinked student record (Phase 7).
 *
 * One identification form, three fields, all three used by the backend to
 * find the student. When nothing matches, the flow stops at the
 * "Data Santri Tidak Ditemukan" message: the form is hidden and the only way
 * forward is a reset, so there is no masukkan-ulang-retry loop to fall into.
 * When a match lands, the found student is shown read-only with one action,
 * "Daftarkan Data Santri", which links the row to this account by re-sending
 * the same three fields — the backend re-derives the student and takes the
 * guardian from the session, so nothing on the client can pick a different
 * row or owner.
 */
export default function WaliSantriLamaPage() {
    const [form, setForm] = useState<SantriLamaPayload>(KOSONG);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [alur, setAlur] = useState<Alur>({ kind: 'form' });
    const [santri, setSantri] = useState<SantriListItem | null>(null);

    const reset = useCallback(() => {
        setForm(KOSONG);
        setErrors({});
        setSantri(null);
        setAlur({ kind: 'form' });
    }, []);

    const setField = (field: keyof SantriLamaPayload, value: string) => {
        setForm((sekarang) => ({ ...sekarang, [field]: value }));
    };

    async function handleCari(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setErrors({});
        setAlur({ kind: 'mencari' });

        try {
            const hasil = await waliApi.matchSantriLama(form);

            if (hasil === null) {
                setSantri(null);
                setAlur({ kind: 'tidak_ditemukan' });
            } else {
                setSantri(hasil);
                setAlur({ kind: 'ditemukan' });
            }
        } catch (cause: unknown) {
            setErrors(fieldErrors(cause));
            setAlur({ kind: 'error', message: describeError(cause) });
        }
    }

    async function handleDaftarkan() {
        setAlur({ kind: 'menyimpan' });

        try {
            setSantri(await waliApi.registerSantriLama(form));
            setAlur({ kind: 'tersimpan' });
        } catch (cause: unknown) {
            setAlur({ kind: 'error', message: describeError(cause) });
        }
    }

    const sedangMencari = alur.kind === 'mencari';
    const sedangMenyimpan = alur.kind === 'menyimpan';
    const bentukTampil = alur.kind === 'form' || sedangMencari || (alur.kind === 'error' && santri === null);

    return (
        <div className="space-y-6">
            <header>
                <h1 className="text-xl font-semibold text-slate-900">Registrasi Santri Lama</h1>
                <p className="text-sm text-slate-500">
                    Hubungkan data santri yang sudah terdaftar di pondok ke akun ini.
                </p>
            </header>

            {alur.kind === 'error' && (
                <p role="alert" className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                    {alur.message}
                </p>
            )}

            {alur.kind === 'tidak_ditemukan' && (
                <Card
                    title="Data Santri Tidak Ditemukan"
                    description="Tidak ada data santri yang cocok dengan NIS/NISN, nama, dan tanggal lahir tersebut."
                >
                    <p className="text-sm text-slate-600">
                        Periksa kembali NIS/NISN, nama, dan tanggal lahir pada data santri yang Anda miliki.
                        Proses berhenti di sini — tidak ada kolom tambahan.
                    </p>
                    <button
                        type="button"
                        onClick={reset}
                        className="mt-4 rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-600 transition hover:bg-slate-50"
                    >
                        Kembali ke formulir
                    </button>
                </Card>
            )}

            {bentukTampil && (
                <Card
                    title="Formulir Identifikasi"
                    description="Isi ketiga data berikut persis seperti yang tercatat di pondok."
                >
                    <form className="space-y-4" onSubmit={handleCari} noValidate>
                        <FormField
                            label="NIS/NISN"
                            name="nis_atau_nisn"
                            value={form.nis_atau_nisn}
                            onChange={(event) => setField('nis_atau_nisn', event.target.value)}
                            error={errors.nis_atau_nisn?.[0]}
                            placeholder="Contoh: P2600001 atau 31512026001"
                            hint="Ketik NIS atau NISN, mana yang Anda ketahui."
                            disabled={sedangMencari}
                        />

                        <FormField
                            label="Nama Santri"
                            name="nama_lengkap"
                            value={form.nama_lengkap}
                            onChange={(event) => setField('nama_lengkap', event.target.value)}
                            error={errors.nama_lengkap?.[0]}
                            placeholder="Nama lengkap sesuai catatan pondok"
                            disabled={sedangMencari}
                        />

                        <FormField
                            label="Tanggal Lahir"
                            name="tanggal_lahir"
                            type="date"
                            value={form.tanggal_lahir}
                            onChange={(event) => setField('tanggal_lahir', event.target.value)}
                            error={errors.tanggal_lahir?.[0]}
                            disabled={sedangMencari}
                        />

                        <button
                            type="submit"
                            disabled={sedangMencari}
                            className={cn(
                                'rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-700',
                                'disabled:cursor-not-allowed disabled:opacity-60',
                            )}
                        >
                            {sedangMencari ? 'Mencari…' : 'Cari Data Santri'}
                        </button>
                    </form>
                </Card>
            )}

            {santri !== null && (
                <Card title="Data Santri" description="Data berikut adalah milik santri yang ditemukan.">
                    <dl className="grid grid-cols-1 gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-xs uppercase tracking-wide text-slate-500">Nama</dt>
                            <dd className="mt-0.5 font-medium text-slate-900">{santri.nama_lengkap}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase tracking-wide text-slate-500">NIS</dt>
                            <dd className="mt-0.5 font-medium text-slate-900">{santri.nis}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase tracking-wide text-slate-500">NISN</dt>
                            <dd className="mt-0.5 font-medium text-slate-900">{santri.nisn ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase tracking-wide text-slate-500">Jenis Kelamin</dt>
                            <dd className="mt-0.5 font-medium text-slate-900">
                                {JENIS_KELAMIN_LABELS[santri.jenis_kelamin]}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase tracking-wide text-slate-500">Tempat Lahir</dt>
                            <dd className="mt-0.5 font-medium text-slate-900">{santri.tempat_lahir}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase tracking-wide text-slate-500">Tanggal Lahir</dt>
                            <dd className="mt-0.5 font-medium text-slate-900">{santri.tanggal_lahir}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase tracking-wide text-slate-500">Status</dt>
                            <dd className="mt-0.5 font-medium text-slate-900">{santri.status_label}</dd>
                        </div>
                    </dl>
                </Card>
            )}

            {alur.kind === 'tersimpan' && (
                <div className="space-y-4">
                    <p
                        role="status"
                        className="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-700"
                    >
                        Data Santri berhasil dihubungkan ke akun Anda.
                    </p>
                    <Link
                        to="/wali/santri"
                        className="inline-block rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-700"
                    >
                        Lihat Data Santri Saya
                    </Link>
                </div>
            )}

            {alur.kind === 'ditemukan' && (
                <div className="flex flex-wrap gap-3">
                    <button
                        type="button"
                        onClick={() => void handleDaftarkan()}
                        disabled={sedangMenyimpan}
                        className={cn(
                            'rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-700',
                            'disabled:cursor-not-allowed disabled:opacity-60',
                        )}
                    >
                        {sedangMenyimpan ? 'Menyimpan…' : 'Daftarkan Data Santri'}
                    </button>
                    <button
                        type="button"
                        onClick={reset}
                        disabled={sedangMenyimpan}
                        className="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-50"
                    >
                        Cari yang lain
                    </button>
                </div>
            )}

            {alur.kind === 'error' && santri !== null && (
                <div className="flex flex-wrap gap-3">
                    <button
                        type="button"
                        onClick={() => void handleDaftarkan()}
                        disabled={sedangMenyimpan}
                        className={cn(
                            'rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-700',
                            'disabled:cursor-not-allowed disabled:opacity-60',
                        )}
                    >
                        {sedangMenyimpan ? 'Menyimpan…' : 'Coba Daftarkan Lagi'}
                    </button>
                    <button
                        type="button"
                        onClick={reset}
                        disabled={sedangMenyimpan}
                        className="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-50"
                    >
                        Cari yang lain
                    </button>
                </div>
            )}
        </div>
    );
}