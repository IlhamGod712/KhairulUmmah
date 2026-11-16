<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\JenisPembayaran;
use App\Enums\StatusPembayaran;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterSppRequest;
use App\Http\Requests\Admin\GenerateSppRequest;
use App\Http\Requests\Admin\UpdateSppStatusRequest;
use App\Http\Resources\TagihanSppResource;
use App\Models\HistoriStatus;
use App\Models\Pembayaran;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\AuditTrailService;
use App\Services\NotifikasiService;
use App\Services\SppGenerator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The admin / Tata Usaha surface for monthly SPP: bill it, then review it.
 *
 * As with the registration fee there is no ownership predicate — reviewing every
 * invoice is the job — so `role:admin` is the boundary and a guardian reaching
 * these routes gets a 403, not a 404, because the routes do exist for admins.
 *
 * Two things here are load-bearing.
 *
 * **Only active students are billed, and the amount is never client-supplied.**
 * Both are enforced in `App\Services\SppGenerator`, not here, because they are
 * facts about the school rather than about HTTP: a student who is still
 * `menunggu_pembayaran` or `verifikasi` — or who was rejected, or cancelled —
 * is never handed an SPP bill, and the price comes from the `biaya_spp_bulanan`
 * setting at generation time. Generation is idempotent by unique index, so
 * running it twice for the same month is a no-op rather than a doubled bill.
 * The controller only chooses the academic year and hands the month over.
 *
 * The verdict endpoint marks the invoice `lunas` or `ditolak` and stops there.
 * It deliberately does **not** touch the student: unlike the registration-fee
 * verdict, verifying an SPP invoice must not activate anybody, because the
 * student was already active when the bill was raised.
 */
class AdminSppController extends Controller
{
    public function __construct(
        private NotifikasiService $notifikasi,
        private AuditTrailService $audit,
    ) {}

    /**
     * The academic years offered as a filter, so the admin UI does not have to
     * hardcode them.
     */
    public function tahunAjaran(Request $request): JsonResponse
    {
        $tahun = TahunAjaran::query()
            ->orderByDesc('tanggal_mulai')
            ->get(['id', 'nama', 'is_active']);

        return response()->json([
            'success' => true,
            'message' => 'Daftar tahun ajaran berhasil dimuat.',
            'data' => $tahun->map(fn (TahunAjaran $item): array => [
                'id' => $item->id,
                'nama' => $item->nama,
                'is_active' => $item->is_active,
            ])->all(),
        ]);
    }

    /**
     * Every SPP invoice, newest month first, optionally filtered.
     *
     * The three filters are the ones the review queue is actually used for:
     * which academic year, which month, and which state the bill is in. They
     * narrow the query, so a filtered table is a genuinely smaller set of rows
     * rather than a full dump the browser was asked to sort.
     */
    public function index(FilterSppRequest $request): JsonResponse
    {
        $query = Pembayaran::query()
            ->tagihanSpp()
            ->with(['santri.wali', 'tahunAjaran'])
            ->orderByDesc('periode')
            ->orderBy('santri_id');

        if ($request->filled('tahun_ajaran_id')) {
            $query->where('tahun_ajaran_id', (int) $request->integer('tahun_ajaran_id'));
        }

        if ($request->filled('periode')) {
            $periode = self::bulanDari($request->validated('periode'));

            $query->whereYear('periode', $periode->year)->whereMonth('periode', $periode->month);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }

        return response()->json([
            'success' => true,
            'message' => 'Daftar tagihan SPP berhasil dimuat.',
            'data' => TagihanSppResource::collection($query->get()),
        ]);
    }

    /**
     * One SPP invoice in full, for review.
     */
    public function show(Request $request, int $pembayaran): JsonResponse
    {
        $model = $this->cari($pembayaran);

        return response()->json([
            'success' => true,
            'message' => 'Detail tagihan SPP berhasil dimuat.',
            'data' => new TagihanSppResource($model),
        ]);
    }

    /**
     * Raise one month of SPP for every active student.
     *
     * Idempotent: the second run of the same month creates nothing and reports
     * the same totals, because the unique index refuses the second insert rather
     * than because this method remembered what it did last time.
     */
    public function generate(GenerateSppRequest $request, SppGenerator $generator): JsonResponse
    {
        $periode = self::bulanDari($request->validated('periode'));

        $tahun = $request->filled('tahun_ajaran_id')
            ? TahunAjaran::query()->findOrFail($request->integer('tahun_ajaran_id'))
            : TahunAjaran::query()->active()->firstOrFail();

        $hasil = DB::transaction(function () use ($generator, $tahun, $periode, $request) {
            $res = $generator->jalankan($tahun, $periode);
            if ($res['dibuat'] > 0) {
                $this->audit->log(
                    'spp.generated',
                    'Admin membuat tagihan SPP.',
                    newValues: [
                        'periode' => $res['periode'],
                        'tahun_ajaran_id' => $tahun->getKey(),
                        'jumlah_tagihan' => $res['dibuat'],
                    ],
                    request: $request,
                );

                $pembayarans = Pembayaran::query()
                    ->where('tahun_ajaran_id', $tahun->getKey())
                    ->where('jenis_pembayaran', JenisPembayaran::Spp->value)
                    ->where('periode', $periode->toDateString())
                    ->with('santri')
                    ->get();
                foreach ($pembayarans as $pb) {
                    if ($pb->santri && $pb->santri->wali_id && $pb->created_at && $pb->created_at->diffInSeconds(now()) < 60) {
                        $wali = User::find($pb->santri->wali_id);
                        if ($wali) {
                            $this->notifikasi->kirim($wali, 'spp_dibuat', 'Tagihan SPP', 'Tagihan SPP periode '.$res['periode_label'].' telah dibuat.', '/wali/spp/'.$pb->id, ['pembayaran_id' => $pb->id, 'periode' => $res['periode']]);
                        }
                    }
                }
            }

            return $res;
        });

        return response()->json([
            'success' => true,
            'message' => $hasil['dibuat'] > 0
                ? "Tagihan SPP {$hasil['periode_label']} dibuat untuk {$hasil['dibuat']} santri aktif."
                : "Tidak ada tagihan SPP baru untuk {$hasil['periode_label']}; {$hasil['dilewati']} sudah ada sebelumnya.",
            'data' => $hasil,
        ]);
    }

