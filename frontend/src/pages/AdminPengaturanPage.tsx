import { useEffect, useState } from 'react';

import { adminApi } from '@/lib/api';

export default function AdminPengaturanPage() {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [msg, setMsg] = useState<string | null>(null);

    const [biaya, setBiaya] = useState('');
    const [jatuhTempo, setJatuhTempo] = useState('');

    const load = async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await adminApi.pengaturan.get();
            setBiaya(res.biaya_spp_bulanan?.nilai || '0');
            setJatuhTempo(res.spp_tanggal_jatuh_tempo?.nilai || '10');
        } catch {
            setError('Gagal memuat pengaturan.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        void load();
    }, []);

    const onSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);
        setError(null);
        setMsg(null);
        try {
        await adminApi.pengaturan.update({
            pengaturan: {
                biaya_spp_bulanan: biaya,
                spp_tanggal_jatuh_tempo: Number(jatuhTempo),
            },
        });
        setMsg('Pengaturan berhasil disimpan. Perubahan hanya berlaku untuk tagihan baru.');
        } catch {
            setError('Gagal menyimpan pengaturan. Periksa nilai yang diinput.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="mx-auto max-w-3xl px-4 py-8">
            <div className="mb-6">
                <h1 className="text-2xl font-semibold text-slate-900">Pengaturan Sistem</h1>
                <p className="mt-1 text-sm text-slate-600">Kelola pengaturan operasional SPP. Tagihan yang sudah dibuat tidak berubah.</p>
            </div>

            {loading ? <p className="text-sm text-slate-600">Memuat...</p> : null}
            {error && <p className="text-sm text-red-600">{error}</p>}
            {msg && <p className="text-sm text-green-600">{msg}</p>}

            {!loading && (
                <form onSubmit={onSubmit} className="space-y-4 rounded-lg border border-slate-200 bg-white p-4">
                    <div className="grid gap-4 md:grid-cols-2">
                        <label className="text-sm text-slate-700">Biaya SPP Bulanan (Rp)
                            <input value={biaya} onChange={(e) => setBiaya(e.target.value)} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="0.00" />
                        </label>
                        <label className="text-sm text-slate-700">Tanggal Jatuh Tempo (1-28)
                            <input type="number" min={1} max={28} value={jatuhTempo} onChange={(e) => setJatuhTempo(e.target.value)} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" />
                        </label>
                    </div>
                    <div className="flex justify-end">
                        <button disabled={saving} type="submit" className="rounded-md bg-brand-600 px-4 py-2 text-sm text-white disabled:opacity-50">{saving ? 'Menyimpan...' : 'Simpan'}</button>
                    </div>
                    <p className="text-xs text-slate-500">Perubahan hanya berlaku untuk tagihan SPP baru yang dibuat setelah perubahan disimpan. Tagihan lama tetap menggunakan nominal dan tanggal jatuh tempo saat dibuat.</p>
                </form>
            )}
        </div>
    );
}
