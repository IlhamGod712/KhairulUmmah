<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Wali;

use App\Enums\StatusPembayaran;
use App\Http\Controllers\Controller;
use App\Http\Requests\Wali\UploadBuktiPembayaranRequest;
use App\Http\Resources\TagihanSppResource;
use App\Models\HistoriStatus;
use App\Models\Pembayaran;
use App\Models\Santri;
use App\Models\User;
use App\Services\AuditTrailService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The guardian's view of their students' monthly SPP invoices, and their upload
 * of each invoice's proof.
 *
 * Ownership is the query, exactly as in `WaliPembayaranController`: every read
 * reaches the invoice through `whereHas('santri', wali_id = session user)`, so
 * another guardian's invoice id is a 404 rather than a 403 and no parameter can
 * widen the scope. There is no `wali_id` in any payload here.
 *
 * The state machine is the only thing that decides whether a proof may be sent.
 * A guardian can move an invoice from `belum` or `ditolak` to
 * `menunggu_verifikasi` and to nothing else — there is no route here that writes
 * a status, so `lunas` is unreachable from this side of the application. Turning
 * a bill `lunas` is an admin verdict in `AdminSppController`, and only from a
 * proof that is actually waiting.
 */
class WaliSppController extends Controller
{
    public function __construct(private readonly AuditTrailService $audit) {}

    /**
     * The SPP invoices belonging to this guardian's own active students.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $wali */
        $wali = $request->user();

        $tagihan = Pembayaran::query()
            ->tagihanSpp()
            ->whereHas('santri', $this->milik($wali))
            ->with(['santri', 'tahunAjaran'])
            ->orderByDesc('periode')
            ->orderBy('santri_id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar tagihan SPP berhasil dimuat.',
            'data' => TagihanSppResource::collection($tagihan),
        ]);
    }

    /**
     * One of this guardian's own SPP invoices.
     */
    public function show(Request $request, int $pembayaran): JsonResponse
    {
        /** @var User $wali */
        $wali = $request->user();

        $model = $this->cariMilik($wali, $pembayaran);

        return response()->json([
            'success' => true,
            'message' => 'Detail tagihan SPP berhasil dimuat.',
            'data' => new TagihanSppResource($model),
        ]);
    }

    /**
     * Upload (or replace) the proof that this month's SPP was paid.
     *
     * The file handling is the Phase 6 pipeline, not a second one: the same
     * `UploadBuktiPembayaranRequest` (finfo on the real bytes, 2 MB cap, PDF /
     * JPG / PNG only), the same private `local` disk, the same server-generated
     * filename. What differs is only the destination — the invoice's own columns
     * rather than the student's single `bukti_pembayaran` document, because a
     * student has a different proof for every month.
     */
    public function uploadBukti(UploadBuktiPembayaranRequest $request, int $pembayaran): JsonResponse
    {
        /** @var User $wali */
        $wali = $request->user();

        $model = $this->cariMilik($wali, $pembayaran);

        DB::transaction(function () use ($request, $model, $wali): void {
            // Re-read under a row lock: two uploads racing on the same invoice
            // must not both decide they are the first to move it off `belum`.
            $tagihan = Pembayaran::query()
                ->whereKey($model->getKey())
                ->lockForUpdate()
                ->with('santri')
                ->firstOrFail();

            if (! $tagihan->status->canTransitionTo(StatusPembayaran::MenungguVerifikasi)) {
                throw ValidationException::withMessages([
                    'bukti' => 'Bukti pembayaran hanya dapat diunggah saat tagihan belum dibayar atau ditolak.',
                ]);
            }

            $statusSebelumnya = $tagihan->status->value;

            $file = $request->file('bukti');

            if ($file === null) {
                throw ValidationException::withMessages(['bukti' => 'Berkas bukti pembayaran wajib dipilih.']);
            }

            $path = $file->store('bukti-pembayaran', 'local');

            if ($path === false) {
                throw new \RuntimeException('Gagal menyimpan berkas bukti pembayaran.');
            }

            // The rejected proof's file is orphaned the moment this row's columns
            // are overwritten, so it is deleted here rather than left to pile up
            // on disk one file per rejection.
            $sebelumnya = $tagihan->bukti_path;

            if (is_string($sebelumnya) && $sebelumnya !== '' && $sebelumnya !== $path) {
                Storage::disk('local')->delete($sebelumnya);
            }

            $tagihan->forceFill([
                'bukti_path' => $path,
                'bukti_nama_asli' => $file->getClientOriginalName(),
                'bukti_mime_type' => $request->mimeTerpercaya(),
                'bukti_ukuran_bytes' => $file->getSize(),
                'status' => StatusPembayaran::MenungguVerifikasi,
                'tanggal_bayar' => now()->toDateString(),
                'referensi' => $request->input('referensi') ?? $tagihan->referensi,
            ])->save();

            HistoriStatus::query()->create([
                'santri_id' => $tagihan->santri_id,
                'dari_status' => $tagihan->santri->status,
                'ke_status' => $tagihan->santri->status,
                'keterangan' => 'Bukti pembayaran SPP '.$tagihan->periodeLabel().' diunggah, menunggu verifikasi admin / tata usaha.',
                'oleh' => (int) $wali->getKey(),
                'pada' => now(),
            ]);

            $this->audit->log(
                'spp.payment.proof_uploaded',
                'Wali mengunggah bukti pembayaran SPP.',
                $tagihan,
                ['status' => $statusSebelumnya],
                ['status' => StatusPembayaran::MenungguVerifikasi->value],
                $request,
            );
        });

        $model = $this->cariMilik($wali, $pembayaran);

        return response()->json([
            'success' => true,
            'message' => 'Bukti pembayaran SPP berhasil diunggah dan menunggu verifikasi admin / tata usaha.',
            'data' => new TagihanSppResource($model),
        ]);
    }

    /**
     * Stream the uploaded proof for this guardian's own invoice.
     */
    public function unduhBukti(Request $request, int $pembayaran): BinaryFileResponse
    {
        /** @var User $wali */
        $wali = $request->user();

        $model = $this->cariMilik($wali, $pembayaran);

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
     * Fetch one of this guardian's own SPP invoices, or 404.
     */
    private function cariMilik(User $wali, int $pembayaran): Pembayaran
    {
        $model = Pembayaran::query()
            ->tagihanSpp()
            ->whereKey($pembayaran)
            ->whereHas('santri', $this->milik($wali))
            ->with(['santri', 'tahunAjaran'])
            ->first();

        // Ownership was part of the lookup, so "absent" and "someone else's" are
        // the same answer here on purpose.
        if (! $model instanceof Pembayaran) {
            throw (new ModelNotFoundException)->setModel(Pembayaran::class, [$pembayaran]);
        }

        return $model;
    }

    /**
     * The ownership predicate, shared by every read and gate.
     *
     * @return Closure(Builder<Santri>): Builder<Santri>
     */
    private function milik(User $wali): Closure
    {
        return fn (Builder $query): Builder => $query->where('wali_id', $wali->getKey());
    }
}
