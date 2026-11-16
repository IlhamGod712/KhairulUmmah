import { useCallback, useEffect } from 'react';
import { NavLink, Outlet, useNavigate } from 'react-router';

import { useAuth } from '@/context/AuthContext';
import { cn } from '@/lib/utils';

interface NavItem {
    to: string;
    label: string;
    /** `undefined` means the section is available; a badge marks one that is not. */
    badge?: string;
}

const NAV_ITEMS: readonly NavItem[] = [
    { to: '/wali/dashboard', label: 'Dashboard' },
    { to: '/wali/santri', label: 'Data Santri Saya' },
    { to: '/wali/santri-lama', label: 'Registrasi Santri Lama' },
    { to: '/wali/pendaftaran', label: 'Pendaftaran' },
    { to: '/wali/pembayaran', label: 'Pembayaran' },
    { to: '/wali/spp', label: 'SPP Bulanan' },
    { to: '/wali/profil', label: 'Profil Saya' },
    { to: '/wali/notifikasi', label: 'Notifikasi' },
];

/**
 * Shell for everything under `/wali`.
 *
 * Separate from `DashboardLayout` because a guardian's navigation is a vertical
 * sidebar with their own sections, and the admin surface has its own items.
 * Sharing one layout meant showing both roles' links to both roles, which the
 * `role:wali` guard then answered with a 403.
 */
export function WaliLayout() {
    const { user, signOut, forget } = useAuth();
    const navigate = useNavigate();

    /**
     * A 401 anywhere in the app means the session is gone. Forget the local
     * profile straight away and leave the current screen; the route guard will
     * send the user to /login on the next navigation.
     */
    useEffect(() => {
        const onUnauthorized = () => {
            forget();
            navigate('/login', { replace: true });
        };

        window.addEventListener('auth:unauthorized', onUnauthorized);

        return () => window.removeEventListener('auth:unauthorized', onUnauthorized);
    }, [forget, navigate]);

    const handleSignOut = useCallback(async () => {
        await signOut();
        navigate('/login', { replace: true });
    }, [signOut, navigate]);

    return (
        <div className="flex min-h-full flex-col">
            <header className="border-b border-slate-200 bg-white">
                <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                    <div>
                        <p className="text-sm font-semibold text-brand-700">Khairul Ummah</p>
                        <p className="text-xs text-slate-500">Portal Wali Santri</p>
                    </div>
                    {user !== null && (
                        <div className="flex items-center gap-4">
                            <div className="text-right">
                                <p className="text-sm font-medium text-slate-800">{user.name}</p>
                                <p className="text-xs text-slate-500">{user.role_label}</p>
                            </div>
                            <button
                                type="button"
                                onClick={() => void handleSignOut()}
                                className="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 transition hover:bg-slate-50"
                            >
                                Keluar
                            </button>
                        </div>
                    )}
                </div>
            </header>

            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 px-4 py-8 md:flex-row">
                <nav aria-label="Navigasi wali" className="md:w-56 md:shrink-0">
                    <ul className="space-y-1">
                        {NAV_ITEMS.map((item) =>
                            item.badge !== undefined ? (
                                <li key={item.to}>
                                    <span
                                        aria-disabled="true"
                                        title="Belum tersedia pada tahap ini"
                                        className="flex cursor-not-allowed items-center justify-between rounded-md px-3 py-2 text-sm text-slate-400"
                                    >
                                        {item.label}
                                        <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-slate-400">
                                            {item.badge}
                                        </span>
                                    </span>
                                </li>
                            ) : (
                                <li key={item.to}>
                                    <NavLink
                                        to={item.to}
                                        className={({ isActive }) =>
                                            cn(
                                                'block rounded-md px-3 py-2 text-sm transition',
                                                isActive
                                                    ? 'bg-brand-50 font-semibold text-brand-700'
                                                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
                                            )
                                        }
                                    >
                                        {item.label}
                                    </NavLink>
                                </li>
                            ),
                        )}
                    </ul>
                </nav>

                <main className="min-w-0 flex-1">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
