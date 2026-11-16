<?php

namespace Database\Seeders;

use App\Enums\StatusPendaftaran;
use App\Models\Santri;
use Illuminate\Database\Seeder;

class PendaftaranSeeder extends Seeder
{
    /**
     * One registration per student, in the student's own academic year.
     *
     * A list of pairs rather than a NIS-keyed map: "260001" would be cast to an
     * integer array key by PHP, which quietly changes the inferred type.
     *
     * @var list<array{0: string, 1: StatusPendaftaran}>
     */
    protected array $pendaftaran = [
        ['260001', StatusPendaftaran::Diterima],
        ['260002', StatusPendaftaran::Diverifikasi],
        ['260003', StatusPendaftaran::Diajukan],
    ];

    /**
     * Create the registration rows.
     */
    public function run(): void
    {
        foreach ($this->pendaftaran as [$nis, $status]) {
            $santri = Santri::query()->where('nis', $nis)->first();

            if ($santri === null) {
                continue;
            }

            $santri->pendaftarans()->updateOrCreate(
                [
                    'santri_id' => $santri->id,
                    'tahun_ajaran_id' => $santri->tahun_ajaran_id,
                ],
                [
                    'nomor_pendaftaran' => 'PDF-'.$nis,
                    'tanggal_daftar' => now()->startOfYear()->addDays(5),
                    'status' => $status,
                    'catatan' => null,
                ],
            );
        }
    }
}
