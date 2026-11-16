import { Navigate, createBrowserRouter } from 'react-router';

import { DashboardLayout } from '@/layouts/DashboardLayout';
import { WaliLayout } from '@/layouts/WaliLayout';
import AdminDashboardPage from '@/pages/AdminDashboardPage';
import AdminPembayaranDetailPage from '@/pages/AdminPembayaranDetailPage';
import AdminPembayaranPage from '@/pages/AdminPembayaranPage';
import AdminPendaftaranDetailPage from '@/pages/AdminPendaftaranDetailPage';
import AdminPendaftaranPage from '@/pages/AdminPendaftaranPage';
import AdminPengaturanPage from '@/pages/AdminPengaturanPage';
import AdminLaporanKeuanganPage from '@/pages/AdminLaporanKeuanganPage';
import AdminPenggunaPage from '@/pages/AdminPenggunaPage';
import AdminPenggunaDetailPage from '@/pages/AdminPenggunaDetailPage';
import AdminAuditTrailPage from '@/pages/AdminAuditTrailPage';
import AdminSantriDetailPage from '@/pages/AdminSantriDetailPage';
import AdminSantriPage from '@/pages/AdminSantriPage';
import AdminSppDetailPage from '@/pages/AdminSppDetailPage';
import AdminSppPage from '@/pages/AdminSppPage';
import ForgotPasswordPage from '@/pages/ForgotPasswordPage';
import LoginPage from '@/pages/LoginPage';
import NotFoundPage from '@/pages/NotFoundPage';
import RegisterPage from '@/pages/RegisterPage';
import ResetPasswordPage from '@/pages/ResetPasswordPage';
import WaliDashboardPage from '@/pages/WaliDashboardPage';
import WaliNotifikasiPage from '@/pages/WaliNotifikasiPage';
import WaliPembayaranDetailPage from '@/pages/WaliPembayaranDetailPage';
import WaliPembayaranPage from '@/pages/WaliPembayaranPage';
import WaliPendaftaranDetailPage from '@/pages/WaliPendaftaranDetailPage';
import WaliPendaftaranPage from '@/pages/WaliPendaftaranPage';
import WaliProfilePage from '@/pages/WaliProfilePage';
import WaliSantriLamaPage from '@/pages/WaliSantriLamaPage';
import WaliSantriPage from '@/pages/WaliSantriPage';
import WaliSppDetailPage from '@/pages/WaliSppDetailPage';
import WaliSppPage from '@/pages/WaliSppPage';
import { RequireRole } from '@/routes/RequireRole';

