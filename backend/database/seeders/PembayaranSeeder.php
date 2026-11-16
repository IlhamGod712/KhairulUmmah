<?php

namespace Database\Seeders;

use App\Enums\JenisPembayaran;
use App\Enums\StatusPembayaran;
use App\Models\Pembayaran;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Database\Seeder;

class PembayaranSeeder extends Seeder
{
    /**
     * One registration-fee payment per student, in the student's own academic
     * year.
     */
    public function run(): void
    {
        $admin = User::query()
            ->where('role', 'admin')
            ->first();

        if ($admin === null) {
            $this->command->warn('Tidak ada user admin; lewati PembayaranSeeder.');

            return;
        }

        $pembayaran = [
            '260001' => ['status' => StatusPembayaran::Lunas, 'jumlah' => '500000.00', 'metode' => 'dana', 'referensi' => 'DANA-260001'],
            '260002' => ['status' => StatusPembayaran::Belum, 'jumlah' => '500000.00', 'metode' => 'transfer_bank', 'referensi' => null],
            '260003' => ['status' => StatusPembayaran::Belum, 'jumlah' => '500000.00', 'metode' => 'tunai', 'referensi' => null],
        ];

        foreach ($pembayaran as $nis => $item) {
            $santri = Santri::query()->where('nis', $nis)->first();

            if ($santri === null) {
                continue;
            }

            $registrasi = $santri->pendaftarans()->first();

            Pembayaran::query()->updateOrCreate(
                [
                    'santri_id' => $santri->id,
                    'tahun_ajaran_id' => $santri->tahun_ajaran_id,
                ],
                $item + [
                    'pendaftaran_id' => $registrasi?->id,
                    'jenis_pembayaran' => JenisPembayaran::Pendaftaran,
                    'tanggal_bayar' => $item['status'] === StatusPembayaran::Lunas
                        ? now()->startOfYear()->addDays(10)
                        : null,
                    'dicatat_oleh' => $admin->id,
                ],
            );
        }
    }
}
