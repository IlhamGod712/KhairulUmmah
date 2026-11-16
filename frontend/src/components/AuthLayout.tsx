import type { ReactNode } from 'react';

interface AuthLayoutProps {
    title: string;
    subtitle: string;
    children: ReactNode;
    /** Links under the form, e.g. "Belum punya akun?" */
    footer?: ReactNode;
}

/**
 * Shared shell for every unauthenticated screen, so the four auth pages look
 * like one flow rather than four separate ones.
 */
export function AuthLayout({ title, subtitle, children, footer }: AuthLayoutProps) {
    return (
        <div className="flex min-h-full items-center justify-center px-4 py-12">
            <div className="w-full max-w-md space-y-6">
                <header className="text-center">
                    <h1 className="text-2xl font-semibold text-slate-900">{title}</h1>
                    <p className="mt-1 text-sm text-slate-500">{subtitle}</p>
                </header>

                {children}

                {footer !== undefined && (
                    <p className="text-center text-sm text-slate-600">{footer}</p>
                )}
            </div>
        </div>
    );
}

/**
 * Banner for a form-level error that does not belong to one field.
 */
export function AuthAlert({ children }: { children: ReactNode }) {
    return (
        <div
            role="alert"
            className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
        >
            {children}
        </div>
    );
}

/**
 * Green banner for a form-level success message.
 */
export function AuthNotice({ children }: { children: ReactNode }) {
    return (
        <div
            role="status"
            className="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"
        >
            {children}
        </div>
    );
}
