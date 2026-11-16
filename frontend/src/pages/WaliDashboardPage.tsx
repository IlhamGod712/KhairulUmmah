import { useEffect, useState } from 'react';
import { Link } from 'react-router';

import { Card } from '@/components/Card';
import { describeError, waliApi } from '@/lib/api';
import { cn } from '@/lib/utils';
import {
    SANTRI_STATUS_LABELS,
    STATUS_PEMBAYARAN_LABELS,
    STATUS_PENDAFTARAN_LABELS,
    type WaliDashboard,
} from '@/types/auth';

/**
 * Format a fixed two-decimal money string for display.
 *
 * `Intl.NumberFormat` handles the thousands grouping and the string is split on
 * the decimal point rather than parsed, so the cents the API sent are the cents
 * shown. Parsing to `Number` would work at this magnitude, but it would also
 * make it look reasonable to do the same to a summed total later, which is how
 * a figure drifts by one cent.
 */
function rupiah(value: string): string {
    const [whole, fraction] = value.split('.');
    const grouped = new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 0,
    }).format(Number(whole));

    return fraction === undefined ? `Rp ${grouped}` : `Rp ${grouped},${fraction}`;
}

interface StatTileProps {
    label: string;
    value: string;
    hint?: string;
    tone?: 'default' | 'warning' | 'positive';
}

function StatTile({ label, value, hint, tone = 'default' }: StatTileProps) {
    return (
        <div
            className={cn(
                'rounded-lg border p-4',
                tone === 'warning'
                    ? 'border-amber-200 bg-amber-50'
                    : tone === 'positive'
                      ? 'border-emerald-200 bg-emerald-50'
                      : 'border-slate-200 bg-white',
            )}
        >
            <p className="text-xs font-medium uppercase tracking-wide text-slate-500">
                {label}
            </p>
            <p
                className={cn(
                    'mt-1 text-xl font-semibold',
                    tone === 'warning'
                        ? 'text-amber-800'
                        : tone === 'positive'
                          ? 'text-emerald-800'
                          : 'text-slate-900',
                )}
            >
                {value}
            </p>
            {hint !== undefined && <p className="mt-1 text-xs text-slate-500">{hint}</p>}
        </div>
    );
}

function StatusBreakdown({
    counts,
    labels,
}: {
    counts: Record<string, number>;
    labels: Record<string, string>;
}) {
    return (
        <ul className="space-y-2">
            {Object.entries(counts).map(([key, value]) => (
                <li key={key} className="flex items-center justify-between text-sm">
                    <span className="text-slate-600">{labels[key] ?? key}</span>
                    <span className="font-medium text-slate-900">{value}</span>
                </li>
            ))}
        </ul>
    );
}

/**
 * First screen a wali lands on after signing in.
 *
 * Every figure comes from `GET /api/wali/dashboard`, which counts only this
 * guardian's own students on the server. Nothing is filtered in the browser: a
 * filter applied here would mean the numbers were computed from rows the browser
 * should never have been sent.
 */
export default function WaliDashboardPage() {
    const [data, setData] = useState<WaliDashboard | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        let active = true;

        waliApi
            .dashboard()
            .then((result) => {
                if (active) {
                    setData(result);
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

    if (error !== null) {
        return (
            <div className="space-y-6">
                <h1 className="text-xl font-semibold text-slate-900">Dashboard Wali</h1>
                <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>
            </div>
        );
    }

    if (isLoading || data === null) {
        return (
            <div className="space-y-6">
                <h1 className="text-xl font-semibold text-slate-900">Dashboard Wali</h1>
                <p className="text-sm text-slate-500">Memuat…</p>
            </div>
        );
    }

    const { wali, ringkasan_santri, ringkasan_pendaftaran, ringkasan_tagihan } = data;

    return (
        <div className="space-y-6">
            <header>
                <h1 className="text-xl font-semibold text-slate-900">Dashboard Wali</h1>
                <p className="text-sm text-slate-500">
                    Halo {wali.name}, berikut ringkasan untuk akun Anda.
                </p>
            </header>

            {!wali.email_verified && (
                <div className="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Email akun Anda belum diverifikasi. Tautan reset password hanya terkirim
                    ke email yang sudah aktif.
                </div>
            )}

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatTile
                    label="Total Santri"
                    value={String(ringkasan_santri.total)}
                    hint="Santri yang tertaut ke akun Anda"
                />
                <StatTile
                    label="Santri Aktif"
                    value={String(ringkasan_santri.per_status.aktif)}
                    tone="positive"
                />
                <StatTile
                    label="Belum Lunas"
                    value={rupiah(ringkasan_tagihan.total_tagihan)}
                    hint={`${ringkasan_tagihan.per_status.belum} tagihan`}
                    tone="warning"
                />
                <StatTile
                    label="Sudah Lunas"
                    value={rupiah(ringkasan_tagihan.total_lunas)}
                    hint={`${ringkasan_tagihan.per_status.lunas} tagihan`}
                    tone="positive"
                />
            </div>

            {ringkasan_santri.total === 0 ? (
                <Card
                    title="Belum ada data"
                    description="Ringkasan muncul setelah ada santri yang tertaut ke akun ini."
                >
                    <p className="text-sm text-slate-600">
                        Belum ada santri yang tertaut ke akun ini. Hubungi admin / tata usaha
                        untuk menghubungkan data Santri Anda ke akun ini.
                    </p>
                </Card>
            ) : (
                <div className="grid gap-4 lg:grid-cols-3">
                    <Card title="Status Santri">
                        <StatusBreakdown
                            counts={ringkasan_santri.per_status}
                            labels={SANTRI_STATUS_LABELS}
                        />
                    </Card>

                    <Card
                        title="Status Pendaftaran"
                        description={`Total ${ringkasan_pendaftaran.total} pengajuan`}
                    >
                        <StatusBreakdown
                            counts={ringkasan_pendaftaran.per_status}
                            labels={STATUS_PENDAFTARAN_LABELS}
                        />
                    </Card>

                    <Card title="Status Pembayaran">
                        <StatusBreakdown
                            counts={ringkasan_tagihan.per_status}
                            labels={STATUS_PEMBAYARAN_LABELS}
                        />
                    </Card>
                </div>
            )}

            <div className="flex flex-wrap gap-3">
                <Link
                    to="/wali/santri"
                    className="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-700"
                >
                    Lihat Data Santri
                </Link>
                <Link
                    to="/wali/profil"
                    className="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 transition hover:bg-slate-50"
                >
                    Kelola Profil
                </Link>
            </div>
        </div>
    );
}
