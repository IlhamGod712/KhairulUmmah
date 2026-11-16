import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { Link } from 'react-router';

import { Card } from '@/components/Card';
import { SelectField } from '@/components/SelectField';
import { StatusBadge } from '@/components/StatusBadge';
import { adminApi, describeError, fieldErrors } from '@/lib/api';
import { cn, rupiah } from '@/lib/utils';
import { STATUS_PEMBAYARAN_LABELS, type StatusPembayaran } from '@/types/auth';
import type { FilterSpp, HasilGenerateSpp, TagihanSpp, TahunAjaranOption } from '@/types/spp';

/**
 * The admin's monthly SPP queue: raise a month, then drain the review queue.
 *
 * Raising a month is idempotent on the server, so the "Buat tagihan" control is
 * safe to press twice — the answer says how many were new versus already there
 * rather than billing anybody a second time. There is no amount field: the price
 * is the school's setting, and the payload has nowhere to put one.
 *
 * The filters narrow the query server-side. A malformed filter is a 422, not an
 * empty table, so an empty result here always means "no invoice matches a real
 * filter" rather than "your filter was ignored".
 */
export default function AdminSppPage() {
    const [tagihan, setTagihan] = useState<TagihanSpp[]>([]);
    const [tahunAjaran, setTahunAjaran] = useState<TahunAjaranOption[]>([]);
    const [filter, setFilter] = useState<FilterSpp>({});
    const [error, setError] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState(true);

    const [periode, setPeriode] = useState('');
    const [tahunUntukBuat, setTahunUntukBuat] = useState('');
    const [buatStatus, setBuatStatus] = useState<
        { kind: 'idle' } | { kind: 'saving' } | { kind: 'done'; hasil: HasilGenerateSpp } | { kind: 'error'; message: string }
    >({ kind: 'idle' });
    const [buatErrors, setBuatErrors] = useState<Record<string, string[]>>({});

    const muat = useCallback(async (aktif: FilterSpp) => {
        setIsLoading(true);
        setError(null);

        try {
            setTagihan(await adminApi.tagihanSpp(aktif));
        } catch (cause: unknown) {
            setError(describeError(cause));
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        void muat(filter);
    }, [muat, filter]);

    useEffect(() => {
        void adminApi
            .tahunAjaranSpp()
            .then(setTahunAjaran)
            .catch(() => setTahunAjaran([]));
    }, []);

    async function handleBuat(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setBuatStatus({ kind: 'saving' });
        setBuatErrors({});

        try {
            const hasil = await adminApi.generateSpp({
                periode,
                tahun_ajaran_id: tahunUntukBuat === '' ? undefined : Number(tahunUntukBuat),
            });
            setBuatStatus({ kind: 'done', hasil });
            await muat(filter);
        } catch (cause: unknown) {
            setBuatErrors(fieldErrors(cause));
            setBuatStatus({ kind: 'error', message: describeError(cause) });
        }
    }

    const menunggu = tagihan.filter((t) => t.status === 'menunggu_verifikasi').length;

    return (
        <div className="space-y-6">
            <header>
                <h1 className="text-xl font-semibold text-slate-900">SPP Bulanan</h1>
                <p className="text-sm text-slate-500">
                    Tagihan SPP seluruh santri aktif. Buat tagihan satu bulan, lalu periksa bukti yang
                    menunggu verifikasi.
                </p>
            </header>

            <Card
                title="Buat Tagihan SPP"
                description="Membuat satu tagihan untuk setiap santri berstatus Aktif pada tahun ajaran yang dipilih. Menekan tombol dua kali tidak menggandakan tagihan."
            >
                <form className="space-y-4" onSubmit={handleBuat} noValidate>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label htmlFor="periode" className="block text-sm font-medium text-slate-700">
                                Periode
                            </label>
                            <input
                                id="periode"
                                name="periode"
                                type="month"
                                value={periode}
                                required
                                onChange={(event) => setPeriode(event.target.value)}
                                aria-invalid={buatErrors.periode !== undefined}
                                aria-describedby={buatErrors.periode !== undefined ? 'periode-error' : undefined}
                                className={cn(
                                    'mt-1 w-full rounded-md border bg-white px-3 py-2 text-sm outline-none focus:ring-2',
                                    buatErrors.periode !== undefined
                                        ? 'border-red-400 focus:border-red-500 focus:ring-red-100'
                                        : 'border-slate-300 focus:border-brand-500 focus:ring-brand-200',
                                )}
                            />
                            {buatErrors.periode !== undefined ? (
                                <p id="periode-error" className="mt-1 text-xs text-red-600">
                                    {buatErrors.periode[0]}
                                </p>
                            ) : (
                                <p className="mt-1 text-xs text-slate-500">
                                    Bulan yang ditagih, misalnya 2026-07 untuk Juli.
                                </p>
                            )}
                        </div>

                        <SelectField
                            label="Tahun ajaran"
                            name="tahun_ajaran_id"
                            value={tahunUntukBuat}
                            onChange={(event) => setTahunUntukBuat(event.target.value)}
                            error={buatErrors.tahun_ajaran_id?.[0]}
                            hint="Kosongkan untuk memakai tahun ajaran aktif."
                        >
                            <option value="">Tahun ajaran aktif</option>
                            {tahunAjaran.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.nama}
                                    {item.is_active ? ' (aktif)' : ''}
                                </option>
                            ))}
                        </SelectField>
                    </div>

                    {buatStatus.kind === 'done' && (
                        <p role="status" className="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                            {buatStatus.hasil.dibuat > 0
                                ? `Tagihan ${buatStatus.hasil.periode_label} dibuat untuk ${buatStatus.hasil.dibuat} santri aktif.`
                                : `Tidak ada tagihan baru untuk ${buatStatus.hasil.periode_label}; ${buatStatus.hasil.dilewati} sudah ada sebelumnya.`}
                        </p>
                    )}

                    {buatStatus.kind === 'error' && (
                        <p role="alert" className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                            {buatStatus.message}
                        </p>
                    )}

                    <button
                        type="submit"
                        disabled={buatStatus.kind === 'saving' || periode === ''}
                        className={cn(
                            'rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-700',
                            'disabled:cursor-not-allowed disabled:opacity-60',
                        )}
                    >
                        {buatStatus.kind === 'saving' ? 'Membuat…' : 'Buat Tagihan'}
                    </button>
                </form>
            </Card>

            {!isLoading && !error && (
                <Card title="Ringkasan">
                    <p className="text-sm text-slate-600">
                        {tagihan.length} tagihan pada tampilan ini ·{' '}
                        <span className="font-medium text-amber-600">{menunggu} menunggu verifikasi</span>
                    </p>
                </Card>
            )}

            <Card title="Filter">
                <div className="grid gap-4 sm:grid-cols-3">
                    <SelectField
                        label="Tahun ajaran"
                        name="filter_tahun_ajaran_id"
                        value={filter.tahun_ajaran_id === undefined ? '' : String(filter.tahun_ajaran_id)}
                        onChange={(event) =>
                            setFilter((lama) => ({
                                ...lama,
                                tahun_ajaran_id: event.target.value === '' ? undefined : Number(event.target.value),
                            }))
                        }
                    >
                        <option value="">Semua tahun ajaran</option>
                        {tahunAjaran.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.nama}
                            </option>
                        ))}
                    </SelectField>

                    <div>
                        <label htmlFor="filter_periode" className="block text-sm font-medium text-slate-700">
                            Periode
                        </label>
                        <input
                            id="filter_periode"
                            name="filter_periode"
                            type="month"
                            value={filter.periode ?? ''}
                            onChange={(event) =>
                                setFilter((lama) => ({
                                    ...lama,
                                    periode: event.target.value === '' ? undefined : event.target.value,
                                }))
                            }
                            className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
                        />
                    </div>

                    <SelectField
                        label="Status"
                        name="filter_status"
                        value={filter.status ?? ''}
                        onChange={(event) =>
                            setFilter((lama) => ({
                                ...lama,
                                status:
                                    event.target.value === ''
                                        ? undefined
                                        : (event.target.value as StatusPembayaran),
                            }))
                        }
                    >
                        <option value="">Semua status</option>
                        {(Object.keys(STATUS_PEMBAYARAN_LABELS) as StatusPembayaran[]).map((nilai) => (
                            <option key={nilai} value={nilai}>
                                {STATUS_PEMBAYARAN_LABELS[nilai]}
                            </option>
                        ))}
                    </SelectField>
                </div>
            </Card>

            {error !== null && (
                <p role="alert" className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                    {error}
                </p>
            )}

            {isLoading && <p className="text-sm text-slate-500">Memuat…</p>}

            {!isLoading && !error && tagihan.length === 0 && (
                <Card title="Tidak ada tagihan" description="Tidak ada tagihan SPP yang cocok dengan tampilan ini.">
                    <p className="text-sm text-slate-600">
                        Buat tagihan untuk satu bulan, atau longgarkan filternya.
                    </p>
                </Card>
            )}

            {!isLoading && !error && tagihan.length > 0 && (
                <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="w-full text-left text-sm">
                        <caption className="sr-only">Seluruh tagihan SPP</caption>
                        <thead className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="px-4 py-3 font-medium">Periode</th>
                                <th className="px-4 py-3 font-medium">Nama Santri</th>
                                <th className="px-4 py-3 font-medium">Wali</th>
                                <th className="px-4 py-3 font-medium">Jumlah</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">
                                    <span className="sr-only">Aksi</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {tagihan.map((item) => (
                                <tr key={item.id}>
                                    <td className="px-4 py-3 font-medium text-slate-800">{item.periode_label}</td>
                                    <td className="px-4 py-3">
                                        <p className="text-slate-800">{item.nama_santri}</p>
                                        <p className="font-mono text-xs text-slate-500">{item.nis}</p>
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">{item.wali?.name ?? '—'}</td>
                                    <td className="px-4 py-3 font-medium text-slate-900">
                                        {rupiah(item.jumlah)}
                                    </td>
                                    <td className="px-4 py-3">
                                        <StatusBadge status={item.status} label={item.status_label} />
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Link
                                            to={`/admin/spp/${item.id}`}
                                            className="text-sm font-medium text-brand-700 underline underline-offset-2 hover:text-brand-800"
                                        >
                                            Periksa
                                        </Link>
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
