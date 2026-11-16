import { useCallback, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router';

import { Card } from '@/components/Card';
import { PembayaranDetailView } from '@/components/PembayaranDetailView';
import { TextAreaField } from '@/components/TextAreaField';
import { adminApi, describeError } from '@/lib/api';
import { cn } from '@/lib/utils';
import type { PembayaranListItem } from '@/types/pembayaran';

type Status = { kind: 'idle' } | { kind: 'saving' } | { kind: 'saved'; message: string } | { kind: 'error'; message: string };

const IDLE: Status = { kind: 'idle' };

/**
 * One payment under review, and the admin's verdict on its proof.
 *
 * Both answers come from the same backend state machine: `lunas` settles the
 * fee and activates the student inside one transaction — activation is never a
 * separate endpoint — and `ditolak` reopens the upload lane so the guardian can
 * send a corrected proof. The verdict card only exists while a proof is
 * actually waiting (`bukti.status === 'menunggu'`), matching the conditions the
 * server re-validates under a row lock.
 */
export default function AdminPembayaranDetailPage() {
    const { id } = useParams<{ id: string }>();
    const pembayaranId = Number(id);

    const [pembayaran, setPembayaran] = useState<PembayaranListItem | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState(true);

    const [catatan, setCatatan] = useState('');
    const [catatanError, setCatatanError] = useState<string | undefined>(undefined);
    const [status, setStatus] = useState<Status>(IDLE);

    const muat = useCallback(async () => {
        setIsLoading(true);
        setError(null);

        try {
            setPembayaran(await adminApi.pembayaranDetail(pembayaranId));
        } catch (cause: unknown) {
            setError(describeError(cause));
        } finally {
            setIsLoading(false);
        }
    }, [pembayaranId]);

    useEffect(() => {
        void muat();
    }, [muat]);

    const dapatDiputus = pembayaran?.bukti?.status === 'menunggu';

    async function putus(keputusan: 'lunas' | 'ditolak') {
        if (keputusan === 'ditolak' && catatan.trim() === '') {
            setCatatanError('Catatan wajib diisi saat menolak bukti pembayaran.');
            return;
        }

        setStatus({ kind: 'saving' });
        setCatatanError(undefined);

        try {
            setPembayaran(
                await adminApi.putusPembayaran(pembayaranId, {
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
                    to="/admin/pembayaran"
                    className="text-sm font-medium text-brand-700 underline underline-offset-2 hover:text-brand-800"
                >
                    &larr; Kembali ke daftar pembayaran
                </Link>
            </header>

            {error !== null && (
                <p role="alert" className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                    {error}
                </p>
            )}

            {isLoading && <p className="text-sm text-slate-500">Memuat…</p>}

            {!isLoading && !error && pembayaran !== null && (
                <>
                    <PembayaranDetailView pembayaran={pembayaran} tampilkanWali />

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
                            description="Menerima bukti melunasi tagihan DAN mengaktifkan santri dalam satu langkah. Menolak membuka unggah ulang bagi wali."
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
                                        Konfirmasi LUNAS & Aktifkan
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