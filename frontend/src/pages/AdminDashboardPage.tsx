import { useEffect, useState } from 'react';

import { Card } from '@/components/Card';
import { useAuth } from '@/context/AuthContext';
import { adminApi, describeError } from '@/lib/api';
import type { AdminOverview } from '@/types/auth';

const STATS = [
    { key: 'total_santri', label: 'Santri terdaftar' },
    { key: 'total_pendaftaran', label: 'Pendaftaran' },
    { key: 'total_pembayaran', label: 'Pembayaran' },
] as const;

/**
 * First screen an admin lands on after signing in.
 *
 * Reads the admin-only endpoint, so the counters double as a live check that
 * the role guard rejects a wali and that a wali's endpoint rejects an admin.
 */
export default function AdminDashboardPage() {
    const { user } = useAuth();

    const [overview, setOverview] = useState<AdminOverview | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        let active = true;

        adminApi
            .overview()
            .then((result) => {
                if (active) {
                    setOverview(result);
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
                <h1 className="text-xl font-semibold text-slate-900">
                    Dashboard Admin / Tata Usaha
                </h1>
                <p className="text-sm text-slate-500">
                    Halo {user?.name}, berikut ringkasan untuk{' '}
                    {overview?.tahun_ajaran_aktif ?? 'tahun ajaran berjalan'}.
                </p>
            </header>

            <Card title="Ringkasan" description="Angka dari endpoint khusus admin.">
                {error !== null && (
                    <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                        {error}
                    </p>
                )}

                {!error && isLoading && <p className="text-sm text-slate-500">Memuat…</p>}

                {!error && overview !== null && (
                    <dl className="grid gap-4 sm:grid-cols-3">
                        {STATS.map((stat) => (
                            <div
                                key={stat.key}
                                className="rounded-lg border border-slate-200 p-4"
                            >
                                <dt className="text-xs uppercase tracking-wide text-slate-500">
                                    {stat.label}
                                </dt>
                                <dd className="mt-1 text-2xl font-semibold text-slate-900">
                                    {overview[stat.key]}
                                </dd>
                            </div>
                        ))}
                    </dl>
                )}
            </Card>
        </div>
    );
}
