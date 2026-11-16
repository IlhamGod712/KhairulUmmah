import { useCallback, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router';

import { Card } from '@/components/Card';
import { TagihanSppDetailView } from '@/components/TagihanSppDetailView';
import { TextAreaField } from '@/components/TextAreaField';
import { adminApi, describeError } from '@/lib/api';
import { cn } from '@/lib/utils';
import type { TagihanSpp } from '@/types/spp';

type Status = { kind: 'idle' } | { kind: 'saving' } | { kind: 'saved'; message: string } | { kind: 'error'; message: string };

const IDLE: Status = { kind: 'idle' };

/**
 * One SPP invoice under review, and the admin's verdict on its proof.
 *
 * The verdict card only exists while the bill is actually waiting
 * (`menunggu_verifikasi`), matching the state the server re-validates under a row
 * lock. Confirming marks it `lunas`; refusing reopens the upload lane for a
 * corrected proof. Neither touches the student — they were already active when
 * the bill was raised, so there is no activation step here, unlike the
 * registration-fee verdict.
 */
export default function AdminSppDetailPage() {
    const { id } = useParams<{ id: string }>();
    const tagihanId = Number(id);

    const [tagihan, setTagihan] = useState<TagihanSpp | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState(true);

    const [catatan, setCatatan] = useState('');
    const [catatanError, setCatatanError] = useState<string | undefined>(undefined);
    const [status, setStatus] = useState<Status>(IDLE);

    const muat = useCallback(async () => {
        setIsLoading(true);
        setError(null);

        try {
            setTagihan(await adminApi.tagihanSppDetail(tagihanId));
        } catch (cause: unknown) {
            setError(describeError(cause));
        } finally {
            setIsLoading(false);
        }
    }, [tagihanId]);

    useEffect(() => {
        void muat();
    }, [muat]);

    const dapatDiputus = tagihan?.status === 'menunggu_verifikasi';

    async function putus(keputusan: 'lunas' | 'ditolak') {
        if (keputusan === 'ditolak' && catatan.trim() === '') {
            setCatatanError('Catatan wajib diisi saat menolak bukti pembayaran.');
            return;
        }

        setStatus({ kind: 'saving' });
        setCatatanError(undefined);

        try {
            setTagihan(
                await adminApi.putusTagihanSpp(tagihanId, {
                    status: keputusan,
                    catatan: catatan.trim() === '' ? null : catatan.trim(),
                }),
            );
            setCatatan('');
            setStatus({ kind: 'saved', message: 'Keputusan berhasil disimpan.' });
        } catch (cause: unknown) {
            setStatus({ kind: 'error', message: describeError(cause) });
        }
    }

    return (
        <div className="space-y-6">
            <header>
                <Link
                    to="/admin/spp"
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
                    <TagihanSppDetailView tagihan={tagihan} tampilkanWali />

                    {status.kind === 'saved' && (
                        <p
                            role="status"
                            className="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-700"
                        >
                            {status.message}
                        </p>
                    )}

                    {dapatDiputus && (
                        <Card
                            title="Keputusan Bukti Pembayaran"
                            description="Mengonfirmasi menandai tagihan bulan ini LUNAS. Menolak membuka unggah ulang bagi wali. Tidak ada perubahan status santri pada langkah ini."
                        >
                            <div className="space-y-4">
                                <TextAreaField
                                    label="Catatan"
                                    name="catatan"
                                    value={catatan}
                                    onChange={(event) => {
                                        setCatatan(event.target.value);
                                        setCatatanError(undefined);
                                    }}
                                    error={catatanError}
                                    hint="Wajib diisi saat menolak. Boleh diisi saat menerima sebagai keterangan verifikasi."
                                />

                                {status.kind === 'error' && (
                                    <p role="alert" className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                                        {status.message}
                                    </p>
                                )}

                                <div className="flex flex-wrap gap-3">
                                    <button
                                        type="button"
                                        onClick={() => void putus('lunas')}
                                        disabled={status.kind === 'saving'}
                                        className={cn(
                                            'rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-700',
                                            'disabled:cursor-not-allowed disabled:opacity-60',
                                        )}
                                    >
                                        Konfirmasi LUNAS
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => void putus('ditolak')}
                                        disabled={status.kind === 'saving'}
                                        className={cn(
                                            'rounded-md border border-rose-300 px-4 py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-50',
                                            'disabled:cursor-not-allowed disabled:opacity-60',
                                        )}
                                    >
                                        Tolak Bukti
                                    </button>
                                </div>
                            </div>
                        </Card>
                    )}
                </>
            )}
        </div>
    );
}