export const router = createBrowserRouter([
    {
        path: '/',
        element: <Navigate to="/login" replace />,
    },
    {
        // Public. Each page redirects to the right dashboard on its own once a
        // session exists, so a signed-in user never sees the login form.
        path: '/login',
        element: <LoginPage />,
    },
    {
        path: '/register',
        element: <RegisterPage />,
    },
    {
        path: '/forgot-password',
        element: <ForgotPasswordPage />,
    },
    {
        path: '/reset-password',
        element: <ResetPasswordPage />,
    },
    {
        // Wali pages sit under their own layout so the guardian navigation is a
        // sidebar of their own sections, not the shared role-agnostic bar.
        element: <WaliLayout />,
        children: [
            {
                path: '/wali/dashboard',
                element: (
                    <RequireRole role="wali">
                        <WaliDashboardPage />
                    </RequireRole>
                ),
            },
            {
                path: '/wali/santri',
                element: (
                    <RequireRole role="wali">
                        <WaliSantriPage />
                    </RequireRole>
                ),
            },
            {
                path: '/wali/santri-lama',
                element: (
                    <RequireRole role="wali">
                        <WaliSantriLamaPage />
                    </RequireRole>
                ),
            },
            {
                path: '/wali/pendaftaran',
                element: (
                    <RequireRole role="wali">
                        <WaliPendaftaranPage />
                    </RequireRole>
                ),
            },
            {
                // Declared after the list route so "pendaftaran" does not
                // shadow the detail path. React Router 7 ranks static segments
                // above dynamic ones regardless of order, but keeping them
                // together reads the way the routes are meant to match.
                path: '/wali/pendaftaran/:id',
                element: (
                    <RequireRole role="wali">
                        <WaliPendaftaranDetailPage />
                    </RequireRole>
                ),
            },
            {
                path: '/wali/pembayaran',
                element: (
                    <RequireRole role="wali">
                        <WaliPembayaranPage />
                    </RequireRole>
                ),
            },
            {
                path: '/wali/pembayaran/:id',
                element: (
                    <RequireRole role="wali">
                        <WaliPembayaranDetailPage />
                    </RequireRole>
                ),
            },
            {
                path: '/wali/spp',
                element: (
                    <RequireRole role="wali">
                        <WaliSppPage />
                    </RequireRole>
                ),
            },
            {
                path: '/wali/spp/:id',
                element: (
                    <RequireRole role="wali">
                        <WaliSppDetailPage />
                    </RequireRole>
                ),
            },
            {
                path: '/wali/profil',
                element: (
                    <RequireRole role="wali">
                        <WaliProfilePage />
                    </RequireRole>
                ),
            },
            {
                path: '/wali/notifikasi',
                element: (
                    <RequireRole role="wali">
                        <WaliNotifikasiPage />
                    </RequireRole>
                ),
            },
        ],
    },
    {
        element: <DashboardLayout />,
        children: [
            {
                path: '/admin/dashboard',
                element: (
                    <RequireRole role="admin">
                        <AdminDashboardPage />
                    </RequireRole>
                ),
            },
            {
                path: '/admin/pendaftaran',
                element: (
                    <RequireRole role="admin">
                        <AdminPendaftaranPage />
                    </RequireRole>
                ),
            },
            {
                path: '/admin/pendaftaran/:id',
                element: (
                    <RequireRole role="admin">
                        <AdminPendaftaranDetailPage />
                    </RequireRole>
                ),
            },
            {
                path: '/admin/pembayaran',
                element: (
                    <RequireRole role="admin">
                        <AdminPembayaranPage />
                    </RequireRole>
                ),
            },
            {
                path: '/admin/pembayaran/:id',
                element: (
                    <RequireRole role="admin">
                        <AdminPembayaranDetailPage />
                    </RequireRole>
                ),
            },
            {
                path: '/admin/spp',
                element: (
                    <RequireRole role="admin">
                        <AdminSppPage />
                    </RequireRole>
                ),
            },
            {
                path: '/admin/spp/:id',
                element: (
                    <RequireRole role="admin">
                        <AdminSppDetailPage />
                    </RequireRole>
                ),
            },
            {
                path: '/admin/santri',
                element: (
                    <RequireRole role="admin">
                        <AdminSantriPage />
                    </RequireRole>
                ),
            },
            {
                path: '/admin/santri/:id',
                element: (
                    <RequireRole role="admin">
                        <AdminSantriDetailPage />
                    </RequireRole>
                ),
            },
            {
                path: '/admin/pengaturan',
                element: (
                    <RequireRole role="admin">
                        <AdminPengaturanPage />
                    </RequireRole>
                ),
            },
            {
                path: '/admin/pengguna',
                element: (
                    <RequireRole role="admin">
                        <AdminPenggunaPage />
                    </RequireRole>
                ),
            },
            {
                path: '/admin/pengguna/:id',
                element: (
                    <RequireRole role="admin">
                        <AdminPenggunaDetailPage />
                    </RequireRole>
                ),
            },
            {
                path: '/admin/audit-trail',
                element: (
                    <RequireRole role="admin">
                        <AdminAuditTrailPage />
                    </RequireRole>
                ),
            },
            {
                path: '/admin/laporan',
                element: (
                    <RequireRole role="admin">
                        <AdminLaporanKeuanganPage />
                    </RequireRole>
                ),
            },
        ],
    },
    {
        path: '*',
        element: <NotFoundPage />,
    },
]);
