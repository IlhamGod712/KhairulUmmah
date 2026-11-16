import { Link } from 'react-router';

import { Card } from '@/components/Card';
import { Baris } from '@/components/DetailRow';
import { StatusBadge } from '@/components/StatusBadge';
import { rupiah } from '@/lib/utils';
import type { PendaftaranDetail } from '@/types/pendaftaran';

function rupiahAngka(nilai: number): string {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(nilai);
}

function GabungAlamat(calon: PendaftaranDetail['calon_santri']): string {
    return [calon.alamat, calon.kelurahan, calon.kecamatan, calon.kabupaten, calon.provinsi]
        .filter((bagian) => bagian !== null && bagian !== '')
        .join(', ');
}

interface PendaftaranDetailViewProps {
    pendaftaran: PendaftaranDetail;
    /**
     * Show the submitting guardian. On for the admin surface, where knowing who
     * filed a submission is the point; off for the guardian, who is looking at
     * their own details and has no use for their own name in the header.
     */
    tampilkanWali?: boolean;
}

/**
 * One registration, in full.
 *
 * Shared verbatim by the guardian's detail page and the admin's, because the
 * only thing that differs between them is who is looking. Two copies of a layout
 * this size would drift, and the fields a guardian may see are not a subset
 * worth special-casing: the admin sees exactly what was submitted, and nothing
 * the guardian is shown is hidden from the admin.
 */
export function PendaftaranDetailView({ pendaftaran, tampilkanWali = false }: PendaftaranDetailViewProps) {
    const { calon_santri: calon, ringkasan_pembayaran: bayar, biaya_pendaftaran: biaya } = pendaftaran;

    // The same view serves both surfaces; `tampilkanWali` is exactly the flag
    // that tells them apart for the fee's detail link.
    const basisPembayaran = tampilkanWali ? '/admin/pembayaran' : '/wali/pembayaran';

    return (
        <div className="space-y-6">
            <Card
                title={`Pendaftaran ${pendaftaran.nomor_pendaftaran}`}
                description={`Diajukan pada ${pendaftaran.tanggal_daftar} untuk tahun ajaran ${pendaftaran.tahun_ajaran ?? '—'}.`}
            >
                <dl className="divide-y divide-slate-100">
                    <Baris label="Status pendaftaran" value={<StatusBadge status={pendaftaran.status} label={pendaftaran.status_label} />} />
                    <Baris label="Status Santri" value={<StatusBadge status={calon.status} label={calon.status_label} />} />
                    <Baris
                        label="Status pembayaran"
                        value={<StatusBadge status={bayar.status} label={bayar.status_label} />}
                    />
                    <Baris
                        label="Rincian pembayaran"
                        value={`${bayar.lunas} lunas dari ${bayar.total} catatan pembayaran`}
                    />
                    {pendaftaran.alasan_ditolak !== null && (
                        <Baris
                            label="Alasan penolakan"
                            value={<span className="text-rose-700">{pendaftaran.alasan_ditolak}</span>}
                        />
                    )}
                    {tampilkanWali && (
                        <Baris
                            label="Diajukan oleh"
                            value={
                                pendaftaran.wali === null
                                    ? null
                                    : `${pendaftaran.wali.name} · ${pendaftaran.wali.email}`
                            }
                        />
                    )}
                    <Baris label="Diproses oleh" value={pendaftaran.diproses_oleh} />
                    <Baris label="Catatan" value={pendaftaran.catatan} />
                </dl>
            </Card>

            <Card
                title="Biaya Pendaftaran"
                description={
                    biaya === null
                        ? 'Tagihan biaya pendaftaran dibuat saat pendaftaran diterima.'
                        : 'Tagihan satu-satunya untuk pendaftaran ini.'
                }
            >
                {biaya === null ? (
                    <p className="text-sm text-slate-600">
                        Belum ada tagihan biaya pendaftaran. Tagihan dibuat otomatis oleh admin / tata usaha ketika
                        pendaftaran diterima.
                    </p>
                ) : (
                    <dl className="divide-y divide-slate-100">
                        <Baris
                            label="Jumlah"
                            value={<span className="font-medium text-slate-900">{rupiah(biaya.jumlah)}</span>}
                        />
                        <Baris
                            label="Status tagihan"
                            value={<StatusBadge status={biaya.status} label={biaya.status_label} />}
                        />
                        <Baris
                            label="Lihat"
                            value={
                                <Link
                                    to={`${basisPembayaran}/${biaya.id}`}
                                    className="text-sm font-medium text-brand-700 underline underline-offset-2 hover:text-brand-800"
                                >
                                    Rincian dan unggah bukti
                                </Link>
                            }
                        />
                    </dl>
                )}
            </Card>

            <Card title="Data calon Santri" description="Data yang Anda isi pada formulir pendaftaran.">
                <dl className="divide-y divide-slate-100">
                    <Baris label="NIS" value={calon.nis} />
                    <Baris label="NISN" value={calon.nisn} />
                    <Baris label="Nama lengkap" value={calon.nama_lengkap} />
                    <Baris label="Jenis kelamin" value={calon.jenis_kelamin_label} />
                    <Baris label="Tempat, tanggal lahir" value={`${calon.tempat_lahir}, ${calon.tanggal_lahir}`} />
                    <Baris label="Alamat" value={GabungAlamat(calon)} />
                    <Baris label="Nomor HP" value={calon.no_hp} />
                </dl>
            </Card>

            <Card title="Data orang tua" description="Dapat dikosongkan bila belum diketahui.">
                <dl className="divide-y divide-slate-100">
                    <Baris label="Nama ayah" value={calon.nama_ayah} />
                    <Baris label="Pekerjaan ayah" value={calon.pekerjaan_ayah} />
                    <Baris label="Nama ibu" value={calon.nama_ibu} />
                    <Baris label="Pekerjaan ibu" value={calon.pekerjaan_ibu} />
                    <Baris
                        label="Penghasilan bulanan"
                        value={calon.penghasilan_bulanan === null ? null : rupiahAngka(calon.penghasilan_bulanan)}
                    />
                    <Baris label="Sekolah asal" value={calon.sekolah_asal} />
                </dl>
            </Card>

            <p className="text-xs leading-relaxed text-slate-500">
                Pendaftaran yang sudah diajukan belum berarti Santri Aktif. Santri menjadi aktif setelah
                pendaftaran diterima <em>dan</em> pembayaran pendaftaran selesai diverifikasi oleh admin /
                tata usaha.
            </p>
        </div>
    );
}
