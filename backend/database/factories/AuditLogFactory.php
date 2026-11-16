<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
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
            'action' => 'santri.activated',
            'subject_type' => null,
            'subject_id' => null,
            'description' => 'Santri diaktifkan setelah seluruh persyaratan terpenuhi.',
            'old_values' => null,
            'new_values' => ['status' => 'aktif'],
            'ip_address' => null,
            'user_agent' => null,
            'created_at' => now(),
        ];
    }
}
