import { useEffect, useState } from 'react';
import { Link } from 'react-router';

import { waliNotifikasiApi } from '@/lib/api';
import type { NotifikasiItem } from '@/types/notifikasi';

export default function WaliNotifikasiPage() {
    const [items, setItems] = useState<NotifikasiItem[]>([]);
    const [unread, setUnread] = useState(0);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const load = async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await waliNotifikasiApi.list(1);
            setItems(res.items || []);
            setUnread(res.unread_count || 0);
        } catch {
            setError('Gagal memuat notifikasi.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        void load();
    }, []);

    const markRead = async (id: number) => {
        try {
            await waliNotifikasiApi.markRead(id);
            setItems((prev) => prev.map((n) => (n.id === id ? { ...n, dibaca: true } : n)));
            setUnread((c) => Math.max(0, c - 1));
        } catch {
            // ignore
        }
    };

    const markAll = async () => {
        try {
            await waliNotifikasiApi.markAllRead();
            setItems((prev) => prev.map((n) => ({ ...n, dibaca: true })));
            setUnread(0);
        } catch {
            // ignore
        }
    };

    return (
        <div className="mx-auto max-w-4xl px-4 py-8">
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Notifikasi</h1>
                    <p className="mt-1 text-sm text-slate-600">Belum dibaca: {unread}</p>
                </div>
                <button onClick={() => void markAll()} className="text-sm text-brand-700 hover:underline">Tandai semua dibaca</button>
            </div>

            {loading && <p className="text-sm text-slate-600">Memuat...</p>}
            {error && <p className="text-sm text-red-600">{error}</p>}
            {!loading && !error && items.length === 0 && <p className="text-sm text-slate-500">Tidak ada notifikasi</p>}

            <div className="space-y-2">
                {items.map((n) => (
                    <div key={n.id} className={`rounded-lg border p-4 ${n.dibaca ? 'border-slate-200 bg-white' : 'border-brand-200 bg-brand-50/40'}`}>
                        <div className="flex items-start justify-between">
                            <div>
                                <p className="font-medium text-slate-900">{n.judul}</p>
                                <p className="mt-1 text-sm text-slate-700">{n.pesan}</p>
                                <p className="mt-1 text-xs text-slate-500">{n.created_at ? new Date(n.created_at).toLocaleString() : ''}</p>
                            </div>
                            <div className="flex flex-col items-end gap-2">
                                {n.url ? <Link to={n.url} className="text-sm text-brand-700 hover:underline">Lihat</Link> : null}
                                {!n.dibaca ? <button onClick={() => void markRead(n.id)} className="text-sm text-slate-600 hover:underline">Tandai dibaca</button> : null}
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
