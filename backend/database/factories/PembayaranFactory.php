<?php

namespace Database\Factories;

use App\Enums\JenisPembayaran;
use App\Enums\MetodePembayaran;
use App\Enums\StatusPembayaran;
use App\Models\Pembayaran;
use App\Models\Santri;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Pembayaran>
 */
class PembayaranFactory extends Factory
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
            'tahun_ajaran_id' => fn (array $attributes) => $this->tahunAjaranFor($attributes),
            'jumlah' => fake()->randomFloat(2, 100_000, 5_000_000),
            'metode' => fake()->randomElement(MetodePembayaran::cases()),
            'status' => StatusPembayaran::Belum,
            'tanggal_bayar' => null,
            'referensi' => null,
            'keterangan' => null,
            'dicatat_oleh' => User::factory()->admin(),
        ];
    }

    /**
     * Reuse the academic year of the student the payment belongs to.
     *
     * Attributes are expanded in declaration order, so `santri_id` has already
     * been reduced to an id by the time this runs; both forms are accepted, at
     * the cost of one lookup in the id case.
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
     * Indicate that the payment has been settled in cash.
     */
    public function lunasTunai(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusPembayaran::Lunas,
            'metode' => MetodePembayaran::Tunai,
            'tanggal_bayar' => now()->toDateString(),
            'referensi' => 'TNH-'.fake()->unique()->numerify('#####'),
        ]);
    }

    /**
     * Indicate that the payment was received through DANA.
     */
    public function lunasDana(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusPembayaran::Lunas,
            'metode' => MetodePembayaran::Dana,
            'tanggal_bayar' => now()->toDateString(),
            'referensi' => 'DANA-'.fake()->unique()->numerify('##########'),
        ]);
    }

    /**
     * A monthly SPP invoice for the given month, unpaid.
     *
     * A *real* one, in the sense the application means it: it carries both
     * `jenis_pembayaran = 'spp'` and a `periode`. The Phase 6 migration left the
     * kind defaulting to `spp` for rows written before that column existed, so a
     * factory state that set only the kind would produce something that reads as
     * SPP on the type but is excluded by the `tagihanSpp` scope — this state
     * always sets the period so tests are exercising the same thing the
     * generator does.
     *
     * @param  string  $periode  Any date inside the month to bill.
     */
    public function spp(string $periode = '2026-07-01'): static
    {
        return $this->state(fn (array $attributes): array => [
            'jenis_pembayaran' => JenisPembayaran::Spp,
            'periode' => Carbon::parse($periode)->startOfMonth()->toDateString(),
            'jatuh_tempo' => Carbon::parse($periode)->startOfMonth()->day(10)->toDateString(),
            'pendaftaran_id' => null,
            'status' => StatusPembayaran::Belum,
            'metode' => null,
            'tanggal_bayar' => null,
        ]);
    }

    /**
     * Indicate that the payment was received by bank transfer.
     */
    public function lunasTransferBank(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusPembayaran::Lunas,
            'metode' => MetodePembayaran::TransferBank,
            'tanggal_bayar' => now()->toDateString(),
            'referensi' => 'TRF-'.fake()->unique()->numerify('#####'),
        ]);
    }
}
