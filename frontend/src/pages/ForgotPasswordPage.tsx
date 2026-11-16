import { useState, type FormEvent } from 'react';
import { Link } from 'react-router';

import { AuthAlert, AuthLayout, AuthNotice } from '@/components/AuthLayout';
import { Card } from '@/components/Card';
import { FormField } from '@/components/FormField';
import { authApi, describeError } from '@/lib/api';

/**
 * Ask for a reset link.
 *
 * The backend answers 200 whether or not the address is registered, so this
 * page shows the same confirmation either way. Telling the user their address
 * is unknown would leak which guardians have accounts.
 */
export default function ForgotPasswordPage() {
    const [email, setEmail] = useState('');
    const [formError, setFormError] = useState<string | null>(null);
    const [sent, setSent] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (event: FormEvent) => {
        event.preventDefault();
        setFormError(null);
        setIsSubmitting(true);

        try {
            const payload = await authApi.forgotPassword(email);

            setSent(payload.email);
        } catch (error) {
            setFormError(describeError(error));
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <AuthLayout
            title="Lupa Kata Sandi"
            subtitle="Kami kirim tautan untuk mengatur ulang kata sandi"
            footer={
                <Link to="/login" className="font-medium text-brand-700 hover:underline">
                    Kembali ke halaman masuk
                </Link>
            }
        >
            <Card title="Atur ulang kata sandi">
                {sent !== null ? (
                    <div className="space-y-4">
                        <AuthNotice>
                            Jika <span className="font-medium">{sent}</span> terdaftar, tautan
                            reset kata sandi sudah dikirim. Periksa juga folder spam.
                        </AuthNotice>
                        <p className="text-xs text-slate-500">
                            Pada pengembangan lokal, email tidak benar-benar dikirim — tautannya
                            ditulis ke <code>storage/logs/laravel.log</code>.
                        </p>
                    </div>
                ) : (
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

                        <button
                            type="submit"
                            disabled={isSubmitting}
                            className="w-full rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:bg-slate-300"
                        >
                            {isSubmitting ? 'Mengirim…' : 'Kirim tautan reset'}
                        </button>
                    </form>
                )}
            </Card>
        </AuthLayout>
    );
}
