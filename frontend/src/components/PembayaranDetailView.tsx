import { Link } from 'react-router';

import { apiUrl } from '@/lib/api';
import { rupiah } from '@/lib/utils';
import { Card } from '@/components/Card';
import { Baris, formatBytes } from '@/components/DetailRow';
import { StatusBadge } from '@/components/StatusBadge';
import { STATUS_DOKUMEN_LABELS } from '@/types/pembayaran';
import type { PembayaranListItem } from '@/types/pembayaran';

interface PembayaranDetailViewProps {
    pembayaran: PembayaranListItem;
    /**
     * Show the paying guardian and use the admin's payment links. On for the
     * admin surface, where knowing who paid is the point; off for the guardian.
     */
    tampilkanWali?: boolean;
}

/**
 * One fee payment, in full.
 *
 * Shared verbatim by the guardian's payment page and the admin's, mirroring
 * `PendaftaranDetailView`: the same data differs only in whose links it carries.
 * The proof block doubles as the review surface — both roles see the uploaded
 * file, its review status, and any note the admin attached to a verdict.
 */
export function PembayaranDetailView({ pembayaran, tampilkanWali = false }: PembayaranDetailViewProps) {
    const basis = tampilkanWali ? '/admin' : '/wali';
    const { bukti } = pembayaran;
    const buktiUrl = bukti?.url !== null && bukti?.url !== undefined ? apiUrl(bukti.url) : null;
    const gambar = bukti?.mime_type !== null && bukti?.mime_type !== undefined && bukti.mime_type.startsWith('image/');

    return (
        <div className="space-y-6">
            <Card
                title="Tagihan Pembayaran"
                description={`${pembayaran.jenis_pembayaran_label} untuk ${pembayaran.nama_santri}, tahun ajaran ${pembayaran.tahun_ajaran}.`}
            >
                <dl className="divide-y divide-slate-100">
                    <Baris
                        label="Jumlah tagihan"
                        value={<span className="font-medium text-slate-900">{rupiah(pembayaran.jumlah)}</span>}
                    />
                    <Baris
                        label="Status tagihan"
                        value={<StatusBadge status={pembayaran.status} label={pembayaran.status_label} />}
                    />
                    <Baris
                        label="Pendaftaran"
                        value={
                            pembayaran.pendaftaran_id === null ? null : (
                                <>
                                    <Link
                                        to={`${basis}/pendaftaran/${pembayaran.pendaftaran_id}`}
                                        className="font-medium text-brand-700 underline underline-offset-2 hover:text-brand-800"
                                    >
                                        {pembayaran.nomor_pendaftaran ?? 'Detail pendaftaran'}
                                    </Link>
                                    {' · '}
                                    {pembayaran.pendaftaran_status === null ? null : (
                                        <StatusBadge
                                            status={pembayaran.pendaftaran_status}
                                            label={pembayaran.pendaftaran_status_label ?? pembayaran.pendaftaran_status}
                                        />
                                    )}
                                </>
                            )
                        }
                    />
                    <Baris label="Tanggal bayar" value={pembayaran.tanggal_bayar} />
                    <Baris label="Referensi transfer" value={pembayaran.referensi} />
                    <Baris
                        label="Metode"
                        value={pembayaran.metode_label !== null ? pembayaran.metode_label : null}
                    />
                    <Baris label="Catatan" value={pembayaran.keterangan} />
                    {tampilkanWali && (
                        <Baris
                            label="Dibayar oleh"
                            value={
                                pembayaran.wali === null
                                    ? null
                                    : `${pembayaran.wali.name} · ${pembayaran.wali.email}`
                            }
                        />
                    )}
                </dl>
            </Card>

            <Card
                title="Bukti Pembayaran"
                description={
                    bukti === null
                        ? 'Belum ada berkas bukti yang diunggah wali.'
                        : `Status berkas: ${STATUS_DOKUMEN_LABELS[bukti.status] ?? bukti.status_label}`
                }
            >
                {bukti === null ? (
                    <p className="text-sm text-slate-600">
                        Bukti pembayaran diunggah oleh wali setelah pendaftaran diterima.
                    </p>
                ) : (
                    <div className="space-y-4">
                        {gambar && buktiUrl !== null && (
                            <img
                                src={buktiUrl}
                                alt={`Bukti pembayaran ${bukti.nama_asli}`}
                                className="max-h-64 rounded-lg border border-slate-200 object-contain"
                            />
                        )}
                        <dl className="divide-y divide-slate-100">
                            <Baris label="Berkas" value={bukti.nama_asli} />
                            <Baris
                                label="Ukuran"
                                value={formatBytes(bukti.ukuran_bytes)}
                            />
                            <Baris
                                label="Status verifikasi"
                                value={<StatusBadge status={bukti.status} label={bukti.status_label} />}
                            />
                            <Baris label="Catatan admin" value={bukti.catatan} />
                        </dl>
                        {buktiUrl !== null && (
                            <p className="text-sm">
                                <a
                                    href={buktiUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="font-medium text-brand-700 underline underline-offset-2 hover:text-brand-800"
                                >
                                    Buka berkas bukti
                                </a>
                            </p>
                        )}
                    </div>
                )}
            </Card>

            <p className="text-xs leading-relaxed text-slate-500">
                Santri menjadi aktif setelah pendaftaran diterima <em>dan</em> pembayaran pendaftaran
                dikonfirmasi lunas oleh admin / tata usaha. Menyerahkan bukti belum berarti pembayaran
                diterima.
            </p>
        </div>
    );
}