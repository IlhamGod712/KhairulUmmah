import { Link } from 'react-router';

import { Card } from '@/components/Card';

export default function NotFoundPage() {
    return (
        <div className="flex min-h-full items-center justify-center px-4 py-12">
            <Card className="w-full max-w-md text-center">
                <p className="text-5xl font-bold text-brand-600">404</p>
                <h1 className="mt-2 text-lg font-semibold text-slate-900">
                    Halaman tidak ditemukan
                </h1>
                <p className="mt-1 text-sm text-slate-500">
                    Alamat yang Anda buka tidak tersedia.
                </p>
                <Link
                    to="/"
                    className="mt-6 inline-block rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700"
                >
                    Kembali ke beranda
                </Link>
            </Card>
        </div>
    );
}
