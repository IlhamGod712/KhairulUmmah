import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router';

import { adminApi } from '@/lib/api';
import type { SantriAdmin, SantriAdminUpdatePayload } from '@/types/santri';

export default function AdminSantriDetailPage() {
    const { id } = useParams();
    const [santri, setSantri] = useState<SantriAdmin | null>(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [msg, setMsg] = useState<string | null>(null);

    const [form, setForm] = useState<SantriAdminUpdatePayload>({});

    useEffect(() => {
        const load = async () => {
            if (!id) return;
            setLoading(true);
            try {
                const res = await adminApi.santri.show(Number(id));
                setSantri(res);
                setForm({
                    nama_lengkap: res.nama_lengkap,
                    jenis_kelamin: res.jenis_kelamin,
                    tempat_lahir: res.tempat_lahir,
                    tanggal_lahir: res.tanggal_lahir || '',
                    alamat: res.alamat,
                    kelurahan: res.kelurahan,
                    kecamatan: res.kecamatan,
                    kabupaten: res.kabupaten,
                    provinsi: res.provinsi,
                    no_hp: res.no_hp,
                    nama_ayah: res.nama_ayah,
                    nama_ibu: res.nama_ibu,
                    pekerjaan_ayah: res.pekerjaan_ayah,
                    pekerjaan_ibu: res.pekerjaan_ibu,
                    penghasilan_bulanan: res.penghasilan_bulanan,
                    sekolah_asal: res.sekolah_asal,
                    nisn: res.nisn,
                });
            } catch {
                setError('Gagal memuat detail santri.');
            } finally {
                setLoading(false);
            }
        };
        void load();
    }, [id]);

    const onChange = (k: keyof SantriAdminUpdatePayload, v: any) => {
        setForm((prev) => ({ ...prev, [k]: v }));
    };

    const onSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!santri) return;
        setSaving(true);
        setError(null);
        setMsg(null);
        try {
            const res = await adminApi.santri.update(santri.id, form);
            setSantri(res);
            setMsg('Data santri berhasil diperbarui.');
        } catch {
            setError('Gagal memperbarui data santri.');
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <div className="mx-auto max-w-4xl px-4 py-8 text-sm text-slate-600">Memuat...</div>;
    if (error && !santri) return <div className="mx-auto max-w-4xl px-4 py-8 text-sm text-red-600">{error}</div>;
    if (!santri) return null;

    return (
        <div className="mx-auto max-w-4xl px-4 py-8">
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Detail Santri</h1>
                    <p className="mt-1 text-sm text-slate-600">{santri.nama_lengkap} - NIS {santri.nis}</p>
                </div>
                <Link to="/admin/santri" className="text-sm text-brand-700 hover:underline">Kembali ke daftar</Link>
            </div>

            <div className="mb-6 grid gap-4 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-2">
                <div>
                    <p className="text-xs text-slate-500">Status</p>
                    <p className="text-sm font-medium text-slate-900">{santri.status_label || santri.status}</p>
                </div>
                <div>
                    <p className="text-xs text-slate-500">Wali</p>
                    <p className="text-sm text-slate-900">{santri.wali ? `${santri.wali.name} (${santri.wali.email})` : '-'}</p>
                </div>
                <div>
                    <p className="text-xs text-slate-500">Tahun Ajaran</p>
                    <p className="text-sm text-slate-900">{santri.tahun_ajaran?.nama || '-'}</p>
                </div>
                <div>
                    <p className="text-xs text-slate-500">NISN</p>
                    <p className="text-sm text-slate-900">{santri.nisn || '-'}</p>
                </div>
            </div>

            <form onSubmit={onSubmit} className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
                <h2 className="text-lg font-medium text-slate-900">Edit Data Santri (Whitelist)</h2>
                {error && <p className="text-sm text-red-600">{error}</p>}
                {msg && <p className="text-sm text-green-600">{msg}</p>}

                <div className="grid gap-4 md:grid-cols-2">
                    <label className="text-sm text-slate-700">Nama Lengkap
                        <input value={form.nama_lengkap || ''} onChange={(e) => onChange('nama_lengkap', e.target.value)} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </label>
                    <label className="text-sm text-slate-700">Jenis Kelamin
                        <select value={form.jenis_kelamin || ''} onChange={(e) => onChange('jenis_kelamin', e.target.value)} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                            <option value="laki_laki">Laki-laki</option>
                            <option value="perempuan">Perempuan</option>
                        </select>
                    </label>
                    <label className="text-sm text-slate-700">Tempat Lahir
                        <input value={form.tempat_lahir || ''} onChange={(e) => onChange('tempat_lahir', e.target.value)} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </label>
                    <label className="text-sm text-slate-700">Tanggal Lahir
                        <input type="date" value={form.tanggal_lahir || ''} onChange={(e) => onChange('tanggal_lahir', e.target.value)} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </label>
                    <label className="text-sm text-slate-700 md:col-span-2">Alamat
                        <textarea value={form.alamat || ''} onChange={(e) => onChange('alamat', e.target.value)} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </label>
                    <label className="text-sm text-slate-700">Kelurahan
                        <input value={form.kelurahan || ''} onChange={(e) => onChange('kelurahan', e.target.value)} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </label>
                    <label className="text-sm text-slate-700">Kecamatan
                        <input value={form.kecamatan || ''} onChange={(e) => onChange('kecamatan', e.target.value)} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </label>
                    <label className="text-sm text-slate-700">Kabupaten
                        <input value={form.kabupaten || ''} onChange={(e) => onChange('kabupaten', e.target.value)} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </label>
                    <label className="text-sm text-slate-700">Provinsi
                        <input value={form.provinsi || ''} onChange={(e) => onChange('provinsi', e.target.value)} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </label>
                    <label className="text-sm text-slate-700">No HP
                        <input value={form.no_hp || ''} onChange={(e) => onChange('no_hp', e.target.value)} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </label>
                    <label className="text-sm text-slate-700">NISN
                        <input value={form.nisn || ''} onChange={(e) => onChange('nisn', e.target.value)} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </label>
                    <label className="text-sm text-slate-700">Nama Ayah
                        <input value={form.nama_ayah || ''} onChange={(e) => onChange('nama_ayah', e.target.value)} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </label>
                    <label className="text-sm text-slate-700">Nama Ibu
                        <input value={form.nama_ibu || ''} onChange={(e) => onChange('nama_ibu', e.target.value)} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </label>
                    <label className="text-sm text-slate-700">Pekerjaan Ayah
                        <input value={form.pekerjaan_ayah || ''} onChange={(e) => onChange('pekerjaan_ayah', e.target.value)} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </label>
                    <label className="text-sm text-slate-700">Pekerjaan Ibu
                        <input value={form.pekerjaan_ibu || ''} onChange={(e) => onChange('pekerjaan_ibu', e.target.value)} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </label>
                    <label className="text-sm text-slate-700">Penghasilan Bulanan
                        <input type="number" value={form.penghasilan_bulanan ?? ''} onChange={(e) => onChange('penghasilan_bulanan', e.target.value === '' ? null : Number(e.target.value))} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </label>
                    <label className="text-sm text-slate-700">Sekolah Asal
                        <input value={form.sekolah_asal || ''} onChange={(e) => onChange('sekolah_asal', e.target.value)} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                    </label>
                </div>

                <div className="flex justify-end">
                    <button disabled={saving} type="submit" className="rounded-md bg-brand-600 px-4 py-2 text-sm text-white disabled:opacity-50">{saving ? 'Menyimpan...' : 'Simpan Perubahan'}</button>
                </div>
                <p className="text-xs text-slate-500">Catatan: status, wali_id, NIS, tahun ajaran, dan data pembayaran/dokumen tidak dapat diubah melalui form ini.</p>
            </form>
        </div>
    );
}
