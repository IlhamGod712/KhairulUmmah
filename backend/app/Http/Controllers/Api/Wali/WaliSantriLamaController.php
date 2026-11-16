<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Wali;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wali\MatchSantriLamaRequest;
use App\Http\Resources\SantriResource;
use App\Models\Santri;
use App\Models\User;
use App\Services\AuditTrailService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Let a guardian claim an existing, previously unlinked student record.
 *
 * The website predates guardian accounts, so rows on `santris` can exist with
 * no wali attached. This controller is the two-step flow that attaches one:
 * identification first, then linking.
 *
 * **The student is identified by the search, never by an id from the client.**
 * Both endpoints take the same three fields — NIS/NISN, name, date of birth —
 * and the register call re-runs that search inside the request. There is no
 * `santri_id` any caller could send, so it is impossible to express "link
 * whatever id I say": the only student a guardian can ever claim is the one
 * whose stored numbers, name, and birth date match what they typed. If the
 * data no longer matches by the time register runs, it finds nothing and says
 * so, rather than trusting a handle captured earlier.
 *
 * **Ownership comes from the session.** The guardian is `$request->user()`
 * throughout; `wali_id` is written from that object and nothing a client sends
 * can override it.
 *
 * **Linking never creates or mutates a student.** The matched row is the row
 * that gets a `wali_id`, its status is left untouched (an already-active
 * student stays active), and no second row is ever inserted.
 *
 * Two refusals are possible at register time, both safe:
 *
 *   - the student already belongs to this guardian -> "sudah terdaftar pada
 *     akun Anda", so a double claim is idempotent from the caller's point of
 *     view;
 *   - the student already belongs to someone else -> "sudah terhubung dengan
 *     akun lain". The record is not moved, and the message does not name the
 *     other account.
 *
 * "Not found" is a 404 in both steps, identical for a record that never
 * existed and one that is now owned by another guardian — a 404 never
 * confirms existence.
 */
class WaliSantriLamaController extends Controller
{
    public function __construct(private readonly AuditTrailService $audit) {}

    /**
     * Search for the student the guardian claims to have on file.
     */
    public function match(MatchSantriLamaRequest $request): JsonResponse
    {
        $santri = $this->cari($request);

        if (! $santri instanceof Santri) {
            return response()->json([
                'success' => false,
                'message' => 'Data Santri Tidak Ditemukan.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data santri ditemukan.',
            'data' => new SantriResource($santri),
        ]);
    }

    /**
     * Attach the identified student to the signed-in guardian's account.
     */
    public function register(MatchSantriLamaRequest $request): JsonResponse
    {
        /** @var User $wali */
        $wali = $request->user();

        $ditemukan = $this->cari($request);

        if (! $ditemukan instanceof Santri) {
            return response()->json([
                'success' => false,
                'message' => 'Data Santri Tidak Ditemukan.',
                'data' => null,
            ], 404);
        }

        [$santri, $konflik] = $this->hubungkan($wali, $ditemukan, $request);

        if ($konflik === 'sama') {
            return response()->json([
                'success' => false,
                'message' => 'Data Santri sudah terdaftar pada akun Anda.',
                'data' => null,
            ], 409);
        }

        if ($konflik === 'lain') {
            return response()->json([
                'success' => false,
                'message' => 'Data Santri sudah terhubung dengan akun lain.',
                'data' => null,
            ], 409);
        }

        if (! $santri instanceof Santri) {
            return response()->json([
                'success' => false,
                'message' => 'Data Santri Tidak Ditemukan.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data Santri berhasil dihubungkan ke akun Anda.',
            'data' => new SantriResource($santri),
        ]);
    }

    /**
     * The exact identification query both endpoints share.
     */
    private function cari(MatchSantriLamaRequest $request): ?Santri
    {
        $validated = $request->validated();

        // Leading and trailing spaces are typing noise, not part of a name or
        // a school number. Everything inside stays exact: a near-match on the
        // other two fields is not a match, because the three fields together
        // are what prove the guardianship claim.
        $nomor = trim($validated['nis_atau_nisn']);
        $nama = trim($validated['nama_lengkap']);

        return Santri::query()
            // The guardian types whichever number they know. NIS and NISN are
            // each unique, but the two sequences are unrelated, so the value
            // is matched against both columns.
            ->where(function (Builder $query) use ($nomor): void {
                $query->where('nis', $nomor)->orWhere('nisn', $nomor);
            })
            ->where('nama_lengkap', $nama)
            // `whereDate` rather than a raw comparison: MariaDB stores a
            // `date` as `2013-05-17` while SQLite stores the model's cast value
            // as `2013-05-17 00:00:00`, so an equality comparison matches on
            // one engine and silently never matches on the other. Same reason
            // as the duplicate-registration guard.
            ->whereDate('tanggal_lahir', $validated['tanggal_lahir'])
            ->first();
    }

    /**
     * Claim the student, or explain why it cannot be claimed.
     *
     * The read, the duplicate checks, and the write share one transaction with
     * a row lock, so two guardians clicking at the same moment cannot both read
     * "unowned" and then overwrite each other — on MySQL the second one sees the
     * first's claim and answers "sudah terhubung dengan akun lain" instead.
     *
     * @return array{0: Santri|null, 1: string|null}
     */
    private function hubungkan(User $wali, Santri $ditemukan, MatchSantriLamaRequest $request): array
    {
        return DB::transaction(function () use ($wali, $ditemukan, $request): array {
            // Re-read under the lock because between the search outside this
            // transaction and this point another request may have claimed the
            // row. The lock makes that re-read the decisive one.
            /** @var Santri $santri */
            $santri = Santri::query()
                ->whereKey($ditemukan->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($santri->wali_id === $wali->getKey()) {
                return [null, 'sama'];
            }

            if ($santri->wali_id !== null) {
                // Someone else's student. Not moved, and the refusal never
                // names the other account.
                return [null, 'lain'];
            }

            // Just the association, in place: no new row, no status change.
            // An already-active student stays active.
            $santri->wali_id = $wali->getKey();
            $santri->save();

            $this->audit->log(
                'wali.santri_linked',
                'Wali menghubungkan data santri lama ke akun.',
                $santri,
                ['wali_id' => null],
                ['wali_id' => $wali->getKey()],
                $request,
            );

            return [$santri, null];
        });
    }
}
