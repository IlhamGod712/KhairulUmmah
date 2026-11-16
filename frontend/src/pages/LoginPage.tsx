import { useState, type FormEvent } from 'react';
import { Link, Navigate, useLocation, useNavigate } from 'react-router';

import { AuthAlert, AuthLayout } from '@/components/AuthLayout';
import { Card } from '@/components/Card';
import { FormField } from '@/components/FormField';
import { useAuth } from '@/context/AuthContext';
import { describeError } from '@/lib/api';
import type { UserRole } from '@/types/auth';

const HOME_BY_ROLE: Record<UserRole, string> = {
    wali: '/wali/dashboard',
    admin: '/admin/dashboard',
};

/**
 * Real login against `POST /api/auth/login`. Sanctum sets the session cookie,
 * so there is nothing to store in the browser afterwards.
 */
export default function LoginPage() {
    const { signIn, user, isLoading } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();

    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [remember, setRemember] = useState(false);
    const [formError, setFormError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const from = (location.state as { from?: string } | null)?.from;

    const handleSubmit = async (event: FormEvent) => {
        event.preventDefault();
        setFormError(null);
        setIsSubmitting(true);

        try {
            const signedIn = await signIn(email, password, remember);

            navigate(from ?? HOME_BY_ROLE[signedIn.role], { replace: true });
        } catch (error) {
            setFormError(describeError(error));
            setIsSubmitting(false);
        }
    };

    // Already signed in, e.g. followed a bookmarked link. Wait for the session
    // check first so this does not redirect a signed-in user to /login.
    if (user !== null) {
        return <Navigate to={HOME_BY_ROLE[user.role]} replace />;
    }

    if (isLoading) {
        return (
            <div className="flex min-h-full items-center justify-center">
                <p className="text-sm text-slate-500">Memuat sesi…</p>
            </div>
        );
    }

    return (
        <AuthLayout
            title="Sistem Informasi Santri"
            subtitle="Pondok Pesantren Terpadu Khairul Ummah"
            footer={
                <>
                    Belum punya akun?{' '}
                    <Link to="/register" className="font-medium text-brand-700 hover:underline">
                        Daftar sebagai Wali Santri
                    </Link>
                </>
            }
        >
            <Card title="Masuk" description="Gunakan email yang terdaftar.">
                <form className="space-y-4" onSubmit={handleSubmit}>
                    {formError !== null && <AuthAlert>{formError}</AuthAlert>}

                    <FormField
                        label="Email"
                        name="email"
                        type="email"
                        autoComplete="email"
                        required
                        value={email}
                        onChange={(event) => setEmail(event.target.value)}
                        placeholder="nama@email.com"
                    />

                    <FormField
                        label="Kata Sandi"
                        name="password"
                        type="password"
                        autoComplete="current-password"
                        required
                        value={password}
                        onChange={(event) => setPassword(event.target.value)}
                        placeholder="••••••••"
                    />

                    <div className="flex items-center justify-between">
                        <label className="flex items-center gap-2 text-sm text-slate-600">
                            <input
                                type="checkbox"
                                checked={remember}
                                onChange={(event) => setRemember(event.target.checked)}
                                className="rounded border-slate-300 text-brand-600 focus:ring-brand-200"
                            />
                            Ingat saya
                        </label>

                        <Link
                            to="/forgot-password"
                            className="text-sm text-brand-700 hover:underline"
                        >
                            Lupa kata sandi?
                        </Link>
                    </div>

                    <button
                        type="submit"
                        disabled={isSubmitting}
                        className="w-full rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:bg-slate-300"
                    >
                        {isSubmitting ? 'Memproses…' : 'Masuk'}
                    </button>
                </form>
            </Card>
        </AuthLayout>
    );
}
