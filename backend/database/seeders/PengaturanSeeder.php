<?php

namespace Database\Seeders;

use App\Models\Pengaturan;
use Illuminate\Database\Seeder;

class PengaturanSeeder extends Seeder
{
    /**
     * Values the admin can edit from the UI later.
     *
     * @var array<string, array{0: string, 1: string}> kunci => [nilai, tipe]
     */
    protected array $settings = [
        'nama_pondok' => ['Pondok Pesantren Terpadu Khairul Ummah', 'string'],
        'alamat_pondok' => ['Jl. Pendidikan No. 1, Kabupaten Bandung', 'string'],
        'email_pondok' => ['info@khairulummah.sch.id', 'string'],
        'no_telepon_pondok' => ['022-87654321', 'string'],
        'nomor_dana' => ['081234567890', 'string'],
        'nama_penerima_dana' => ['Bendahara Khairul Ummah', 'string'],
        'nama_bank' => ['Bank Syariah Indonesia', 'string'],
        'nomor_rekening' => ['7123456789', 'string'],
        'atas_nama_rekening' => ['Pondok Pesantren Terpadu Khairul Ummah', 'string'],
        'biaya_pendaftaran' => ['500000', 'integer'],
        'biaya_spp_bulanan' => ['750000', 'integer'],
        // Which day of the billed month an SPP invoice falls due. Phase 8 reads
        // this when raising the bill and stores the resulting date on the
        // invoice, so a later change cannot move a due date a guardian has
        // already seen. Clamped to 1..28 by the generator.
        'spp_tanggal_jatuh_tempo' => ['10', 'integer'],
    ];

    /**
     * Insert or refresh every setting.
     */
    public function run(): void
    {
        foreach ($this->settings as $kunci => [$nilai, $tipe]) {
            Pengaturan::write($kunci, $nilai, $tipe);
        }
    }
}
