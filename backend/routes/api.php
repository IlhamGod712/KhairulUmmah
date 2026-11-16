<?php

use App\Http\Controllers\Api\Admin\AdminAuditTrailController;
use App\Http\Controllers\Api\Admin\AdminDokumenController;
use App\Http\Controllers\Api\Admin\AdminLaporanKeuanganController;
use App\Http\Controllers\Api\Admin\AdminOverviewController;
use App\Http\Controllers\Api\Admin\AdminPembayaranController;
use App\Http\Controllers\Api\Admin\AdminPendaftaranController;
use App\Http\Controllers\Api\Admin\AdminPengaturanController;
use App\Http\Controllers\Api\Admin\AdminPenggunaController;
use App\Http\Controllers\Api\Admin\AdminSantriController;
use App\Http\Controllers\Api\Admin\AdminSppController;
use App\Http\Controllers\Api\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\Auth\NewPasswordController;
use App\Http\Controllers\Api\Auth\PasswordResetLinkController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\Wali\WaliDashboardController;
use App\Http\Controllers\Api\Wali\WaliDokumenController;
use App\Http\Controllers\Api\Wali\WaliNotifikasiController;
use App\Http\Controllers\Api\Wali\WaliPasswordController;
use App\Http\Controllers\Api\Wali\WaliPembayaranController;
use App\Http\Controllers\Api\Wali\WaliPendaftaranController;
use App\Http\Controllers\Api\Wali\WaliProfileController;
use App\Http\Controllers\Api\Wali\WaliSantriController;
use App\Http\Controllers\Api\Wali\WaliSantriLamaController;
use App\Http\Controllers\Api\Wali\WaliSppController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('api.health');

