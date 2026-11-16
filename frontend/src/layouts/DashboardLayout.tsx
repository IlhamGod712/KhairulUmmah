import { useCallback, useEffect } from 'react';
import { NavLink, Outlet, useNavigate } from 'react-router';

import { useAuth } from '@/context/AuthContext';
import { cn } from '@/lib/utils';
import type { UserRole } from '@/types/auth';

const NAV_ITEMS = [
    { to: '/wali/dashboard', label: 'Dashboard Wali', role: 'wali' },
    { to: '/admin/dashboard', label: 'Dashboard Admin', role: 'admin' },
    { to: '/admin/pendaftaran', label: 'Pendaftaran', role: 'admin' },
    { to: '/admin/santri', label: 'Manajemen Santri', role: 'admin' },
    { to: '/admin/pembayaran', label: 'Pembayaran', role: 'admin' },
    { to: '/admin/spp', label: 'SPP', role: 'admin' },
    { to: '/admin/pengaturan', label: 'Pengaturan', role: 'admin' },
    { to: '/admin/pengguna', label: 'Pengguna', role: 'admin' },
    { to: '/admin/audit-trail', label: 'Audit Trail', role: 'admin' },
    { to: '/admin/laporan', label: 'Laporan Keuangan', role: 'admin' },
] as const satisfies readonly { to: string; label: string; role: UserRole }[];

/**
 * Shell for the admin surface, and the shared sign-out / 401 handling.
 *
 * The nav is filtered to the signed-in user's own role. Listing both links to
 * everyone was harmless in Phase 3 only because both dashboards were probes;
 * with real pages behind them it would mean showing a guardian an Admin link
 * that the `role:admin` guard answers with a 403.
 *
 * Wali pages use `WaliLayout` instead, which has its own sidebar.
 */
export function DashboardLayout() {
    const { user, signOut, forget } = useAuth();
    const navigate = useNavigate();

    const items = NAV_ITEMS.filter((item) => item.role === user?.role);

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
                        <p className="text-xs text-slate-500">Manajemen Santri</p>
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

            {items.length > 0 && (
                <div className="border-b border-slate-200 bg-white">
                    <nav className="mx-auto flex max-w-6xl gap-2 px-4">
                        {items.map((item) => (
                            <NavLink
                                key={item.to}
                                to={item.to}
                                className={({ isActive }) =>
                                    cn(
                                        'border-b-2 px-3 py-2 text-sm transition',
                                        isActive
                                            ? 'border-brand-600 font-semibold text-brand-700'
                                            : 'border-transparent text-slate-500 hover:text-slate-700',
                                    )
                                }
                            >
                                {item.label}
                            </NavLink>
                        ))}
                    </nav>
                </div>
            )}

            <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
                <Outlet />
            </main>
        </div>
    );
}
