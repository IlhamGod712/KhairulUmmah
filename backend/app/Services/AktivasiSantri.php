<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\JenisPembayaran;
use App\Enums\SantriStatus;
use App\Enums\StatusPembayaran;
use App\Enums\StatusPendaftaran;
use App\Models\HistoriStatus;
use App\Models\Pembayaran;
use App\Models\Santri;
use Illuminate\Support\Facades\DB;

class AktivasiSantri
{
    public function __construct(private readonly AuditTrailService $audit) {}

    /**
     * @return array{status: string, message: string, diaktifkan: bool}
     */
    public function cobaAktifkan(Santri $santri): array
    {
        return DB::transaction(function () use ($santri) {
            $santriLocked = Santri::query()->whereKey($santri->getKey())->lockForUpdate()->first();

            if (! $santriLocked instanceof Santri) {
                return ['status' => 'tidak_dapat', 'message' => 'Santri tidak ditemukan.', 'diaktifkan' => false];
            }

            $currentStatus = $santriLocked->status;
            if ($currentStatus instanceof SantriStatus ? $currentStatus === SantriStatus::Aktif : $currentStatus === SantriStatus::Aktif->value) {
                return ['status' => 'sudah_aktif', 'message' => 'Santri sudah aktif.', 'diaktifkan' => false];
            }

            // Need accepted registration
            $pendaftaran = $santriLocked->pendaftarans()
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            if (! $pendaftaran) {
                return ['status' => 'menunggu_pendaftaran', 'message' => 'Belum ada pendaftaran untuk santri ini.', 'diaktifkan' => false];
            }

            if ($pendaftaran->status !== StatusPendaftaran::Diterima) {
                return ['status' => 'menunggu_pendaftaran', 'message' => 'Pendaftaran belum diterima.', 'diaktifkan' => false];
            }

            // Fee must be lunas and verified
            $fee = Pembayaran::query()
                ->where('santri_id', $santriLocked->getKey())
                ->where('pendaftaran_id', $pendaftaran->getKey())
                ->where('jenis_pembayaran', JenisPembayaran::Pendaftaran)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            if (! $fee) {
                return ['status' => 'menunggu_pembayaran', 'message' => 'Pembayaran pendaftaran belum diunggah.', 'diaktifkan' => false];
            }

            if ($fee->status !== StatusPembayaran::Lunas) {
                return ['status' => 'menunggu_pembayaran', 'message' => 'Pembayaran pendaftaran belum lunas.', 'diaktifkan' => false];
            }

            // All required documents must be accepted
            $santriLocked->load('dokumen');
            if (! $santriLocked->berkasLengkap()) {
                return ['status' => 'menunggu_dokumen', 'message' => 'Seluruh dokumen persyaratan wajib belum disetujui.', 'diaktifkan' => false];
            }

            $before = $santriLocked->status;
            $beforeValue = $before instanceof SantriStatus ? $before->value : $before;
            $santriLocked->status = SantriStatus::Aktif;
            $santriLocked->save();

            try {
                HistoriStatus::create([
                    'santri_id' => $santriLocked->getKey(),
                    'status_sebelum' => $beforeValue,
                    'status_sesudah' => SantriStatus::Aktif->value,
                    'keterangan' => 'Diaktifkan otomatis setelah pendaftaran diterima, pembayaran lunas, dan dokumen persyaratan lengkap disetujui.',
                    'diubah_oleh' => auth()->id(),
                ]);
            } catch (\Throwable $e) {
                HistoriStatus::query()->create([
                    'santri_id' => $santriLocked->getKey(),
                    'dari_status' => $before,
                    'ke_status' => SantriStatus::Aktif,
                    'keterangan' => 'Diaktifkan otomatis setelah pendaftaran diterima, pembayaran lunas, dan dokumen persyaratan lengkap disetujui.',
                    'oleh' => auth()->id(),
                    'pada' => now(),
                ]);
            }

            $this->audit->log(
                'santri.activated',
                'Santri diaktifkan setelah seluruh persyaratan terpenuhi.',
                $santriLocked,
                ['status' => $beforeValue],
                ['status' => SantriStatus::Aktif->value],
            );

            return ['status' => 'aktif', 'message' => 'Santri berhasil diaktifkan.', 'diaktifkan' => true];
        });
    }
}
