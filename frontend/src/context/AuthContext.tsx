import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useState,
    type ReactNode,
} from 'react';

import { authApi } from '@/lib/api';
import type { AuthUser } from '@/types/auth';

/**
 * Real session state, backed by Laravel Sanctum.
 *
 * The SPA authenticates with the session cookie, so there is nothing to keep in
 * localStorage: on mount the provider asks the API who it is, and the answer
 * either is a user or a 401. The browser holds the credential, this context
 * only holds the profile the UI renders.
 */
interface AuthContextValue {
    user: AuthUser | null;
    isAuthenticated: boolean;
    /**
     * True until the initial "who am I" request settles. Route guards must wait
     * for this, otherwise a hard refresh on a protected route would bounce the
     * user to /login before the session has been checked.
     */
    isLoading: boolean;
    /**
     * Sign in and return the resulting profile, so the caller can route to the
     * right dashboard without waiting for a state update.
     */
    signIn: (email: string, password: string, remember: boolean) => Promise<AuthUser>;
    register: (input: {
        name: string;
        email: string;
        password: string;
        password_confirmation: string;
    }) => Promise<AuthUser>;
    signOut: () => Promise<void>;
    /** Drop the local profile without calling the API (session already gone). */
    forget: () => void;
    /**
     * Replace the cached profile after the server returned an updated one, e.g.
     * after a profile edit. Keeps the header in step without a refetch, and
     * without the page holding a second copy of the user's name.
     */
    applyUser: (user: AuthUser) => void;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<AuthUser | null>(null);
    const [isLoading, setIsLoading] = useState(true);

    const loadSession = useCallback(async () => {
        try {
            setUser(await authApi.me());
        } catch {
            // A 401 here is the normal case for a visitor, not an error.
            setUser(null);
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        void loadSession();
    }, [loadSession]);

    const signIn = useCallback(async (email: string, password: string, remember: boolean) => {
        const payload = await authApi.login({ email, password, remember });

        setUser(payload.user);

        return payload.user;
    }, []);

    const register = useCallback(
        async (input: { name: string; email: string; password: string; password_confirmation: string }) => {
            const payload = await authApi.register(input);

            setUser(payload.user);

            return payload.user;
        },
        [],
    );

    const signOut = useCallback(async () => {
        try {
            await authApi.logout();
        } finally {
            // Clear locally even if the request failed: a dead session cookie
            // must not leave the UI showing a signed-in shell.
            setUser(null);
        }
    }, []);

    const forget = useCallback(() => {
        setUser(null);
    }, []);

    const applyUser = useCallback((updated: AuthUser) => {
        setUser(updated);
    }, []);

    const value = useMemo<AuthContextValue>(
        () => ({
            user,
            isAuthenticated: user !== null,
            isLoading,
            signIn,
            register,
            signOut,
            forget,
            applyUser,
        }),
        [user, isLoading, signIn, register, signOut, forget, applyUser],
    );

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
    const context = useContext(AuthContext);

    if (context === null) {
        throw new Error('useAuth must be used inside <AuthProvider>.');
    }

    return context;
}
