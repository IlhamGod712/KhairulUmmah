<?php

namespace Database\Seeders;

use App\Enums\JenisDokumen;
use App\Enums\StatusDokumen;
use App\Models\Dokumen;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Database\Seeder;

class DokumenSeeder extends Seeder
{
    /**
     * The active student has every required document; the other two are still
     * mid-registration, which gives `berkasLengkap()` something to compare.
     */
    public function run(): void
    {
        $admin = User::query()
            ->where('role', 'admin')
            ->first();

        $santriLengkap = Santri::query()->where('nis', '260001')->first();
        $santriSebagian = Santri::query()->where('nis', '260002')->first();

        if ($santriLengkap !== null) {
            foreach (JenisDokumen::cases() as $jenis) {
                Dokumen::query()->updateOrCreate(
                    [
                        'santri_id' => $santriLengkap->id,
                        'jenis' => $jenis,
                    ],
                    [
                        'path' => 'dokumen/'.$santriLengkap->nis.'/'.$jenis->value.'.pdf',
                        'nama_asli' => $jenis->label().'.pdf',
                        'mime_type' => 'application/pdf',
                        'ukuran_bytes' => 250000,
                        'status' => StatusDokumen::Diterima,
                        'diverifikasi_oleh' => $admin?->id,
                        'diverifikasi_pada' => $admin !== null ? now() : null,
                    ],
                );
            }
        }

        if ($santriSebagian !== null) {
            foreach ([JenisDokumen::KartuKeluarga, JenisDokumen::AktaLahir] as $jenis) {
                Dokumen::query()->updateOrCreate(
                    [
                        'santri_id' => $santriSebagian->id,
                        'jenis' => $jenis,
                    ],
                    [
                        'path' => 'dokumen/'.$santriSebagian->nis.'/'.$jenis->value.'.pdf',
                        'nama_asli' => $jenis->label().'.pdf',
                        'mime_type' => 'application/pdf',
                        'ukuran_bytes' => 180000,
                        'status' => StatusDokumen::Menunggu,
                        'diverifikasi_oleh' => null,
                        'diverifikasi_pada' => null,
                    ],
                );
            }
        }
    }
}