Route::middleware('guest')->group(function (): void {
    Route::post('/auth/register', [RegisterController::class, 'store'])->name('register');
    Route::post('/auth/login', [AuthenticatedSessionController::class, 'store'])->name('login');
    Route::post('/auth/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::post('/auth/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/auth/user', [AuthenticatedSessionController::class, 'show'])->name('auth.user');
    Route::post('/auth/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/user', function (Request $request) {
        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil dimuat.',
            'data' => new UserResource($request->user()),
        ]);
    });
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/overview', [AdminOverviewController::class, '__invoke'])->name('overview');
        Route::get('/pendaftaran', [AdminPendaftaranController::class, 'index'])->name('pendaftaran.index');
        Route::get('/pendaftaran/{pendaftaran}', [AdminPendaftaranController::class, 'show'])->name('pendaftaran.show');
        Route::put('/pendaftaran/{pendaftaran}/status', [AdminPendaftaranController::class, 'putStatus'])->name('pendaftaran.decide');
        Route::get('/pembayaran', [AdminPembayaranController::class, 'index'])->name('pembayaran.index');
        Route::get('/pembayaran/{pembayaran}', [AdminPembayaranController::class, 'show'])->name('pembayaran.show');
        Route::get('/pembayaran/{pembayaran}/bukti', [AdminPembayaranController::class, 'unduhBukti'])->name('pembayaran.berkas');
        Route::put('/pembayaran/{pembayaran}/status', [AdminPembayaranController::class, 'putStatus'])->name('pembayaran.decide');
        Route::get('/dokumen', [AdminDokumenController::class, 'index'])->name('dokumen.index');
        Route::get('/dokumen/{dokumen}', [AdminDokumenController::class, 'show'])->name('dokumen.show');
        Route::get('/dokumen/{dokumen}/berkas', [AdminDokumenController::class, 'berkas'])->name('dokumen.berkas');
        Route::put('/dokumen/{dokumen}/status', [AdminDokumenController::class, 'updateStatus'])->name('dokumen.status');
        Route::get('/santri', [AdminSantriController::class, 'index'])->name('santri.index');
        Route::get('/santri/{santri}', [AdminSantriController::class, 'show'])->name('santri.show');
        Route::put('/santri/{santri}', [AdminSantriController::class, 'update'])->name('santri.update');
        Route::get('/pengaturan', [AdminPengaturanController::class, 'index'])->name('pengaturan.index');
        Route::put('/pengaturan', [AdminPengaturanController::class, 'update'])->name('pengaturan.update');
        Route::get('/laporan', [AdminLaporanKeuanganController::class, 'index'])->name('laporan.index');
        Route::get('/laporan/ekspor', [AdminLaporanKeuanganController::class, 'export'])->name('laporan.ekspor');
        Route::get('/pengguna', [AdminPenggunaController::class, 'index'])->name('pengguna.index');
        Route::get('/pengguna/{pengguna}', [AdminPenggunaController::class, 'show'])->name('pengguna.show');
        Route::put('/pengguna/{pengguna}', [AdminPenggunaController::class, 'update'])->name('pengguna.update');
        Route::get('/audit-trail', [AdminAuditTrailController::class, 'index'])->name('audit-trail.index');
        Route::get('/audit-trail/{auditLog}', [AdminAuditTrailController::class, 'show'])
            ->whereNumber('auditLog')
            ->name('audit-trail.show');
        Route::get('/spp/tahun-ajaran', [AdminSppController::class, 'tahunAjaran'])->name('spp.tahun_ajaran');
        Route::post('/spp/generate', [AdminSppController::class, 'generate'])->name('spp.generate');
        Route::get('/spp', [AdminSppController::class, 'index'])->name('spp.index');
        Route::get('/spp/{pembayaran}', [AdminSppController::class, 'show'])->name('spp.show');
        Route::put('/spp/{pembayaran}/status', [AdminSppController::class, 'putStatus'])->name('spp.decide');
        Route::get('/spp/{pembayaran}/bukti', [AdminSppController::class, 'unduhBukti'])->name('spp.berkas');
    });

    Route::middleware('role:wali')->prefix('wali')->name('wali.')->group(function (): void {
        Route::get('/dashboard', WaliDashboardController::class)->name('dashboard');
        Route::get('/profile', [WaliProfileController::class, 'show'])->name('profile.show');
        Route::put('/profile', [WaliProfileController::class, 'update'])->name('profile.update');
        Route::put('/password', WaliPasswordController::class)->name('password.update');
        Route::get('/santri', WaliSantriController::class)->name('santri');
        Route::post('/santri-lama/match', [WaliSantriLamaController::class, 'match'])->name('santri-lama.match');
        Route::post('/santri-lama/register', [WaliSantriLamaController::class, 'register'])->name('santri-lama.register');
        Route::get('/notifikasi', [WaliNotifikasiController::class, 'index'])->name('notifikasi.index');
        Route::get('/notifikasi/unread-count', [WaliNotifikasiController::class, 'unreadCount'])->name('notifikasi.unread');
        Route::put('/notifikasi/read-all', [WaliNotifikasiController::class, 'markAllRead'])->name('notifikasi.read_all');
        Route::put('/notifikasi/{notifikasi}/read', [WaliNotifikasiController::class, 'markRead'])->name('notifikasi.read');
        Route::get('/pendaftaran', [WaliPendaftaranController::class, 'index'])->name('pendaftaran.index');
        Route::post('/pendaftaran', [WaliPendaftaranController::class, 'store'])->name('pendaftaran.store');
        Route::get('/pendaftaran/{pendaftaran}', [WaliPendaftaranController::class, 'show'])->whereNumber('pendaftaran')->name('pendaftaran.show');
        Route::get('/pendaftaran/{pendaftaran}/dokumen', [WaliDokumenController::class, 'index'])->name('pendaftaran.dokumen.index');
        Route::post('/pendaftaran/{pendaftaran}/dokumen/{jenis}', [WaliDokumenController::class, 'upload'])->where('jenis', '[a-z_]+')->name('pendaftaran.dokumen.upload');
        Route::get('/pendaftaran/{pendaftaran}/dokumen/{jenis}/berkas', [WaliDokumenController::class, 'berkas'])->where('jenis', '[a-z_]+')->name('pendaftaran.dokumen.berkas');
        Route::get('/pembayaran', [WaliPembayaranController::class, 'index'])->name('pembayaran.index');
        Route::get('/pembayaran/{pembayaran}', [WaliPembayaranController::class, 'show'])->whereNumber('pembayaran')->name('pembayaran.show');
        Route::post('/pembayaran/{pembayaran}/bukti', [WaliPembayaranController::class, 'uploadBukti'])->whereNumber('pembayaran')->name('pembayaran.bukti');
        Route::get('/pembayaran/{pembayaran}/bukti', [WaliPembayaranController::class, 'unduhBukti'])->whereNumber('pembayaran')->name('pembayaran.berkas');
        Route::get('/spp', [WaliSppController::class, 'index'])->name('spp.index');
        Route::get('/spp/{pembayaran}', [WaliSppController::class, 'show'])->name('spp.show');
        Route::post('/spp/{pembayaran}/bukti', [WaliSppController::class, 'uploadBukti'])->name('spp.bukti');
        Route::get('/spp/{pembayaran}/bukti', [WaliSppController::class, 'unduhBukti'])->name('spp.berkas');
    });
});
