<?php

namespace Database\Factories;

use App\Enums\JenisKelamin;
use App\Enums\SantriStatus;
use App\Models\Santri;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Santri>
 */
class SantriFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $jenisKelamin = fake()->randomElement(JenisKelamin::cases());

        return [
            'wali_id' => User::factory()->wali(),
            'tahun_ajaran_id' => TahunAjaran::factory(),
            'nis' => (string) fake()->unique()->numberBetween(100000, 999999),
            'nisn' => (string) fake()->unique()->numerify('##########'),
            'nama_lengkap' => fake()->name(),
            'jenis_kelamin' => $jenisKelamin,
            'tempat_lahir' => fake()->city(),
            'tanggal_lahir' => fake()->dateTimeBetween('-18 years', '-6 years'),
            'alamat' => fake()->streetAddress(),
            'kelurahan' => fake()->streetName(),
            'kecamatan' => fake()->citySuffix(),
            'kabupaten' => fake()->city(),
            'provinsi' => 'Jawa Barat',
            'no_hp' => '08'.fake()->numerify('##########'),
            'nama_ayah' => fake()->name('male'),
            'nama_ibu' => fake()->name('female'),
            'pekerjaan_ayah' => fake()->randomElement(['Petani', 'Guru', 'Wiraswasta', 'Buruh Pabrik']),
            'pekerjaan_ibu' => fake()->randomElement(['Ibu Rumah Tangga', 'Guru', 'Pedagang']),
            'penghasilan_bulanan' => fake()->numberBetween(1_000_000, 8_000_000),
            'sekolah_asal' => fake()->company().' SD',
            'foto_path' => null,
            'status' => SantriStatus::Pendaftaran,
        ];
    }

    /**
     * Indicate that the student is registered for the current academic year.
     */
    public function aktif(): static
    {
        return $this->state(fn (array $attributes) => ['status' => SantriStatus::Aktif]);
    }

    /**
     * Indicate that the student is on file but not yet linked to any guardian
     * account — the "santri lama" a guardian searches for in Phase 7.
     */
    public function tanpaWali(): static
    {
        return $this->state(fn (array $attributes) => ['wali_id' => null]);
    }

    /**
     * Indicate that the student is waiting on document review.
     */
    public function verifikasi(): static
    {
        return $this->state(fn (array $attributes) => ['status' => SantriStatus::Verifikasi]);
    }

    /**
     * Indicate that the student has passed review and awaits payment.
     */
    public function menungguPembayaran(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SantriStatus::MenungguPembayaran,
        ]);
    }

    /**
     * Indicate that the registration was rejected.
     */
    public function ditolak(): static
    {
        return $this->state(fn (array $attributes) => ['status' => SantriStatus::Ditolak]);
    }

    /**
     * Indicate that the registration was withdrawn.
     *
     * This is the default, but it is named here so a test that sweeps *every*
     * non-active status can call it like the others instead of silently relying
     * on `definition()`.
     */
    public function pendaftaran(): static
    {
        return $this->state(fn (array $attributes) => ['status' => SantriStatus::Pendaftaran]);
    }

    /**
     * Indicate that the student record was cancelled.
     */
    public function dibatalkan(): static
    {
        return $this->state(fn (array $attributes) => ['status' => SantriStatus::Dibatalkan]);
    }

    /**
     * Attach this student to a specific wali.
     */
    public function untukWali(User $wali): static
    {
        return $this->state(fn (array $attributes) => ['wali_id' => $wali->id]);
    }

    /**
     * Attach this student to a specific academic year.
     */
    public function untukTahunAjaran(TahunAjaran $tahunAjaran): static
    {
        return $this->state(fn (array $attributes) => [
            'tahun_ajaran_id' => $tahunAjaran->id,
        ]);
    }
}
