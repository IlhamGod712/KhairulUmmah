<?php

namespace Database\Factories;

use App\Enums\StatusPendaftaran;
use App\Models\Pendaftaran;
use App\Models\Santri;
use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pendaftaran>
 */
class PendaftaranFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $santri = Santri::factory();

        return [
            'santri_id' => $santri,
            // A registration always belongs to the student's own academic year.
            'tahun_ajaran_id' => fn (array $attributes) => $this->tahunAjaranFor($attributes),
            'nomor_pendaftaran' => 'PDF-'.fake()->unique()->numerify('#####'),
            'tanggal_daftar' => now()->toDateString(),
            'status' => StatusPendaftaran::Diajukan,
            'diproses_oleh' => null,
            'catatan' => null,
        ];
    }

    /**
     * Reuse the academic year of the student being registered, so the
     * (santri_id, tahun_ajaran_id) unique index always holds.
     *
     * Laravel expands attributes in declaration order, so by the time this runs
     * `santri_id` is already an id rather than the model. Both forms are
     * therefore accepted, at the cost of one lookup in the id case.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function tahunAjaranFor(array $attributes): mixed
    {
        $santri = $attributes['santri_id'] ?? null;

        if ($santri instanceof Santri) {
            return $santri->exists ? $santri->tahun_ajaran_id : TahunAjaran::factory();
        }

        if (is_numeric($santri)) {
            $ada = Santri::find((int) $santri);

            return $ada instanceof Santri ? $ada->tahun_ajaran_id : TahunAjaran::factory();
        }

        return TahunAjaran::factory();
    }

    /**
     * Indicate that the submission passed document review.
     */
    public function diverifikasi(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusPendaftaran::Diverifikasi,
        ]);
    }

    /**
     * Indicate that the submission was accepted.
     */
    public function diterima(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusPendaftaran::Diterima,
        ]);
    }

    /**
     * Indicate that the submission was rejected.
     */
    public function ditolak(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusPendaftaran::Ditolak,
            'catatan' => 'Berkas tidak sesuai ketentuan.',
        ]);
    }
}
