<?php

namespace Database\Seeders;

use App\Models\Dokumen;
use App\Models\HistoriStatus;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\Pengaturan;
use App\Models\Santri;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Order matters: accounts, then the academic years they are scoped to, then
     * the student records and everything hanging off them.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            TahunAjaranSeeder::class,
            PengaturanSeeder::class,
            SantriSeeder::class,
            PendaftaranSeeder::class,
            PembayaranSeeder::class,
            DokumenSeeder::class,
            HistoriStatusSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info('Ringkasan data awal:');
        $this->command->table(
            ['Tabel', 'Jumlah'],
            [
                ['users', User::query()->count()],
                ['tahun_ajaran', TahunAjaran::query()->count()],
                ['pengaturan', Pengaturan::query()->count()],
                ['santris', Santri::query()->count()],
                ['pendaftaran', Pendaftaran::query()->count()],
                ['pembayarans', Pembayaran::query()->count()],
                ['dokumen', Dokumen::query()->count()],
                ['histori_status', HistoriStatus::query()->count()],
            ],
        );
    }
}
