<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\JenisKelamin;
use App\Enums\JenisPembayaran;
use App\Enums\StatusPembayaran;
use App\Enums\StatusPendaftaran;
use App\Enums\UserRole;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\Pengaturan;
use App\Models\Santri;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Explicit demo data for the registration-fee lane, never run by DatabaseSeeder.
 *
 * Why it exists: a database whose payment rows predate `jenis_pembayaran` can
 * end up with an empty registration-fee queue even though the flow works. This
 * seeder creates one self-contained, clearly named demo family — a guardian, a
 * student, an accepted registration, and the unpaid invoice — so the list,
 * upload, verdict, and activation can be demonstrated end to end.
 *
 * What it will not do:
 *
 *  - touch an existing user, student, registration, or payment not created by
 *    this seeder;
 *  - reset the demo rows if someone already progressed them (re-running only
 *    fills a missing row, it never puts a paid bill back to unpaid);
 *  - write SPP invoices, documents, settings, or history rows;
 *  - run automatically. `DatabaseSeeder` deliberately does not call it.
 *
 * It reads the amount from `pengaturan.biaya_pendaftaran`, exactly as the real
 * acceptance path does, so a changed setting is reflected instead of a number
 * copied into a seeder.
 *
 * Run against a testing/demo database only:
 *
 *     php artisan db:seed --class=DemoPembayaranPendaftaranSeeder
 */
class DemoPembayaranPendaftaranSeeder extends Seeder
{
    /**
     * Fixed identifiers make a second run a no-op rather than a second family.
     */
    private const EMAIL = 'demo.wali@khairulummah.test';

    private const NIS = 'DEMO-0001';

    private const NOMOR_PENDAFTARAN = 'DEMO-0001';

    /**
     * The demo password, so the account is usable without a reset mail.
     */
    private const PASSWORD = 'password';

    /**
     * Create the demo family where it is absent.
     */
    public function run(): void
    {
        $tahun = TahunAjaran::query()->where('is_active', true)->first();

        if (! $tahun instanceof TahunAjaran) {
            $this->command->warn('Tidak ada tahun ajaran aktif; lewati DemoPembayaranPendaftaranSeeder.');

            return;
        }

        $nominal = Pengaturan::read('biaya_pendaftaran', '0');

        if ((float) $nominal <= 0) {
            $this->command->warn('Pengaturan biaya_pendaftaran belum diisi; lewati DemoPembayaranPendaftaranSeeder.');

            return;
        }

        $wali = User::query()->firstOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'Wali Demo',
                'password' => self::PASSWORD,
                'role' => UserRole::Wali,
                'no_hp' => '080000000000',
            ],
        );

        $santri = Santri::query()->where('nis', self::NIS)->first();

        if (! $santri instanceof Santri) {
            $santri = Santri::factory()
                ->menungguPembayaran()
                ->untukWali($wali)
                ->untukTahunAjaran($tahun)
                ->create([
                    'nis' => self::NIS,
                    'nisn' => null,
                    'nama_lengkap' => 'Santri Demo',
                    'jenis_kelamin' => JenisKelamin::LakiLaki,
                    'tempat_lahir' => 'Bandung',
                    'tanggal_lahir' => '2013-01-01',
                    'alamat' => 'Jl. Demo No. 1',
                    'nama_ayah' => 'Wali Demo',
                    'nama_ibu' => 'Ibu Demo',
                ]);
        }

        $pendaftaran = Pendaftaran::query()
            ->where('santri_id', $santri->getKey())
            ->where('tahun_ajaran_id', $tahun->getKey())
            ->first();

        if (! $pendaftaran instanceof Pendaftaran) {
            $pendaftaran = Pendaftaran::factory()
                ->diterima()
                ->create([
                    'santri_id' => $santri->getKey(),
                    'tahun_ajaran_id' => $tahun->getKey(),
                    'nomor_pendaftaran' => self::NOMOR_PENDAFTARAN,
                    'tanggal_daftar' => now()->toDateString(),
                ]);
        }

        if ($pendaftaran->status !== StatusPendaftaran::Diterima) {
            $this->command->warn(
                'Pendaftaran '.$pendaftaran->nomor_pendaftaran.' bukan berstatus diterima; '
                .'tidak membuat tagihan demo.',
            );

            return;
        }

        $sudahAda = Pembayaran::query()
            ->where('pendaftaran_id', $pendaftaran->getKey())
            ->where('jenis_pembayaran', JenisPembayaran::Pendaftaran)
            ->exists();

        if ($sudahAda) {
            $this->command->info(
                'Demo pembayaran pendaftaran sudah ada untuk '.self::NIS.'; tidak ada yang dibuat.',
            );

            return;
        }

        Pembayaran::query()->create([
            'pendaftaran_id' => $pendaftaran->getKey(),
            'santri_id' => $santri->getKey(),
            'tahun_ajaran_id' => $tahun->getKey(),
            'jenis_pembayaran' => JenisPembayaran::Pendaftaran,
            'periode' => null,
            'jatuh_tempo' => null,
            'jumlah' => number_format(max(0, (float) $nominal), 2, '.', ''),
            'metode' => null,
            'status' => StatusPembayaran::Belum,
            'tanggal_bayar' => null,
            'referensi' => null,
            'keterangan' => 'Data demo pembayaran pendaftaran.',
            'dicatat_oleh' => null,
        ]);

        $this->command->newLine();
        $this->command->table(
            ['Akun demo', 'Nilai'],
            [
                ['Email wali', self::EMAIL],
                ['Kata sandi', self::PASSWORD],
                ['NIS santri', self::NIS],
                ['Nomor pendaftaran', $pendaftaran->nomor_pendaftaran],
                ['Jumlah tagihan', $nominal],
            ],
        );
    }
}
