import type { ReactNode } from 'react';
import { Navigate, useLocation } from 'react-router';

import { useAuth } from '@/context/AuthContext';
import type { UserRole } from '@/types/auth';

const HOME_BY_ROLE: Record<UserRole, string> = {
    wali: '/wali/dashboard',
    admin: '/admin/dashboard',
};

/**
 * Role gate for the dashboards.
 *
 * This is a convenience, not a security boundary. The API enforces the same
 * split with the `role` middleware, so a user who reaches the wrong screen by
 * hand still gets a 403 on the data behind it.
 */
export function RequireRole({
    role,
    children,
}: {
    role: UserRole;
    children: ReactNode;
}) {
    const { user, isLoading } = useAuth();
    const location = useLocation();

    if (isLoading) {
        return <AuthSkeleton />;
    }

    if (user === null) {
        return <Navigate to="/login" replace state={{ from: location.pathname }} />;
    }

    if (user.role !== role) {
        // Wrong role for this URL: send them to the dashboard they do own
        // instead of rendering a screen full of controls they cannot use.
        return <Navigate to={HOME_BY_ROLE[user.role]} replace />;
    }

    return <>{children}</>;
}

/**
 * Shown while the session is being resolved, so a refresh does not flash the
 * login page at somebody who is actually signed in.
 */
function AuthSkeleton() {
    return (
        <div className="flex min-h-[50vh] items-center justify-center">
            <p className="text-sm text-slate-500">Memuat sesi…</p>
        </div>
    );
}

/**
 * Gate for pages that only need a signed-in user, not a specific role.
 */
export function RequireAuth({ children }: { children: ReactNode }) {
    const { user, isLoading } = useAuth();
    const location = useLocation();

    if (isLoading) {
        return <AuthSkeleton />;
    }

    if (user === null) {
        return <Navigate to="/login" replace state={{ from: location.pathname }} />;
    }

    return <>{children}</>;
}
