<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\JenisPembayaran;
use App\Enums\SantriStatus;
use App\Enums\StatusPembayaran;
use App\Models\Pembayaran;
use App\Models\Pengaturan;
use App\Models\Santri;
use App\Models\TahunAjaran;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Raise one month of SPP for every active student.
 *
 * This is the billing rule, kept out of the controller because it is a fact about
 * the school, not about HTTP: the same month must be billable from the admin UI
 * today and from a scheduled command or a backfill next term, and neither should
 * have to re-state who counts as active or where the amount comes from.
 *
 * **Only active students are billed.** The `status = 'aktif'` filter is in the
 * query that decides who to bill, not a check afterwards, and it reads the
 * student's *current* status. Nothing here back-fills: generating September does
 * not hand a student who activated in December a bill for the months before
 * they arrived.
 *
 * The corollary is worth stating plainly, because it is the one way this class
 * can overcharge: the filter reads *current* status, so re-running a month that
 * is already in the past will bill students who have become `aktif` since. That
 * is a deliberate consequence of not asking "when did this student turn up?" —
 * eligibility is a live fact, not a history — but it means a past month is only
 * safe to re-run when the active roster is still the one that was billed.
 *
 * **The database is what makes this idempotent.** Each `(santri, academic year,
 * period)` goes in through a unique index rather than a read-then-write check, so
 * a second run of the same month is a no-op rather than a doubled bill, and two
 * admins pressing the button at once cannot both win. The caller still has to
 * open the transaction — the index protects the rows, the transaction keeps the
 * "how many were new" count honest against a concurrent run.
 *
 * The amount is the school's `biaya_spp_bulanan` setting read here, so nothing
 * that reaches this class can carry a price.
 */
class SppGenerator
{
    /**
     * The school setting key for the monthly fee.
     */
    public const NOMINAL_KEY = 'biaya_spp_bulanan';

    /**
     * The school setting key for the day of the month a bill falls due.
     */
    public const JATUH_TEMPO_KEY = 'spp_tanggal_jatuh_tempo';

    /**
     * Insert one invoice per active student in `$tahun` for `$periode`, and
     * report honestly how many were new.
     *
     * The rows are all server-derived — ids from the students just selected, the
     * amount from the settings, the period from the caller after validation — so
     * `insertOrIgnore` is used for the one thing it is good at, letting the
     * unique index drop the rows that are already billed, and not to paper over a
     * bad value. Counts are taken before and after the insert so a repeat run
     * says "0 created, N already there" instead of implying it billed everyone
     * again.
     *
     * @return array{periode: string, periode_label: string, nominal: string, jatuh_tempo: string, total_santri_aktif: int, dibuat: int, dilewati: int}
     */
    public function jalankan(TahunAjaran $tahun, Carbon $periode): array
    {
        $nominal = $this->nominal();
        $jatuhTempo = $periode->copy()->day($this->hariJatuhTempo());

        $santri = Santri::query()
            ->where('tahun_ajaran_id', $tahun->getKey())
            ->where('status', SantriStatus::Aktif->value)
            ->orderBy('id')
            ->get(['id']);

        $dibuat = 0;

        if ($santri->isNotEmpty()) {
            $dibuat = DB::table('pembayarans')->insertOrIgnore(
                $santri->map(fn (Santri $item): array => [
                    'santri_id' => $item->getKey(),
                    'tahun_ajaran_id' => $tahun->getKey(),
                    'jenis_pembayaran' => JenisPembayaran::Spp->value,
                    'periode' => $periode->toDateString(),
                    'jatuh_tempo' => $jatuhTempo->toDateString(),
                    'jumlah' => $nominal,
                    'metode' => null,
                    'status' => StatusPembayaran::Belum->value,
                    'tanggal_bayar' => null,
                    'referensi' => null,
                    'keterangan' => null,
                    // Nobody has received this money yet, so nobody is recorded
                    // as having recorded it. `dicatat_oleh` fills in at the
                    // verdict, alongside the verification columns.
                    'dicatat_oleh' => null,
                    'diverifikasi_oleh' => null,
                    'diverifikasi_pada' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all()
            );
        }

        return [
            'periode' => $periode->toDateString(),
            'periode_label' => Pembayaran::labelPeriode($periode) ?? $periode->format('Y-m'),
            'nominal' => $nominal,
            'jatuh_tempo' => $jatuhTempo->toDateString(),
            'total_santri_aktif' => $santri->count(),
            'dibuat' => $dibuat,
            'dilewati' => $santri->count() - $dibuat,
        ];
    }

    /**
     * The monthly fee, as a fixed two-decimal string.
     *
     * Read as a string and never cast to a float beyond formatting, because
     * `jumlah` is `decimal(15,2)` and summing floats across rows is what produces
     * a total that is a cent off.
     */
    public function nominal(): string
    {
        return number_format(max(0, (float) Pengaturan::read(self::NOMINAL_KEY, '0')), 2, '.', '');
    }

    /**
     * The day of the month an SPP bill falls due.
     *
     * Read from the settings so the school can move it, clamped to 1..28 so a
     * two-day February or a 30th can never produce a due date that does not
     * exist. The day is stored on the invoice, so moving the setting later cannot
     * alter a due date a guardian has already been shown.
     */
    public function hariJatuhTempo(): int
    {
        $hari = (int) Pengaturan::read(self::JATUH_TEMPO_KEY, '10');

        if ($hari < 1) {
            return 1;
        }

        return min($hari, 28);
    }
}
