<?php

namespace Database\Seeders;

use App\Enums\SantriStatus;
use App\Models\HistoriStatus;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Database\Seeder;

class HistoriStatusSeeder extends Seeder
{
    /**
     * Status trail to seed. Each inner list is a series of transitions written as
     * its own row, so `Santri::historiStatus()` returns a full ordered audit.
     *
     * A list of pairs rather than a NIS-keyed map: "260001" would be cast to an
     * integer array key by PHP, which quietly changes the inferred type.
     *
     * @var list<array{0: string, 1: list<array{0: SantriStatus|null, 1: SantriStatus}>}>
     */
    protected array $riwayat = [
        ['260001', [
            [null, SantriStatus::Pendaftaran],
            [SantriStatus::Pendaftaran, SantriStatus::Verifikasi],
            [SantriStatus::Verifikasi, SantriStatus::MenungguPembayaran],
            [SantriStatus::MenungguPembayaran, SantriStatus::Aktif],
        ]],
        ['260002', [
            [null, SantriStatus::Pendaftaran],
            [SantriStatus::Pendaftaran, SantriStatus::Verifikasi],
            [SantriStatus::Verifikasi, SantriStatus::MenungguPembayaran],
        ]],
        ['260003', [
            [null, SantriStatus::Pendaftaran],
            [SantriStatus::Pendaftaran, SantriStatus::Verifikasi],
        ]],
    ];

    /**
     * Write one audit row per step, oldest first.
     */
    public function run(): void
    {
        $admin = User::query()
            ->where('role', 'admin')
            ->first();

        foreach ($this->riwayat as [$nis, $langkah]) {
            $santri = Santri::query()->where('nis', $nis)->first();

            if ($santri === null) {
                continue;
            }

            // Clear previous entries so re-seeding stays idempotent.
            $santri->historiStatus()->delete();

            $waktu = now()->startOfYear()->addDays(5);

            foreach ($langkah as $index => [$dari, $ke]) {
                HistoriStatus::query()->create([
                    'santri_id' => $santri->id,
                    'dari_status' => $dari,
                    'ke_status' => $ke,
                    'keterangan' => $index === 0
                        ? 'Pendaftaran baru dibuat oleh wali.'
                        : 'Status diperbarui oleh admin.',
                    // The first row is the wali's own action, the rest the admin's.
                    'oleh' => $index === 0 ? $santri->wali_id : $admin?->id,
                    'pada' => $waktu->copy()->addMinutes($index * 10),
                ]);
            }
        }
    }
}
