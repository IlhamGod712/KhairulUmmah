import { useCallback, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router';

import { PendaftaranDetailView } from '@/components/PendaftaranDetailView';
import { describeError, waliApi } from '@/lib/api';
import type { PendaftaranDetail } from '@/types/pendaftaran';

/**
 * One of the guardian's own registrations.
 *
 * The `wali` block is not rendered here. It would be the guardian's own name
 * in a page whose entire purpose is showing them what they submitted, and the
 * admin view next door is where "who filed this" is actually the question.
 */
export default function WaliPendaftaranDetailPage() {
    const { id } = useParams<{ id: string }>();
    const [pendaftaran, setPendaftaran] = useState<PendaftaranDetail | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState(true);

    const muat = useCallback(async () => {
        setIsLoading(true);
        setError(null);

        try {
            setPendaftaran(await waliApi.pendaftaranDetail(Number(id)));
        } catch (cause: unknown) {
            setError(describeError(cause));
        } finally {
            setIsLoading(false);
        }
    }, [id]);

    useEffect(() => {
        void muat();
    }, [muat]);

    return (
        <div className="space-y-6">
            <header>
                <Link
                    to="/wali/pendaftaran"
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
                <PendaftaranDetailView pendaftaran={pendaftaran} />
            )}
        </div>
    );
}
