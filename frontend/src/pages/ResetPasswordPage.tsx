import { useState, type FormEvent } from 'react';
import { Link, useSearchParams } from 'react-router';

import { AuthAlert, AuthLayout, AuthNotice } from '@/components/AuthLayout';
import { Card } from '@/components/Card';
import { FormField } from '@/components/FormField';
import { authApi, describeError } from '@/lib/api';

/**
 * Set a new password from the emailed link.
 *
 * Both `token` and `email` arrive as query parameters on the link the backend
 * builds, so they are read straight off the URL. A missing or blank token is
 * treated as a dead link rather than being submitted, because the server would
 * only reject it anyway.
 */
export default function ResetPasswordPage() {
    const [searchParams] = useSearchParams();

    const token = searchParams.get('token') ?? '';
    const email = searchParams.get('email') ?? '';

    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [formError, setFormError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isDone, setIsDone] = useState(false);

    const isLinkUsable = token !== '' && email !== '';

    const handleSubmit = async (event: FormEvent) => {
        event.preventDefault();
        setFormError(null);

        if (password !== passwordConfirmation) {
            setFormError('Konfirmasi kata sandi tidak cocok.');

            return;
        }

        setIsSubmitting(true);

        try {
            await authApi.resetPassword({
                token,
                email,
                password,
                password_confirmation: passwordConfirmation,
            });

            setIsDone(true);
        } catch (error) {
            setFormError(describeError(error));
            setIsSubmitting(false);
        }
    };

    if (isDone) {
        return (
            <AuthLayout
                title="Kata sandi diperbarui"
                subtitle="Silakan masuk dengan kata sandi baru"
                footer={
                    <Link to="/login" className="font-medium text-brand-700 hover:underline">
                        Masuk sekarang
                    </Link>
                }
            >
                <Card>
                    <AuthNotice>
                        Kata sandi berhasil diperbarui. Semua sesi dan perangkat yang
                        sebelumnya login sudah dikeluarkan.
                    </AuthNotice>
                </Card>
            </AuthLayout>
        );
    }

    return (
        <AuthLayout
            title="Kata Sandi Baru"
            subtitle="Pondok Pesantren Terpadu Khairul Ummah"
            footer={
                <Link to="/login" className="font-medium text-brand-700 hover:underline">
                    Kembali ke halaman masuk
                </Link>
            }
        >
            <Card title="Buat kata sandi baru">
                {!isLinkUsable ? (
                    <div className="space-y-4">
                        <AuthAlert>
                            Tautan ini tidak lengkap atau sudah tidak berlaku. Minta tautan
                            baru dari halaman lupa kata sandi.
                        </AuthAlert>
                        <Link
                            to="/forgot-password"
                            className="block text-sm font-medium text-brand-700 hover:underline"
                        >
                            Minta tautan baru
                        </Link>
                    </div>
                ) : (
                    <form className="space-y-4" onSubmit={handleSubmit}>
                        {formError !== null && <AuthAlert>{formError}</AuthAlert>}

                        <p className="rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-600">
                            Mengatur ulang kata sandi untuk{' '}
                            <span className="font-medium text-slate-800">{email}</span>
                        </p>

                        <FormField
                            label="Kata Sandi Baru"
                            name="password"
                            type="password"
                            autoComplete="new-password"
                            required
                            minLength={8}
                            value={password}
                            onChange={(event) => setPassword(event.target.value)}
                            placeholder="Minimal 8 karakter"
                        />

                        <FormField
                            label="Ulangi Kata Sandi Baru"
                            name="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                            required
                            value={passwordConfirmation}
                            onChange={(event) => setPasswordConfirmation(event.target.value)}
                        />

                        <button
                            type="submit"
                            disabled={isSubmitting}
                            className="w-full rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:bg-slate-300"
                        >
                            {isSubmitting ? 'Menyimpan…' : 'Simpan kata sandi baru'}
                        </button>
                    </form>
                )}
            </Card>
        </AuthLayout>
    );
}
