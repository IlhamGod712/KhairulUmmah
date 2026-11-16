<?php

namespace Database\Factories;

use App\Enums\JenisDokumen;
use App\Enums\StatusDokumen;
use App\Models\Dokumen;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dokumen>
 */
class DokumenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $jenis = fake()->randomElement(JenisDokumen::cases());

        return [
            'santri_id' => Santri::factory(),
            'jenis' => $jenis,
            'path' => 'dokumen/'.$jenis->value.'/'.fake()->uuid().'.pdf',
            'nama_asli' => $jenis->label().'.pdf',
            'mime' => 'application/pdf',
            'mime_type' => 'application/pdf',
            'ukuran' => fake()->numberBetween(50_000, 2_000_000),
            'ukuran_bytes' => fake()->numberBetween(50_000, 2_000_000),
            'status' => StatusDokumen::Menunggu,
            'diverifikasi_oleh' => null,
            'diverifikasi_pada' => null,
            'catatan' => null,
        ];
    }

    /**
     * Indicate that an admin accepted the file.
     */
    public function diterima(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusDokumen::Diterima,
            'diverifikasi_oleh' => User::factory()->admin(),
            'diverifikasi_pada' => now(),
        ]);
    }

    /**
     * Indicate that an admin rejected the file.
     */
    public function ditolak(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusDokumen::Ditolak,
            'diverifikasi_oleh' => User::factory()->admin(),
            'diverifikasi_pada' => now(),
            'catatan' => 'Foto tidak terbaca, mohon unggah ulang.',
        ]);
    }
}
