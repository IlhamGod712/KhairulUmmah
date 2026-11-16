<?php

namespace Database\Factories;

use App\Models\Pengaturan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pengaturan>
 */
class PengaturanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kunci' => 'pengaturan.'.fake()->unique()->slug(2),
            'nilai' => fake()->word(),
            'tipe' => 'string',
        ];
    }

    /**
     * Indicate a numeric setting.
     */
    public function integer(): static
    {
        return $this->state(fn (array $attributes) => [
            'nilai' => (string) fake()->numberBetween(100_000, 5_000_000),
            'tipe' => 'integer',
        ]);
    }

    /**
     * Indicate a boolean setting.
     */
    public function boolean(): static
    {
        return $this->state(fn (array $attributes) => [
            'nilai' => fake()->boolean() ? 'true' : 'false',
            'tipe' => 'boolean',
        ]);
    }
}