    /**
     * Confirm the proof (`lunas`) or refuse it (`ditolak`), reopening the lane
     * for a corrected upload.
     */
    public function putStatus(UpdateSppStatusRequest $request, int $pembayaran): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $keputusan = StatusPembayaran::tryFrom($request->validated('status'));

        $this->cari($pembayaran);

        DB::transaction(function () use ($request, $pembayaran, $keputusan, $admin): void {
            $tagihan = Pembayaran::query()
                ->tagihanSpp()
                ->whereKey($pembayaran)
                ->lockForUpdate()
                ->with('santri')
                ->firstOrFail();

            // `belum -> lunas` and `ditolak -> lunas` are refused here, by the
            // same map the guardian's upload and the admin's verdict share.
            if (! $tagihan->status->canTransitionTo($keputusan)) {
                throw ValidationException::withMessages([
                    'status' => 'Keputusan ini tidak valid untuk status pembayaran saat ini.',
                ]);
            }

            $statusSebelumnya = $tagihan->status->value;

            // A verdict with no file to judge would settle a bill on air.
            if ($keputusan === StatusPembayaran::Lunas && ! is_string($tagihan->bukti_path)) {
                throw ValidationException::withMessages([
                    'status' => 'Tidak ada bukti pembayaran menunggu verifikasi untuk dikonfirmasi.',
                ]);
            }

            $catatan = $request->input('catatan');

            $tagihan->forceFill([
                'status' => $keputusan,
                'diverifikasi_oleh' => $admin->getKey(),
                'diverifikasi_pada' => now(),
                'keterangan' => $catatan ?? $tagihan->keterangan,
                // `lunas` is the moment the money is recorded as received; a
                // rejection is not a payment, so the date a bill was refused
                // must not be mistaken for the date it was paid.
                'tanggal_bayar' => $keputusan === StatusPembayaran::Lunas
                    ? ($tagihan->tanggal_bayar ?? now()->toDateString())
                    : $tagihan->tanggal_bayar,
            ])->save();

            HistoriStatus::query()->create([
                'santri_id' => $tagihan->santri_id,
                'dari_status' => $tagihan->santri->status,
                'ke_status' => $tagihan->santri->status,
                'keterangan' => $keputusan === StatusPembayaran::Lunas
                    ? 'Pembayaran SPP '.$tagihan->periodeLabel().' dikonfirmasi lunas oleh admin / tata usaha.'
                    : 'Pembayaran SPP '.$tagihan->periodeLabel().' ditolak admin: '.($catatan ?? 'tanpa catatan'),
                'oleh' => (int) $admin->getKey(),
                'pada' => now(),
            ]);

            $this->audit->log(
                $keputusan === StatusPembayaran::Lunas ? 'spp.payment.verified' : 'spp.payment.rejected',
                $keputusan === StatusPembayaran::Lunas
                    ? 'Admin memverifikasi pembayaran SPP.'
                    : 'Admin menolak bukti pembayaran SPP.',
                $tagihan,
                ['status' => $statusSebelumnya],
                ['status' => $keputusan->value],
                $request,
            );
        });

        $model = $this->cari($pembayaran);

        return response()->json([
            'success' => true,
            'message' => $keputusan === StatusPembayaran::Lunas
                ? 'Pembayaran SPP dikonfirmasi LUNAS.'
                : 'Bukti pembayaran SPP ditolak. Wali dapat mengunggah ulang bukti.',
            'data' => new TagihanSppResource($model),
        ]);
    }

    /**
     * Stream any invoice's proof, for the admin's review.
     */
    public function unduhBukti(Request $request, int $pembayaran): BinaryFileResponse
    {
        $model = $this->cari($pembayaran);

        $path = $model->bukti_path;

        if (! is_string($path) || $path === '') {
            throw (new ModelNotFoundException)->setModel(Pembayaran::class, [$model->getKey()]);
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            throw (new ModelNotFoundException)->setModel(Pembayaran::class, [$model->getKey()]);
        }

        return response()->file(
            $disk->path($path),
            ['Content-Type' => $model->bukti_mime_type ?? 'application/octet-stream'],
        );
    }

    /**
     * One SPP invoice by key, or 404.
     */
    private function cari(int $pembayaran): Pembayaran
    {
        $model = Pembayaran::query()
            ->tagihanSpp()
            ->whereKey($pembayaran)
            ->with(['santri.wali', 'tahunAjaran'])
            ->first();

        if (! $model instanceof Pembayaran) {
            throw (new ModelNotFoundException)->setModel(Pembayaran::class, [$pembayaran]);
        }

        return $model;
    }

    /**
     * Read a `YYYY-MM` (or `YYYY-MM-DD`) request value as the first of that month.
     */
    private static function bulanDari(string $nilai): Carbon
    {
        $tahun = (int) substr($nilai, 0, 4);
        $bulan = (int) substr($nilai, 5, 2);

        return Carbon::create($tahun, $bulan, 1)->startOfDay();
    }
}
