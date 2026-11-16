<?php

namespace Database\Factories;

use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TahunAjaran>
 */
class TahunAjaranFactory extends Factory
{
    /**
     * The year the factory generates data for, used as the base for `nama`.
     */
    protected static ?int $baseYear;

    /**
     * Incremented per generated model so `nama` stays unique within a test run.
     */
    protected static int $sequence = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static::$baseYear ??= (int) now()->year;

        $start = static::$baseYear + static::$sequence++;

        return [
            'nama' => $start.'/'.($start + 1),
            'tanggal_mulai' => now()->startOfYear(),
            'tanggal_selesai' => now()->startOfYear()->addYear()->subDay(),
            'is_active' => false,
        ];
    }

    /**
     * Indicate that this is the academic year currently in use.
     */
    public function aktif(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => true]);
    }
}
