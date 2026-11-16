<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Wali;

use App\Enums\SantriStatus;
use App\Enums\StatusPendaftaran;
use App\Http\Controllers\Controller;
use App\Http\Requests\Wali\StorePendaftaranRequest;
use App\Http\Resources\PendaftaranDetailResource;
use App\Http\Resources\PendaftaranResource;
use App\Models\Pendaftaran;
use App\Models\Santri;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\AuditTrailService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A guardian submitting a new student, and reading back their own submissions.
 *
 * Three properties are load bearing here.
 *
 * **Ownership is the query, not a filter applied afterwards.** Both reads reach
 * the registrations through `whereHas('santri', wali_id = the session's user)`,
 * so there is no parameter a caller can pass to widen the scope. The detail
 * endpoint is the sharper version of the same idea: the ownership predicate is
 * part of the lookup, so another guardian's id is simply not found. That is a
 * 404, never a 403, because a 403 would confirm the row exists.
 *
 * **Nothing authoritative comes from the client.** The academic year, the
 * registration number, the student number, the submission date, and both
 * statuses are assigned here. `wali_id` is `$request->user()`, full stop. The
 * request declares only the columns a guardian may fill in, and the columns are
 * assigned one at a time rather than through `fill()`, so a crafted payload
 * carrying `wali_id` or `status: aktif` has nowhere to land.
 *
 * **A new registration is not an active student.** It produces a Santri in
 * `SantriStatus::Pendaftaran` and a Pendaftaran in `StatusPendaftaran::Diajukan`.
 * The pipeline to `Aktif` runs through acceptance, payment, and payment
 * verification, which is Phase 6. Nothing in this file can shortcut it.
 *
 * Status mapping, since the brief named three states and the schema has four:
 *
 *   MENUNGGU_VERIFIKASI -> `diajukan`      (submitted, admin has not acted)
 *   (review in progress) -> `diverifikasi`  (admin picked it up)
 *   DITERIMA             -> `diterima`     (accepted, still owes the fee)
 *   DITOLAK              -> `ditolak`
 *
 * `diajukan` is the existing spelling of "waiting for verification"; renaming
 * the enum would be a destructive migration over data that Phase 4 already
 * reads, so the value stays and the UI label carries the meaning.
 */
class WaliPendaftaranController extends Controller
{
    public function __construct(private readonly AuditTrailService $audit) {}

    /**
     * How many times a submission may regenerate its assigned numbers before
     * the underlying database error is surfaced.
     *
     * Two are enough in practice. The lock below makes a conflict impossible
     * against MySQL; this loop exists for the SQLite suite, where `FOR UPDATE`
     * is not emitted, and as a backstop if a row is inserted by something other
     * than this controller between the read and the write.
     */
    private const PERCOBAAN_NOMOR = 3;

    /**
     * Register a new student, on behalf of the signed-in guardian.
     */
    public function store(StorePendaftaranRequest $request): JsonResponse
    {
        /** @var User $wali */
        $wali = $request->user();

        $tahunAjaran = TahunAjaran::query()->active()->first();

        // A submission has to land in a specific academic year, and the only
        // defensible one is the one currently in force. Asking the guardian to
        // pick would be asking them to know something the school decides.
        if (! $tahunAjaran instanceof TahunAjaran) {
            return response()->json([
                'success' => false,
                'message' => 'Belum ada tahun ajaran aktif, sehingga pendaftaran tidak dapat diproses. '
                    .'Silakan hubungi admin / tata usaha.',
                'data' => null,
            ], 409);
        }

        $pendaftaran = $this->daftarkan($wali, $tahunAjaran, $request);

        $pendaftaran->load(['santri.wali', 'santri.pembayarans', 'santri.buktiPembayaran', 'tahunAjaran']);

        return response()->json([
            'success' => true,
            'message' => 'Pendaftaran berhasil diajukan dan sedang menunggu verifikasi admin / tata usaha.',
            'data' => new PendaftaranDetailResource($pendaftaran),
        ], 201);
    }

    /**
     * The registrations this guardian has submitted.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $wali */
        $wali = $request->user();

