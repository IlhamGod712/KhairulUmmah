import { useCallback, useEffect, useState } from 'react';
import { useParams } from 'react-router';

import { adminPenggunaApi, describeError } from '@/lib/api';
import { USER_ROLE_LABELS, type UserRole } from '@/types/auth';
import { Baris } from '@/components/DetailRow';
import { useAuth } from '@/context/AuthContext';
import type { Pengguna, PenggunaUpdatePayload } from '@/types/pengguna';

export default function AdminPenggunaDetailPage() {
    const { id } = useParams();
    const { user: currentUser } = useAuth();

    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [msg, setMsg] = useState<string | null>(null);
    const [pengguna, setPengguna] = useState<Pengguna | null>(null);

    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [noHp, setNoHp] = useState<string | null>(null);
    const [role, setRole] = useState<UserRole>('wali');
    const [password, setPassword] = useState('');

    const load = useCallback(async () => {
        if (!id) {
            setError('ID pengguna tidak valid.');
            setLoading(false);
            return;
        }

        setLoading(true);
        setError(null);
        try {
            const res = await adminPenggunaApi.get(id);
            setPengguna(res);
            setName(res.name);
            setEmail(res.email);
            setNoHp(res.no_hp);
            setRole(res.role);
        } catch (e) {
            console.error(e);
            setError(describeError(e));
            setPengguna(null);
        } finally {
            setLoading(false);
        }
    }, [id]);

    useEffect(() => {
        void load();
    }, [load]);

    const onSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!id) return;

        setSaving(true);
        setError(null);
        setMsg(null);
        try {
            const payload: PenggunaUpdatePayload = {
                name,
                email,
                no_hp: noHp,
                role,
            };

            if (password) {
                payload.password = password;
            }

            const res = await adminPenggunaApi.update(id, payload);
            setPengguna(res);
            setName(res.name);
            setEmail(res.email);
            setNoHp(res.no_hp);
            setRole(res.role);
            setPassword(''); // Clear password field after update
            setMsg('Pengguna berhasil diperbarui.');
        } catch (e) {
            console.error(e);
            setError(describeError(e));
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return <p className="text-sm text-slate-600">Memuat...</p>;
    }

    if (error && !pengguna) {
        return <p className="text-sm text-red-600">{error}</p>;
    }

    if (!pengguna) {
        return <p className="text-sm text-slate-600">Pengguna tidak ditemukan.</p>;
    }

    const isSelf = currentUser?.id === pengguna.id;

    return (
        <div className="mx-auto max-w-3xl px-4 py-8">
            <div className="mb-6">
                <h1 className="text-2xl font-semibold text-slate-900">Detail Pengguna</h1>
                <p className="mt-1 text-sm text-slate-600">Informasi lengkap dan pengelolaan akun pengguna.</p>
            </div>

            {error && <p className="text-sm text-red-600">{error}</p>}
            {msg && <p className="text-sm text-green-600">{msg}</p>}

            <form onSubmit={onSubmit} className="space-y-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div className="grid gap-4 md:grid-cols-2">
                    <label className="text-sm text-slate-700">Nama
                        <input
                            required
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                            placeholder="Nama Lengkap"
                        />
                    </label>
                    <label className="text-sm text-slate-700">Email
                        <input
                            type="email"
                            required
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                            placeholder="email@example.com"
                        />
                    </label>
                    <label className="text-sm text-slate-700">No. HP
                        <input
                            type="text"
                            value={noHp ?? ''}
                            onChange={(e) => setNoHp(e.target.value)}
                            className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                            placeholder="Nomor Telepon (opsional)"
                        />
                    </label>
                    <label className="text-sm text-slate-700">Role
                        <select
                            value={role}
                            onChange={(e) => setRole(e.target.value as UserRole)}
                            disabled={isSelf} // Prevent admin from demoting self
                            className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 disabled:bg-slate-100"
                        >
                            {Object.entries(USER_ROLE_LABELS).map(([key, label]) => (
                                <option key={key} value={key}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="text-sm text-slate-700">Ganti Password (kosongkan jika tidak ingin mengubah)
                        <input
                            type="password"
                            autoComplete="new-password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                            placeholder="*****"
                        />
                    </label>
                </div>
                <div className="flex justify-end">
                    <button disabled={saving} type="submit" className="rounded-md bg-brand-600 px-4 py-2 text-sm text-white disabled:opacity-50">
                        {saving ? 'Menyimpan...' : 'Simpan Perubahan'}
                    </button>
                </div>
            </form>

            <div className="mt-8 space-y-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h2 className="text-lg font-semibold text-slate-900">Informasi Tambahan</h2>
                <Baris label="ID Pengguna" value={pengguna.id} />
                <Baris label="Email Terverifikasi" value={pengguna.email_verified_at ? 'Ya' : 'Tidak'} />
                <Baris label="Terdaftar Sejak" value={pengguna.created_at ? new Date(pengguna.created_at).toLocaleDateString() : '-'} />
                <Baris label="Terakhir Diperbarui" value={pengguna.updated_at ? new Date(pengguna.updated_at).toLocaleDateString() : '-'} />
                {pengguna.role === 'wali' && (
                    <div className="mt-4 p-4 border border-slate-200 rounded-md bg-slate-50">
                        <p className="text-sm font-medium text-slate-700">Santri Terkait:</p>
                        <p className="text-xs text-slate-500">Untuk melihat detail santri, navigasikan ke halaman Manajemen Santri.</p>
                    </div>
                )}
            </div>
        </div>
    );
}
