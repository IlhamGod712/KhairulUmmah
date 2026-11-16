import { Link } from 'react-router';

import { Card } from '@/components/Card';
import { Baris, formatBytes } from '@/components/DetailRow';
import { StatusBadge } from '@/components/StatusBadge';
import { apiUrl } from '@/lib/api';
import { rupiah } from '@/lib/utils';
import type { TagihanSpp } from '@/types/spp';

interface TagihanSppDetailViewProps {
    tagihan: TagihanSpp;
    /**
     * Show the paying guardian. On for the admin surface, where knowing who
     * owes is the point; off for the guardian, who already knows.
     */
    tampilkanWali?: boolean;
}

/**
 * One monthly SPP invoice, in full.
 *
 * Shared by the guardian's and the admin's SPP detail pages, mirroring
 * `PembayaranDetailView`: the same invoice differs only in whose links it
 * carries. The proof block doubles as the review surface — both roles see the
 * uploaded file, and the admin adds the verdict control beside it.
 *
 * There is no "review status" row for the proof, because the invoice's own
 * `status` *is* the review: `menunggu_verifikasi` means a file is waiting,
 * `lunas`/`ditolak` mean it was judged. A second badge here would be a second
 * thing to keep in sync with the first.
 */
export function TagihanSppDetailView({ tagihan, tampilkanWali = false }: TagihanSppDetailViewProps) {
    const { bukti } = tagihan;
    const buktiUrl = bukti?.url !== null && bukti?.url !== undefined ? apiUrl(bukti.url) : null;
    const gambar = bukti?.mime_type !== null && bukti?.mime_type !== undefined && bukti.mime_type.startsWith('image/');

    return (
        <div className="space-y-6">
            <Card
                title="Tagihan SPP"
                description={`SPP bulanan untuk ${tagihan.nama_santri}, tahun ajaran ${tagihan.tahun_ajaran}.`}
            >
                <dl className="divide-y divide-slate-100">
                    <Baris
                        label="Periode"
                        value={<span className="font-medium text-slate-900">{tagihan.periode_label}</span>}
                    />
                    <Baris
                        label="Jumlah tagihan"
                        value={<span className="font-medium text-slate-900">{rupiah(tagihan.jumlah)}</span>}
                    />
                    <Baris
                        label="Status tagihan"
                        value={<StatusBadge status={tagihan.status} label={tagihan.status_label} />}
                    />
                    <Baris label="Jatuh tempo" value={tagihan.jatuh_tempo} />
                    <Baris label="Tanggal bayar" value={tagihan.tanggal_bayar} />
                    <Baris label="Referensi transfer" value={tagihan.referensi} />
                    <Baris
                        label="Metode"
                        value={tagihan.metode_label !== null ? tagihan.metode_label : null}
                    />
                    <Baris label="Catatan" value={tagihan.keterangan} />
                    <Baris label="Diverifikasi pada" value={tagihan.diverifikasi_pada} />
                    <Baris
                        label="Santri"
                        value={
                            <>
                                {tagihan.nama_santri}
                                {' · '}
                                <span className="font-mono text-xs text-slate-500">{tagihan.nis}</span>
                                {' · '}
                                <StatusBadge
                                    status={tagihan.status_santri}
                                    label={tagihan.status_santri_label}
                                />
                            </>
                        }
                    />
                    {tampilkanWali && (
                        <Baris
                            label="Wali penanggung jawab"
                            value={
                                tagihan.wali === null
                                    ? null
                                    : `${tagihan.wali.name} · ${tagihan.wali.email}`
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
                        : 'Bukti untuk tagihan bulan ini.'
                }
            >
                {bukti === null ? (
                    <p className="text-sm text-slate-600">
                        Wali mengunggah bukti pembayaran untuk tagihan bulan ini.
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
                            <Baris label="Ukuran" value={formatBytes(bukti.ukuran_bytes)} />
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
                SPP ditagih per bulan untuk santri yang berstatus{' '}
                <Link
                    to="/wali/santri"
                    className="font-medium text-brand-700 underline underline-offset-2 hover:text-brand-800"
                >
                    Aktif
                </Link>
                . Setiap bulan menyimpan buktinya sendiri, jadi mengunggah bukti Juli tidak menutup tagihan
                Agustus. Membuka bukti <em>belum</em> berarti pembayaran diterima: admin / tata usaha
                mengonfirmasinya.
            </p>
        </div>
    );
}
