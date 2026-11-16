import { useState, type FormEvent } from 'react';
import { Link, Navigate, useNavigate } from 'react-router';

import { AuthAlert, AuthLayout } from '@/components/AuthLayout';
import { Card } from '@/components/Card';
import { FormField } from '@/components/FormField';
import { useAuth } from '@/context/AuthContext';
import { describeError, fieldErrors } from '@/lib/api';

/**
 * Guardian self registration.
 *
 * Only wali accounts can be created — the endpoint hard codes the role, so
 * there is deliberately no role picker here. An existing student record is not
 * claimed here: that is the separate "Registrasi Santri Lama" page, which
 * identifies the record by NIS/NISN, name, and birth date.
 */
export default function RegisterPage() {
    const { register, user } = useAuth();
    const navigate = useNavigate();

    const [form, setForm] = useState({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });
    const [formError, setFormError] = useState<string | null>(null);
    const [fieldError, setFieldError] = useState<Record<string, string[]>>({});
    const [isSubmitting, setIsSubmitting] = useState(false);

    const update = (key: keyof typeof form) => (value: string) => {
        setForm((previous) => ({ ...previous, [key]: value }));
        setFieldError((previous) => {
            if (!(key in previous)) {
                return previous;
            }

            const next = { ...previous };

            delete next[key];

            return next;
        });
    };

    const handleSubmit = async (event: FormEvent) => {
        event.preventDefault();
        setFormError(null);
        setFieldError({});
        setIsSubmitting(true);

        try {
            await register(form);
            navigate('/wali/dashboard', { replace: true });
        } catch (error) {
            const fields = fieldErrors(error);

            setFieldError(fields);
            // Nothing to place next to an input means it is a form-level problem.
            setFormError(Object.keys(fields).length === 0 ? describeError(error) : null);
            setIsSubmitting(false);
        }
    };

    if (user !== null) {
        return <Navigate to="/wali/dashboard" replace />;
    }

    return (
        <AuthLayout
            title="Daftar Wali Santri"
            subtitle="Pondok Pesantren Terpadu Khairul Ummah"
            footer={
                <>
                    Sudah punya akun?{' '}
                    <Link to="/login" className="font-medium text-brand-700 hover:underline">
                        Masuk di sini
                    </Link>
                </>
            }
        >
            <Card
                title="Buat akun"
                description="Gunakan email aktif agar tautan reset kata sandi bisa diterima."
            >
                <form className="space-y-4" onSubmit={handleSubmit}>
                    {formError !== null && <AuthAlert>{formError}</AuthAlert>}

                    <FormField
                        label="Nama Lengkap"
                        name="name"
                        autoComplete="name"
                        required
                        minLength={3}
                        error={fieldError.name?.[0]}
                        value={form.name}
                        onChange={(event) => update('name')(event.target.value)}
                        placeholder="Nama wali sesuai KTP"
                    />

                    <FormField
                        label="Email"
                        name="email"
                        type="email"
                        autoComplete="email"
                        required
                        error={fieldError.email?.[0]}
                        value={form.email}
                        onChange={(event) => update('email')(event.target.value)}
                        placeholder="nama@email.com"
                    />

                    <FormField
                        label="Kata Sandi"
                        name="password"
                        type="password"
                        autoComplete="new-password"
                        required
                        minLength={8}
                        error={fieldError.password?.[0]}
                        value={form.password}
                        onChange={(event) => update('password')(event.target.value)}
                        placeholder="Minimal 8 karakter"
                    />

                    <FormField
                        label="Ulangi Kata Sandi"
                        name="password_confirmation"
                        type="password"
                        autoComplete="new-password"
                        required
                        error={fieldError.password_confirmation?.[0]}
                        value={form.password_confirmation}
                        onChange={(event) => update('password_confirmation')(event.target.value)}
                    />

                    <button
                        type="submit"
                        disabled={isSubmitting}
                        className="w-full rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:bg-slate-300"
                    >
                        {isSubmitting ? 'Mendaftarkan…' : 'Daftar'}
                    </button>
                </form>
            </Card>
        </AuthLayout>
    );
}
