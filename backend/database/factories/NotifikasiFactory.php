<?php

namespace Database\Factories;

use App\Models\Notifikasi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notifikasi>
 */
class NotifikasiFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tipe' => 'santri_aktif',
            'judul' => 'Santri Diaktifkan',
            'pesan' => 'Santri telah diaktifkan.',
            'url' => null,
            'data' => [],
            'dibaca_pada' => null,
        ];
    }
}
