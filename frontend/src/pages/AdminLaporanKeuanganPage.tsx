import { useCallback, useEffect, useState } from "react";

import { Card } from "@/components/Card";
import { StatusBadge } from "@/components/StatusBadge";
import { adminApi, describeError } from "@/lib/api";
import { rupiah } from "@/lib/utils";
import type { LaporanFilter, LaporanReport, LaporanStatusPembayaran, LaporanItem } from "@/types/laporan";

export default function AdminLaporanKeuanganPage() {
    const [report, setReport] = useState<LaporanReport | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [filter, setFilter] = useState<LaporanFilter>({ per_page: 25 });

    const muat = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            setReport(await adminApi.laporan(filter));
        } catch (cause: unknown) {
            setError(describeError(cause));
        } finally {
            setIsLoading(false);
        }
    },
    [filter],
    );

    useEffect(() => {
        void muat();
    },
    [muat],
    );

    const onExport = async () => {
        try {
            await adminApi.laporanExport(filter);
        } catch (cause: unknown) {
            setError(describeError(cause));
        }
    };

    const items = report?.items ?? [];
    const ringkasan = report?.ringkasan ?? null;

    return (
        <div className="space-y-6">
            <header>
                <h1 className="text-xl font-semibold text-slate-900">Laporan Keuangan</h1>
                <p className="mt-1 text-sm text-slate-500">
                    Rekap pembayaran pendaftaran dan SPP. Hanya status lunas yang dihitung
                    sebagai pemasukan terverifikasi.
                </p>
            </header>

            {error !== null && (
                <p role="alert" className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                    {error}
                </p>
            )}

            <Card title="Ringkasan">
                {isLoading || ringkasan === null ? (
                    <p className="text-sm text-slate-500">Memuat...</p>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <p className="text-xs uppercase tracking-wide text-slate-500">Total transaksi</p>
                            <p className="mt-1 text-2xl font-semibold text-slate-900">{ringkasan.total_transaksi}</p>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-wide text-slate-500">Total nominal</p>
                            <p className="mt-1 text-2xl font-semibold text-slate-900">{rupiah(ringkasan.total_nominal)}</p>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-wide text-slate-500">Terverifikasi</p>
                            <p className="mt-1 text-2xl font-semibold text-emerald-600">{rupiah(ringkasan.terverifikasi.nominal)}</p>
                            <p className="text-xs text-slate-500">{ringkasan.terverifikasi.jumlah} transaksi</p>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-wide text-slate-500">Menunggu verifikasi</p>
                            <p className="mt-1 text-2xl font-semibold text-amber-600">{rupiah(ringkasan.menunggu_verifikasi.nominal)}</p>
                            <p className="text-xs text-slate-500">{ringkasan.menunggu_verifikasi.jumlah} transaksi</p>
                        </div>
                    </div>
                )}
            </Card>

            <Card title="Transaksi">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <label className="text-sm text-slate-700">
                            Tanggal awal
                            <input
                                type="date"
                                value={filter.tanggal_awal ?? ""}
                                onChange={(e) => setFilter({ ...filter, tanggal_awal: e.target.value || undefined })}
                                className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                            />
                        </label>
                        <label className="text-sm text-slate-700">
                            Tanggal akhir
                            <input
                                type="date"
                                value={filter.tanggal_akhir ?? ""}
                                onChange={(e) => setFilter({ ...filter, tanggal_akhir: e.target.value || undefined })}
                                className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                            />
                        </label>
                        <label className="text-sm text-slate-700">
                            Jenis pembayaran
                            <select
                                value={filter.jenis_pembayaran ?? ""}
                                onChange={(e) => setFilter({ ...filter, jenis_pembayaran: (e.target.value || undefined) as LaporanFilter["jenis_pembayaran"] })}
                                className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                            >
                                <option value="">Semua</option>
                                <option value="pendaftaran">Biaya Pendaftaran</option>
                                <option value="spp">SPP Bulanan</option>
                            </select>
                        </label>
                        <label className="text-sm text-slate-700">
                            Status
                            <select
                                value={filter.status ?? ""}
                                onChange={(e) => setFilter({ ...filter, status: (e.target.value || undefined) as LaporanStatusPembayaran })}
                                className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                            >
                                <option value="">Semua</option>
                                <option value="lunas">Lunas</option>
                                <option value="menunggu_verifikasi">Menunggu Verifikasi</option>
                                <option value="ditolak">Ditolak</option>
                                <option value="belum">Belum Bayar</option>
                            </select>
                        </label>
                    </div>
                    <div className="mt-4 flex items-center gap-2">
                        <button
                            type="button"
                            onClick={() => void muat()}
                            className="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700"
                        >
                            Muat ulang
                        </button>
                        <button
                            type="button"
                            onClick={() => void onExport()}
                            className="rounded-md bg-brand-600 px-3 py-2 text-sm text-white"
                        >
                            Ekspor CSV
                        </button>
                    </div>
                </div>

                {isLoading ? (
                    <p className="mt-4 text-sm text-slate-500">Memuat...</p>
                ) : error !== null ? (
                    <p className="mt-4 text-sm text-red-700">{error}</p>
                ) : items.length === 0 ? (
                    <p className="mt-4 text-sm text-slate-500">Tidak ada transaksi untuk filter ini.</p>
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                        <table className="w-full text-left text-sm">
                            <caption className="sr-only">Transaksi keuangan</caption>
                            <thead className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Tanggal</th>
                                    <th className="px-4 py-3 font-medium">Jenis</th>
                                    <th className="px-4 py-3 font-medium">Periode</th>
                                    <th className="px-4 py-3 font-medium">NIS</th>
                                    <th className="px-4 py-3 font-medium">Nama Santri</th>
                                    <th className="px-4 py-3 font-medium">Nominal</th>
                                    <th className="px-4 py-3 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {items.map((item: LaporanItem) => (
                                    <tr key={item.id}>
                                        <td className="px-4 py-3 text-slate-600">{item.tanggal_transaksi ?? ""}</td>
                                        <td className="px-4 py-3 text-slate-600">{item.jenis_pembayaran_label}</td>
                                        <td className="px-4 py-3 text-slate-600">{item.periode_label ?? ""}</td>
                                        <td className="px-4 py-3 font-mono text-xs text-slate-600">{item.nis ?? ""}</td>
                                        <td className="px-4 py-3 text-slate-600">{item.nama_santri ?? ""}</td>
                                        <td className="px-4 py-3 font-medium text-slate-900">{rupiah(item.jumlah)}</td>
                                        <td className="px-4 py-3">
                                            <StatusBadge status={item.status} label={item.status_label} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            {report !== null && report.pagination !== null && report.pagination.last_page > 1 && (
                <div className="flex items-center justify-between">
                    <p className="text-sm text-slate-500">
                        Halaman {report.pagination.current_page} dari {report.pagination.last_page}
                    </p>
                    <button
                        type="button"
                        disabled={report.pagination.current_page <= 1}
                        onClick={() => setFilter({ ...filter, per_page: report.pagination.per_page })}
                        className="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 disabled:opacity-50"
                    >
                        Sebelumnya
                    </button>
                </div>
            )}
        </div>
    );
}
