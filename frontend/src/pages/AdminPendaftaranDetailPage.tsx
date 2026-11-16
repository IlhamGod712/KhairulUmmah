import { useCallback, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router';

import { Card } from '@/components/Card';
import { PendaftaranDetailView } from '@/components/PendaftaranDetailView';
import { TextAreaField } from '@/components/TextAreaField';
import { adminApi, describeError } from '@/lib/api';
import { cn } from '@/lib/utils';
import type { PendaftaranDetail } from '@/types/pendaftaran';

type Status = { kind: 'idle' } | { kind: 'saving' } | { kind: 'saved'; message: string } | { kind: 'error'; message: string };

const IDLE: Status = { kind: 'idle' };

/**
 * One registration as the office sees it, plus the decision.
 *
 * Shares `PendaftaranDetailView` with the guardian's page and turns on
 * `tampilkanWali`, because answering "who submitted this" is the one thing an
 * admin cannot get from the guardian's own copy.
 *
 * The decision form appears only while the registration is undecided
 * (`diajukan` or `diverifikasi`): once decided it is terminal, and the card
 * disappears to say so. Accepting opens the payment lane and moves the student
 * to `menunggu_pembayaran` — it does not activate them.
 */
export default function AdminPendaftaranDetailPage() {
    const { id } = useParams<{ id: string }>();
    const pendaftaranId = Number(id);

    const [pendaftaran, setPendaftaran] = useState<PendaftaranDetail | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState(true);

    const [alasan, setAlasan] = useState('');
    const [alasanError, setAlasanError] = useState<string | undefined>(undefined);
    const [status, setStatus] = useState<Status>(IDLE);

    const muat = useCallback(async () => {
        setIsLoading(true);
        setError(null);

        try {
            setPendaftaran(await adminApi.pendaftaranDetail(pendaftaranId));
        } catch (cause: unknown) {
            setError(describeError(cause));
        } finally {
            setIsLoading(false);
        }
    }, [pendaftaranId]);

    useEffect(() => {
        void muat();
    }, [muat]);

    const belumDiputus =
        pendaftaran?.status === 'diajukan' || pendaftaran?.status === 'diverifikasi';

    async function putus(keputusan: 'diterima' | 'ditolak') {
        if (keputusan === 'ditolak' && alasan.trim() === '') {
            setAlasanError('Alasan penolakan wajib diisi.');
            return;
        }

        setStatus({ kind: 'saving' });
        setAlasanError(undefined);

        try {
            setPendaftaran(
                await adminApi.putusPendaftaran(pendaftaranId, {
                    status: keputusan,
                    ...(keputusan === 'ditolak' ? { alasan_ditolak: alasan.trim() } : {}),
                }),
            );
            setAlasan('');
            setStatus({ kind: 'saved', message: 'Keputusan berhasil disimpan.' });
        } catch (cause: unknown) {
            setStatus({ kind: 'error', message: describeError(cause) });
        }
    }

    return (
        <div className="space-y-6">
            <header>
                <Link
                    to="/admin/pendaftaran"
                    className="text-sm font-medium text-brand-700 underline underline-offset-2 hover:text-brand-800"
                >
                    &larr; Kembali ke daftar pendaftaran
                </Link>
            </header>

            {error !== null && (
                <p role="alert" className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                    {error}
                </p>
            )}

            {isLoading && <p className="text-sm text-slate-500">Memuat…</p>}

            {!isLoading && !error && pendaftaran !== null && (
                <>
                    <PendaftaranDetailView pendaftaran={pendaftaran} tampilkanWali />

                    {status.kind === 'saved' && (
                        <p
                            role="status"
                            className="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-700"
                        >
                            {status.message}
                        </p>
                    )}

                    {belumDiputus && (
                        <Card
                            title="Keputusan Pendaftaran"
                            description="Menerima membuka tagihan biaya pendaftaran bagi wali (santri belum aktif). Menolak meminta alasan yang dilihat wali di detail pendaftaran."
                        >
                            <div className="space-y-4">
                                <TextAreaField
                                    label="Alasan Penolakan"
                                    name="alasan_ditolak"
                                    value={alasan}
                                    onChange={(event) => {
                                        setAlasan(event.target.value);
                                        setAlasanError(undefined);
                                    }}
                                    error={alasanError}
                                    hint="Wajib diisi hanya saat menolak pendaftaran."
                                />

                                {status.kind === 'error' && (
                                    <p role="alert" className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                                        {status.message}
                                    </p>
                                )}

                                <div className="flex flex-wrap gap-3">
                                    <button
                                        type="button"
                                        onClick={() => void putus('diterima')}
                                        disabled={status.kind === 'saving'}
                                        className={cn(
                                            'rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-700',
                                            'disabled:cursor-not-allowed disabled:opacity-60',
                                        )}
                                    >
                                        Terima Pendaftaran
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
                                        Tolak Pendaftaran
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