<?php

namespace Database\Factories;

use App\Enums\SantriStatus;
use App\Models\HistoriStatus;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HistoriStatus>
 */
class HistoriStatusFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'santri_id' => Santri::factory(),
            // The very first entry has no previous status.
            'dari_status' => null,
            'ke_status' => SantriStatus::Pendaftaran,
            'keterangan' => 'Pendaftaran baru dibuat oleh wali.',
            'oleh' => null,
            'pada' => now(),
        ];
    }

    /**
     * Indicate a transition performed by a specific user.
     */
    public function oleh(User $user): static
    {
        return $this->state(fn (array $attributes) => ['oleh' => $user->id]);
    }

    /**
     * Indicate a transition from one status to another.
     */
    public function transisi(SantriStatus $dari, SantriStatus $ke): static
    {
        return $this->state(fn (array $attributes) => [
            'dari_status' => $dari,
            'ke_status' => $ke,
        ]);
    }
}
