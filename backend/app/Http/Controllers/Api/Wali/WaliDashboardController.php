<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Wali;

use App\Enums\SantriStatus;
use App\Enums\StatusPembayaran;
use App\Enums\StatusPendaftaran;
use App\Http\Controllers\Controller;
use App\Http\Resources\WaliProfileResource;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The guardian's landing summary.
 *
 * Every figure is derived from the signed-in guardian's own students, reached
 * through the `wali_id` relation rather than a filter the client could widen.
 * There is no parameter a caller can pass to change whose data this is, which is
 * the point: a guardian cannot ask for another guardian's numbers, because that
 * question cannot be expressed in the first place.
 *
 * The summary is read-only and counts what the current tables hold for this
 * guardian: students by status, this year's registrations, and the payment
 * position. The write flows live on their own endpoints (Phases 6–8); nothing
 * here accepts an id, and every query reaches the rows through `wali_id`, so
 * another guardian's students cannot be expressed in the first place. The
 * figures include zeros on purpose, so the SPA can tell "nothing on file yet"
 * apart from "the request failed" without a second guess.
 */
class WaliDashboardController extends Controller
{
    /**
     * Return the guardian's summary for their own account.
     */
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $wali */
        $wali = $request->user();

        $perStatusSantri = $this->santriPerStatus($wali);

        return response()->json([
            'success' => true,
            'message' => 'Dashboard wali berhasil dimuat.',
            'data' => [
                'wali' => new WaliProfileResource($wali),
                'ringkasan_santri' => [
                    'total' => array_sum($perStatusSantri),
                    'per_status' => $perStatusSantri,
                ],
                'ringkasan_pendaftaran' => $this->pendaftaranSummary($this->waliSantriIds($wali)),
                'ringkasan_tagihan' => $this->tagihanSummary($this->waliSantriIds($wali)),
            ],
        ]);
    }

    /**
     * A fresh subquery of one guardian's student ids.
     *
     * Deliberately returns a new builder on every call rather than one shared
     * instance. An Eloquent builder is mutable: `selectRaw()` or `groupBy()` on
     * a builder passed in as a `whereIn` subquery rewrites that subquery too, so
     * a shared instance would make the sections quietly depend on each other's
     * call order — and the first symptom of that is a database error about a
     * subquery returning the wrong number of columns.
     *
     * @return Builder<Santri>
     */
    private function waliSantriIds(User $wali): Builder
    {
        return $wali->santris()->getQuery()->select('id');
    }

    /**
     * Student counts grouped by lifecycle status.
     *
     * Every case of the enum is reported, including the ones counting zero, so
     * the SPA renders a stable set of tiles instead of a set whose shape changes
     * as a student moves through the pipeline.
     *
     * Built from the relation's own query rather than from `waliSantriIds()`,
     * which carries a `select('id')` projection. `reorder()` clears the ordering
     * but leaves that projection in place, and `selectRaw()` adds to the existing
     * columns rather than replacing them — so the result would be
     * `select id, status, count(*) ... group by status`. SQLite accepts that;
     * MySQL with `ONLY_FULL_GROUP_BY`, which is the default, rejects it because
     * `id` is neither aggregated nor grouped. Passing the guardian rather than a
     * shared builder also keeps this query independent of the other two.
     *
     * @return array<string, int>
     */
    private function santriPerStatus(User $wali): array
    {
        $counts = $wali->santris()
            ->reorder()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $perStatus = [];

        foreach (SantriStatus::cases() as $status) {
            $perStatus[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $perStatus;
    }

    /**
     * Registration attempts belonging to this guardian's students.
     *
     * @param  Builder<Santri>  $santriIds
     * @return array<string, mixed>
     */
    private function pendaftaranSummary(Builder $santriIds): array
    {
        $counts = Pendaftaran::query()
            ->whereIn('santri_id', $santriIds)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $perStatus = [];

        foreach (StatusPendaftaran::cases() as $status) {
            $perStatus[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return [
            'total' => array_sum($perStatus),
            'per_status' => $perStatus,
        ];
    }

    /**
     * Billing position for this guardian's students.
     *
     * `jumlah` is a `decimal(15,2)` column, so the database does the summing in
     * decimal arithmetic and the result arrives as a string. It is converted to
     * integer cents exactly once per figure, here, and only for rendering —
     * summing in PHP floats across rows is what produces totals that are off by
     * a cent.
     *
     * @param  Builder<Santri>  $santriIds
     * @return array<string, mixed>
     */
    private function tagihanSummary(Builder $santriIds): array
    {
        $perStatus = [];

        foreach (StatusPembayaran::cases() as $status) {
            $perStatus[$status->value] = Pembayaran::query()
                ->whereIn('santri_id', $santriIds)
                ->where('status', $status->value)
                ->count();
        }

        $totalBelum = $this->centsFor($santriIds, StatusPembayaran::Belum);
        $totalLunas = $this->centsFor($santriIds, StatusPembayaran::Lunas);

        return [
            'total_tagihan' => $this->rupiah($totalBelum),
            'total_lunas' => $this->rupiah($totalLunas),
            'per_status' => $perStatus,
        ];
    }

    /**
     * Sum one payment status for the given students, in integer cents.
     *
     * @param  Builder<Santri>  $santriIds
     */
    private function centsFor(Builder $santriIds, StatusPembayaran $status): int
    {
        $sum = Pembayaran::query()
            ->whereIn('santri_id', $santriIds)
            ->where('status', $status->value)
            ->sum('jumlah');

        return (int) round(((float) $sum) * 100);
    }

    /**
     * Render integer cents back to a fixed two-decimal string.
     */
    private function rupiah(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
