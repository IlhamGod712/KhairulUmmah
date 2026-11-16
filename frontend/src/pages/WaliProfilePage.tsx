import { useEffect, useState, type FormEvent } from 'react';

import { Card } from '@/components/Card';
import { FormField } from '@/components/FormField';
import { useAuth } from '@/context/AuthContext';
import { describeError, fieldErrors, waliApi } from '@/lib/api';
import { cn } from '@/lib/utils';
import type { WaliProfile } from '@/types/auth';

type Status = { kind: 'idle' } | { kind: 'saving' } | { kind: 'saved' } | { kind: 'error'; message: string };

const IDLE: Status = { kind: 'idle' };

/**
 * The guardian's own account settings.
 *
 * Two forms because they are two different risks. The profile form changes
 * cosmetic contact details and the API accepts only `name` and `no_hp`. The
 * password form is the one that can lock someone out, so it asks for the
 * current one and the server checks it before accepting a new one.
 *
 * Email and role are rendered as disabled inputs on purpose. Hiding them would
 * make their absence a mystery; showing them as read-only says plainly that the
 * values are real and not editable here. Changing an email needs a
 * confirmation flow on the new address, which is a separate piece of work.
 */
export default function WaliProfilePage() {
    const { user, applyUser } = useAuth();

    const [profile, setProfile] = useState<WaliProfile | null>(null);
    const [loadError, setLoadError] = useState<string | null>(null);

    const [name, setName] = useState('');
    const [noHp, setNoHp] = useState('');
    const [profileErrors, setProfileErrors] = useState<Record<string, string[]>>({});
    const [profileStatus, setProfileStatus] = useState<Status>(IDLE);

    const [currentPassword, setCurrentPassword] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [passwordErrors, setPasswordErrors] = useState<Record<string, string[]>>({});
    const [passwordStatus, setPasswordStatus] = useState<Status>(IDLE);

    useEffect(() => {
        let active = true;

        waliApi
            .profile()
            .then((result) => {
                if (active) {
                    setProfile(result);
                    setName(result.name);
                    setNoHp(result.no_hp ?? '');
                }
            })
            .catch((cause: unknown) => {
                if (active) {
                    setLoadError(describeError(cause));
                }
            });

        return () => {
            active = false;
        };
    }, []);

    async function handleProfileSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setProfileStatus({ kind: 'saving' });
        setProfileErrors({});

        try {
            const saved = await waliApi.updateProfile({
                name,
                // An untouched, empty input means "I have no phone number",
                // which the API stores as null rather than an empty string.
                no_hp: noHp.trim() === '' ? null : noHp.trim(),
            });

            setProfile(saved);
            setName(saved.name);
            setNoHp(saved.no_hp ?? '');
            // Keep the header name in step with what was just saved.
            applyUser(saved);
            setProfileStatus({ kind: 'saved' });
        } catch (cause: unknown) {
            setProfileErrors(fieldErrors(cause));
            setProfileStatus({ kind: 'error', message: describeError(cause) });
        }
    }

    async function handlePasswordSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setPasswordStatus({ kind: 'saving' });
        setPasswordErrors({});

        try {
            await waliApi.updatePassword({
                current_password: currentPassword,
                password,
                password_confirmation: passwordConfirmation,
            });

            // The old value is now wrong, so leaving it in the field would be
            // misleading. The session stays valid; other sessions were revoked
            // server side.
            setCurrentPassword('');
            setPassword('');
            setPasswordConfirmation('');
            setPasswordStatus({ kind: 'saved' });
        } catch (cause: unknown) {
            setPasswordErrors(fieldErrors(cause));
            setPasswordStatus({ kind: 'error', message: describeError(cause) });
        }
    }

    if (loadError !== null) {
        return (
            <div className="space-y-6">
                <h1 className="text-xl font-semibold text-slate-900">Profil Saya</h1>
                <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                    {loadError}
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <header>
                <h1 className="text-xl font-semibold text-slate-900">Profil Saya</h1>
                <p className="text-sm text-slate-500">
                    Data yang tertaut ke akun {user?.email ?? 'Anda'}.
                </p>
            </header>

            <Card
                title="Data Akun"
                description="Nama dan nomor HP boleh diubah. Email dan peran akun tidak."
            >
                {profile === null ? (
                    <p className="text-sm text-slate-500">Memuat…</p>
                ) : (
                    <form className="space-y-4" onSubmit={handleProfileSubmit} noValidate>
                        <FormField
                            label="Nama Lengkap"
                            name="name"
                            value={name}
                            onChange={(event) => setName(event.target.value)}
                            error={profileErrors.name?.[0]}
                            autoComplete="name"
                            required
                        />

                        <FormField
                            label="Nomor HP"
                            name="no_hp"
                            value={noHp}
                            onChange={(event) => setNoHp(event.target.value)}
                            error={profileErrors.no_hp?.[0]}
                            placeholder="0812 3456 7890"
                            inputMode="tel"
                            autoComplete="tel"
                            hint="Boleh dikosongkan jika belum ada."
                        />

                        <FormField
                            label="Email"
                            name="email"
                            value={profile.email}
                            disabled
                            readOnly
                        />

                        <FormField
                            label="Peran Akun"
                            name="role"
                            value={profile.role_label}
                            disabled
                            readOnly
                        />

                        <StatusMessage status={profileStatus} />

                        <button
                            type="submit"
                            disabled={profileStatus.kind === 'saving'}
                            className={cn(
                                'rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-700',
                                'disabled:cursor-not-allowed disabled:opacity-60',
                            )}
                        >
                            {profileStatus.kind === 'saving' ? 'Menyimpan…' : 'Simpan Perubahan'}
                        </button>
                    </form>
                )}
            </Card>

            <Card
                title="Ubah Password"
                description="Sesi di perangkat lain akan dikeluarkan setelah password diganti."
            >
                <form className="space-y-4" onSubmit={handlePasswordSubmit} noValidate>
                    <FormField
                        label="Password Lama"
                        name="current_password"
                        type="password"
                        value={currentPassword}
                        onChange={(event) => setCurrentPassword(event.target.value)}
                        error={passwordErrors.current_password?.[0]}
                        autoComplete="current-password"
                        required
                    />

                    <FormField
                        label="Password Baru"
                        name="password"
                        type="password"
                        value={password}
                        onChange={(event) => setPassword(event.target.value)}
                        error={passwordErrors.password?.[0]}
                        autoComplete="new-password"
                        hint="Minimal 8 karakter, mengandung huruf dan angka."
                        required
                    />

                    <FormField
                        label="Konfirmasi Password Baru"
                        name="password_confirmation"
                        type="password"
                        value={passwordConfirmation}
                        onChange={(event) => setPasswordConfirmation(event.target.value)}
                        error={passwordErrors.password_confirmation?.[0]}
                        autoComplete="new-password"
                        required
                    />

                    <StatusMessage status={passwordStatus} />

                    <button
                        type="submit"
                        disabled={passwordStatus.kind === 'saving'}
                        className={cn(
                            'rounded-md bg-slate-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-900',
                            'disabled:cursor-not-allowed disabled:opacity-60',
                        )}
                    >
                        {passwordStatus.kind === 'saving'
                            ? 'Mengubah…'
                            : 'Ubah Password'}
                    </button>
                </form>
            </Card>
        </div>
    );
}

function StatusMessage({ status }: { status: Status }) {
    if (status.kind === 'idle' || status.kind === 'saving') {
        return null;
    }

    if (status.kind === 'saved') {
        return (
            <p
                role="status"
                className="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-700"
            >
                Berhasil disimpan.
            </p>
        );
    }

    return (
        <p role="alert" className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
            {status.message}
        </p>
    );
}