        $pendaftarans = Pendaftaran::query()
            ->whereHas('santri', $this->milik($wali))
            ->with(['santri.wali', 'santri.pembayarans', 'santri.buktiPembayaran', 'tahunAjaran'])
            ->latest('tanggal_daftar')
            ->latest('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar pendaftaran berhasil dimuat.',
            'data' => PendaftaranResource::collection($pendaftarans),
        ]);
    }

    /**
     * One of this guardian's own registrations.
     */
    public function show(Request $request, int $pendaftaran): JsonResponse
    {
        /** @var User $wali */
        $wali = $request->user();

        $model = Pendaftaran::query()
            ->whereKey($pendaftaran)
            ->whereHas('santri', $this->milik($wali))
            ->with(['santri.wali', 'santri.pembayarans', 'santri.buktiPembayaran', 'tahunAjaran'])
            ->first();

        // Ownership was part of the lookup, so "absent" and "someone else's"
        // are the same answer here on purpose. Distinguishing them would tell a
        // guardian which registration ids exist.
        if (! $model instanceof Pendaftaran) {
            throw (new ModelNotFoundException)->setModel(Pendaftaran::class, [$pendaftaran]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail pendaftaran berhasil dimuat.',
            'data' => new PendaftaranDetailResource($model),
        ]);
    }

    /**
     * The ownership predicate, shared by both reads.
     *
     * Declared as a `Closure` rather than a `callable` because that is what
     * `whereHas()` declares in its signature, and Larastan level 7 is right to
     * refuse the looser type.
     *
     * @return Closure(Builder<Santri>): Builder<Santri>
     */
    private function milik(User $wali): Closure
    {
        return fn (Builder $query): Builder => $query->where('wali_id', $wali->getKey());
    }

    /**
     * Create the student record and the registration that points at it.
     *
     * Both rows are written in one transaction: a registration whose student
     * vanished, or a student whose registration never landed, would both be
     * states the admin surface cannot explain.
     */
    private function daftarkan(User $wali, TahunAjaran $tahunAjaran, StorePendaftaranRequest $request): Pendaftaran
    {
        $percobaan = 0;

        while (true) {
            $percobaan++;

            try {
                return DB::transaction(function () use ($wali, $tahunAjaran, $request): Pendaftaran {
                    // Held for the rest of the transaction. On MySQL this is a
                    // real `FOR UPDATE`, which serialises concurrent submissions
                    // for this academic year so the sequence numbers below are
                    // allocated one at a time. On SQLite the grammar drops the
                    // clause entirely, so the same code still runs in the test
                    // suite — just without the mutual exclusion.
                    $tahun = $this->kunciTahunAjaran($tahunAjaran);

                    $data = $this->kolomSantri($request);

                    // Re-checked here rather than only in the form request.
                    // Validation runs before the transaction opens, so a second
                    // click arriving a moment later would have passed it; the
                    // first submission is already committed by the time this
                    // one holds the lock, so it has to be asked again.
                    $this->tolakPendaftaranGanda($wali, $data);

                    $santri = $this->buatSantri($wali, $tahun, $data);

                    $pendaftaran = $this->buatPendaftaran($santri, $tahun);

                    $this->audit->log(
                        'pendaftaran.submitted',
                        'Wali mengajukan pendaftaran santri.',
                        $pendaftaran,
                        newValues: ['status' => StatusPendaftaran::Diajukan->value],
                        request: $request,
                    );

                    return $pendaftaran;
                });
            } catch (QueryException $exception) {
                // A unique index is the last line of defence for the generated
                // numbers. Losing that race is not a failure; the sequence is
                // re-read and the whole attempt is repeated.
                if ($percobaan >= self::PERCOBAAN_NOMOR || ! $this->konflikUnik($exception)) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * Re-read the academic year under a row lock.
     */
    private function kunciTahunAjaran(TahunAjaran $tahunAjaran): TahunAjaran
    {
        return TahunAjaran::query()
            ->whereKey($tahunAjaran->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * The student columns the guardian supplied, and nothing else.
     *
     * Reads through the request's allow-list rather than the raw payload, so a
     * `wali_id` or `status` in the body is not merely unvalidated — it is never
     * looked at.
     *
     * @return array<string, mixed>
     */
    private function kolomSantri(StorePendaftaranRequest $request): array
    {
        $validated = $request->validated();

        $kolom = [];

        foreach ($request->kolomSantri() as $nama) {
            $kolom[$nama] = $validated[$nama] ?? null;
        }

        return $kolom;
    }

    /**
     * Refuse a second live registration for the same child.
     *
     * The registration table's own unique index is `(santri_id,
     * tahun_ajaran_id)`, which cannot catch this: a second submission creates a
     * *new* student row, so both registrations are legitimately distinct as far
     * as the database is concerned. The identity that actually repeats is the
     * guardian's own name-plus-birth-date pair, and that is what is checked.
     *
     * A rejected or cancelled student is excluded, because a guardian filing
     * again after a refusal is a legitimate new attempt, not a double click.
     *
     * `whereDate` rather than an equality comparison: MariaDB stores a `date`
     * column as `2013-05-17` while SQLite stores the model's cast value as
     * `2013-05-17 00:00:00`, so a raw comparison matches on one engine and
     * silently never matches on the other. The query is already narrowed to one
     * guardian and one name, both indexed, so wrapping the column in a function
     * costs nothing that matters.
     *
     * Matching is exact. A near-duplicate differing only in capitalisation is
     * a data-quality question for the admin reviewing the file, not something
     * to guess at here, and the two engines disagree about case folding anyway.
     *
     * @param  array<string, mixed>  $data
     */
    private function tolakPendaftaranGanda(User $wali, array $data): void
    {
        $sudahAda = Santri::query()
            ->where('wali_id', $wali->getKey())
            ->where('nama_lengkap', $data['nama_lengkap'])
            ->whereDate('tanggal_lahir', $data['tanggal_lahir'])
            ->whereNotIn('status', $this->statusFinal())
            ->exists();

        if ($sudahAda) {
            throw ValidationException::withMessages([
                'nama_lengkap' => 'Calon santri dengan nama dan tanggal lahir yang sama sudah pernah didaftarkan '
                    .'dan masih berjalan. Periksa daftar pendaftaran Anda.',
            ]);
        }
    }

    /**
     * The statuses a student never leaves, as column values.
     *
     * @return array<int, string>
     */
    private function statusFinal(): array
    {
        return array_values(array_map(
            fn (SantriStatus $status): string => $status->value,
            array_filter(SantriStatus::cases(), fn (SantriStatus $status): bool => $status->isFinal()),
        ));
    }

    /**
     * Insert the student, in the status that means "submitted, not a student of
     * the school yet".
     *
     * @param  array<string, mixed>  $data
     */
    private function buatSantri(User $wali, TahunAjaran $tahunAjaran, array $data): Santri
    {
        $santri = new Santri;

        // Assigned one by one on purpose. The `#[Fillable]` attribute on the
        // model is broad enough for the admin surface, and the fields a guardian
        // may never reach are mixed in with the ones they may.
        $santri->wali_id = $wali->getKey();
        $santri->tahun_ajaran_id = $tahunAjaran->getKey();
        $santri->nis = $this->nomorBerikut('santris', 'nis', $this->prefixNis($tahunAjaran));
        $santri->nisn = $data['nisn'];
        $santri->nama_lengkap = $data['nama_lengkap'];
        $santri->jenis_kelamin = $data['jenis_kelamin'];
        $santri->tempat_lahir = $data['tempat_lahir'];
        $santri->tanggal_lahir = $data['tanggal_lahir'];
        $santri->alamat = $data['alamat'];
        $santri->kelurahan = $data['kelurahan'];
        $santri->kecamatan = $data['kecamatan'];
        $santri->kabupaten = $data['kabupaten'];
        $santri->provinsi = $data['provinsi'];
        $santri->no_hp = $data['no_hp'];
        $santri->nama_ayah = $data['nama_ayah'];
        $santri->nama_ibu = $data['nama_ibu'];
        $santri->pekerjaan_ayah = $data['pekerjaan_ayah'];
        $santri->pekerjaan_ibu = $data['pekerjaan_ibu'];
        $santri->penghasilan_bulanan = $data['penghasilan_bulanan'];
        $santri->sekolah_asal = $data['sekolah_asal'];
        $santri->foto_path = null;
        // The only status a fresh registration can produce. Not `aktif`, and
        // not `menunggu_pembayaran` either: neither acceptance nor payment has
        // happened yet.
        $santri->status = SantriStatus::Pendaftaran;
        $santri->save();

        return $santri;
    }

    /**
     * Insert the registration pointing at the student just created.
     */
    private function buatPendaftaran(Santri $santri, TahunAjaran $tahunAjaran): Pendaftaran
    {
        $pendaftaran = new Pendaftaran;

        $pendaftaran->santri_id = $santri->getKey();
        // Matches the student's year, so the `(santri_id, tahun_ajaran_id)`
        // unique index holds and the registration describes the year the student
        // is actually being admitted to.
        $pendaftaran->tahun_ajaran_id = $tahunAjaran->getKey();
        $pendaftaran->nomor_pendaftaran = $this->nomorBerikut(
            'pendaftaran',
            'nomor_pendaftaran',
            $this->prefixPendaftaran($tahunAjaran),
        );
        $pendaftaran->tanggal_daftar = $this->tanggalDaftar();
        // Also the column's default, stated here so the initial status of a
        // submission is visible in this file rather than only in the migration.
        $pendaftaran->status = StatusPendaftaran::Diajukan;
        // Phase 6 territory: the admin who accepts or rejects.
        $pendaftaran->diproses_oleh = null;
        $pendaftaran->catatan = null;
        $pendaftaran->save();

        return $pendaftaran;
    }

    /**
     * Today, as a value the model's `date` cast declares it accepts.
     *
     * `AppServiceProvider` calls `Date::use(CarbonImmutable::class)`, so the
     * `now()` and `today()` helpers hand back a `CarbonImmutable` — while every
     * model's PHPDoc and `date` cast declare `Illuminate\Support\Carbon`.
     * Reaching for the concrete class instead of the helper is the way to
     * satisfy both, without editing the Phase 1-3 models to match a global
     * setting that predates them. Eloquent serialises the value either way, so
     * nothing observable depends on which one it is.
     */
    private function tanggalDaftar(): Carbon
    {
        return Carbon::today();
    }

    /**
     * Next unused number for a column, within one academic year.
     *
     * `max()` over a string column is a lexicographic maximum, which is exactly
     * right here because the suffix is zero padded to a fixed width. Reading the
     * maximum rather than counting means a registration that is deleted while
     * later ones remain leaves a gap instead of handing its receipt out twice.
     *
     * It does not survive the last row being removed: with an empty table there
     * is no maximum to read, and the sequence starts over at one. Guaranteeing
     * non-reuse across deletion would need a counter that is never itself
     * deleted, and no phase of this application deletes registrations — there is
     * no delete endpoint — so the unique index is the guarantee that actually
     * holds, and the loop above is what makes losing that race survivable.
     */
    private function nomorBerikut(string $tabel, string $kolom, string $prefix): string
    {
        $terakhir = DB::table($tabel)->where($kolom, 'like', $prefix.'%')->max($kolom);
        $urutan = 1;

        if (is_string($terakhir) && str_starts_with($terakhir, $prefix)) {
            $urutan = ((int) substr($terakhir, strlen($prefix))) + 1;
        }

        return $prefix.str_pad((string) $urutan, 5, '0', STR_PAD_LEFT);
    }

    /**
     * `PDF-26-` — the year comes from the academic year's start date rather than
     * from the wall clock, so a registration filed in the first weeks of a new
     * calendar year still lands under the year it is actually for.
     */
    private function prefixPendaftaran(TahunAjaran $tahunAjaran): string
    {
        return 'PDF-'.$tahunAjaran->tanggal_mulai->format('y').'-';
    }

    /**
     * `P26` — the school issues this, so it is generated rather than asked for.
     */
    private function prefixNis(TahunAjaran $tahunAjaran): string
    {
        return 'P'.$tahunAjaran->tanggal_mulai->format('y');
    }

    /**
     * Whether a driver rejected a row for violating a unique index.
     *
     * 1062 is MySQL and MariaDB, 19 is SQLite's generic constraint failure and
     * 2067 its specific unique violation.
     */
    private function konflikUnik(QueryException $exception): bool
    {
        $kode = $exception->errorInfo[1] ?? null;

        return in_array($kode, [1062, 19, 2067], true);
    }
}
