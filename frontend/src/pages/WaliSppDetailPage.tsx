import { useCallback, useEffect, useRef, useState, type FormEvent } from 'react';
import { Link, useParams } from 'react-router';

import { Card } from '@/components/Card';
import { FormField } from '@/components/FormField';
import { TagihanSppDetailView } from '@/components/TagihanSppDetailView';
import { describeError, fieldErrors, waliApi } from '@/lib/api';
import { cn } from '@/lib/utils';
import type { TagihanSpp } from '@/types/spp';

type Status = { kind: 'idle' } | { kind: 'saving' } | { kind: 'saved' } | { kind: 'error'; message: string };

const IDLE: Status = { kind: 'idle' };

/**
 * One of the guardian's own monthly SPP invoices, and the way to settle it.
 *
 * The upload form appears only when the server would accept it: the bill is
 * still `belum` or was `ditolak`, which is exactly the pair the backend's state
 * machine allows to move to `menunggu_verifikasi`. Both conditions are read from
 * the freshly fetched row, so the button can never offer an upload the backend
 * would answer with a 422. There is no way to set `lunas` from here — that is an
 * admin verdict.
 */
export default function WaliSppDetailPage() {
    const { id } = useParams<{ id: string }>();
    const tagihanId = Number(id);

    const [tagihan, setTagihan] = useState<TagihanSpp | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState(true);

    const [berkas, setBerkas] = useState<File | null>(null);
    const [referensi, setReferensi] = useState('');
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [status, setStatus] = useState<Status>(IDLE);
    const berkasInput = useRef<HTMLInputElement>(null);

    const muat = useCallback(async () => {
        setIsLoading(true);
        setError(null);

        try {
            setTagihan(await waliApi.tagihanSppDetail(tagihanId));
        } catch (cause: unknown) {
            setError(describeError(cause));
        } finally {
            setIsLoading(false);
        }
    }, [tagihanId]);

    useEffect(() => {
        void muat();
    }, [muat]);

    const dapatUnggah = tagihan !== null && (tagihan.status === 'belum' || tagihan.status === 'ditolak');

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setStatus({ kind: 'saving' });
        setErrors({});

        if (berkas === null) {
            setErrors({ bukti: ['Berkas bukti pembayaran wajib dipilih.'] });
            setStatus({ kind: 'error', message: 'Pilih berkas bukti pembayaran terlebih dahulu.' });
            return;
        }

        try {
            setTagihan(await waliApi.unggahBuktiSpp(tagihanId, { bukti: berkas, referensi }));
            setBerkas(null);
            setReferensi('');
            if (berkasInput.current !== null) {
                berkasInput.current.value = '';
            }
            setStatus({ kind: 'saved' });
        } catch (cause: unknown) {
            setErrors(fieldErrors(cause));
            setStatus({ kind: 'error', message: describeError(cause) });
        }
    }

    return (
        <div className="space-y-6">
            <header>
                <Link
                    to="/wali/spp"
                    className="text-sm font-medium text-brand-700 underline underline-offset-2 hover:text-brand-800"
                >
                    &larr; Kembali ke daftar SPP
                </Link>
            </header>

            {error !== null && (
                <p role="alert" className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                    {error}
                </p>
            )}

            {isLoading && <p className="text-sm text-slate-500">Memuat…</p>}

            {!isLoading && !error && tagihan !== null && (
                <>
                    <TagihanSppDetailView tagihan={tagihan} />

                    {status.kind === 'saved' && (
                        <p
                            role="status"
                            className="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-700"
                        >
                            Bukti pembayaran SPP berhasil diunggah dan menunggu verifikasi admin / tata usaha.
                        </p>
                    )}

                    {tagihan.status === 'ditolak' && tagihan.keterangan !== null && (
                        <div className="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                            <p className="font-medium">Bukti sebelumnya ditolak admin / tata usaha.</p>
                            <p className="mt-1">{tagihan.keterangan}</p>
                        </div>
                    )}

                    {dapatUnggah && (
                        <Card
                            title="Unggah Bukti Pembayaran"
                            description="PDF, JPG, atau PNG maksimal 2 MB. Pastikan bukti memperlihatkan nomor transfer dan jumlah yang jelas."
                        >
                            <form className="space-y-4" onSubmit={handleSubmit} noValidate>
                                <div>
                                    <label
                                        htmlFor="bukti"
                                        className="block text-sm font-medium text-slate-700"
                                    >
                                        Berkas Bukti
                                    </label>
                                    <input
                                        ref={berkasInput}
                                        id="bukti"
                                        name="bukti"
                                        type="file"
                                        accept="application/pdf,image/jpeg,image/png"
                                        onChange={(event) => setBerkas(event.target.files?.[0] ?? null)}
                                        aria-invalid={errors.bukti !== undefined}
                                        aria-describedby={
                                            errors.bukti !== undefined ? 'bukti-error' : undefined
                                        }
                                        className="mt-1 block w-full text-sm text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100"
                                    />
                                    {errors.bukti !== undefined ? (
                                        <p id="bukti-error" className="mt-1 text-xs text-red-600">
                                            {errors.bukti[0]}
                                        </p>
                                    ) : (
                                        <p className="mt-1 text-xs text-slate-500">
                                            Konten berkas diperiksa di server; nama atau ekstensi saja tidak
                                            cukup.
                                        </p>
                                    )}
                                </div>

                                <FormField
                                    label="Referensi Transfer"
                                    name="referensi"
                                    value={referensi}
                                    onChange={(event) => setReferensi(event.target.value)}
                                    error={errors.referensi?.[0]}
                                    placeholder="Contoh: TRF-20260928-001"
                                    hint="Nomor referensi dari aplikasi transfer, boleh dikosongkan."
                                />

                                {status.kind === 'error' && (
                                    <p role="alert" className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                                        {status.message}
                                    </p>
                                )}

                                <button
                                    type="submit"
                                    disabled={status.kind === 'saving'}
                                    className={cn(
                                        'rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-700',
                                        'disabled:cursor-not-allowed disabled:opacity-60',
                                    )}
                                >
                                    {status.kind === 'saving' ? 'Mengunggah…' : 'Unggah Bukti'}
                                </button>
                            </form>
                        </Card>
                    )}

                    {!dapatUnggah && tagihan.status !== 'lunas' && (
                        <Card title="Unggahan tidak tersedia" description="Tagihan sedang ditinjau admin / tata usaha.">
                            <p className="text-sm text-slate-600">
                                Bukti sudah diunggah dan sedang menunggu verifikasi. Anda dapat mengunggah
                                ulang bila admin menolak bukti tersebut.
                            </p>
                        </Card>
                    )}
                </>
            )}
        </div>
    );
}
