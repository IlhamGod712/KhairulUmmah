<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Wali;

use App\Enums\JenisDokumen;
use App\Enums\JenisPembayaran;
use App\Enums\StatusDokumen;
use App\Enums\StatusPembayaran;
use App\Enums\StatusPendaftaran;
use App\Http\Controllers\Controller;
use App\Http\Requests\Wali\UploadBuktiPembayaranRequest;
use App\Http\Resources\PembayaranResource;
use App\Models\Dokumen;
use App\Models\HistoriStatus;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
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
 * The guardian's view of the registration-fee payment, and their upload of its
 * proof.
 *
 * Ownership is the query, exactly as in `WaliPendaftaranController`: every read
 * reaches the payments through `whereHas('santri', wali_id = session user)`, so
 * another guardian's payment id is a 404 and no parameter can widen the scope.
 * The kind is a second condition in the same lookup — this surface answers only
 * for `jenis_pembayaran = pendaftaran`, and an SPP invoice id is likewise a 404
 * rather than a row whose shape it would have to explain. The same predicate
 * gates the upload and the file stream.
 *
 * Two gates decide whether an upload is legal, both evaluated server side:
 *
 *  - The registration must have been **accepted**. A fee for a submission that
 *    is still `diajukan`/`diverifikasi` cannot be paid in, because there is
 *    nothing to pay against yet; a rejected one (terminal) never gets a lane.
 *  - The payment must be in a state the state machine can move to
 *    `menunggu_verifikasi` from, i.e. `belum` or `ditolak`. Proof already under
 *    review (`menunggu_verifikasi`) or already settled (`lunas`) stays put.
 *
 * "Paid in" never means "active": turning the fee `lunas` (the student becomes
 * eligible for activation) is an admin verdict in `AdminPembayaranController`.
 */
class WaliPembayaranController extends Controller
{
    public function __construct(private readonly AuditTrailService $audit) {}

    /**
     * The registration-fee payments belonging to this guardian's own students.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $wali */
        $wali = $request->user();

        $pembayarans = Pembayaran::query()
            ->whereHas('santri', $this->milik($wali))
            ->where('jenis_pembayaran', JenisPembayaran::Pendaftaran)
            ->with(['santri.wali', 'santri.buktiPembayaran', 'pendaftaran'])
            ->latest('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar pembayaran pendaftaran berhasil dimuat.',
            'data' => PembayaranResource::collection($pembayarans),
        ]);
    }

    /**
     * One of this guardian's own registration-fee payments.
     */
    public function show(Request $request, int $pembayaran): JsonResponse
    {
        /** @var User $wali */
        $wali = $request->user();

        $model = $this->cariMilik($wali, $pembayaran, ['santri.wali', 'santri.buktiPembayaran', 'pendaftaran']);

        return response()->json([
            'success' => true,
            'message' => 'Detail pembayaran pendaftaran berhasil dimuat.',
            'data' => new PembayaranResource($model),
        ]);
    }

    /**
     * Upload (or replace) the proof of payment for one of this guardian's fees.
     */
    public function uploadBukti(UploadBuktiPembayaranRequest $request, int $pembayaran): JsonResponse
    {
        /** @var User $wali */
        $wali = $request->user();

        $model = $this->cariMilik($wali, $pembayaran, ['pendaftaran', 'santri']);

        DB::transaction(function () use ($request, $model, $wali): void {
            $fee = Pembayaran::query()
                ->whereKey($model->getKey())
                ->lockForUpdate()
                ->with(['pendaftaran', 'santri'])
                ->firstOrFail();

            $this->pastikanDapatDiunggah($fee);
            $statusSebelumnya = $fee->status->value;

            $file = $request->file('bukti');

            if ($file === null) {
                throw ValidationException::withMessages(['bukti' => 'Berkas bukti pembayaran wajib dipilih.']);
            }

            $path = $file->store('bukti-pembayaran', 'local');

            if ($path === false) {
                throw new \RuntimeException('Gagal menyimpan berkas bukti pembayaran.');
            }

            $lama = Dokumen::query()
                ->where('santri_id', $fee->santri_id)
                ->where('jenis', JenisDokumen::BuktiPembayaran)
                ->first();

            // The old physical file is orphaned once its row is replaced; it is
            // deleted here so a large rejected proof does not accumulate on disk.
            if ($lama instanceof Dokumen) {
                Storage::disk('local')->delete($lama->path);
                $lama->forceFill([
                    'path' => $path,
                    'nama_asli' => $file->getClientOriginalName(),
                    'mime_type' => $request->mimeTerpercaya(),
                    'ukuran_bytes' => $file->getSize(),
                    'status' => StatusDokumen::Menunggu,
                    'diverifikasi_oleh' => null,
                    'diverifikasi_pada' => null,
                    'catatan' => null,
                ])->save();
            } else {
                Dokumen::query()->create([
                    'santri_id' => $fee->santri_id,
                    'jenis' => JenisDokumen::BuktiPembayaran,
                    'path' => $path,
                    'nama_asli' => $file->getClientOriginalName(),
                    'mime_type' => $request->mimeTerpercaya(),
                    'ukuran_bytes' => $file->getSize(),
                    'status' => StatusDokumen::Menunggu,
                ]);
            }

            $fee->forceFill([
                'status' => StatusPembayaran::MenungguVerifikasi,
                'tanggal_bayar' => now()->toDateString(),
                'referensi' => $request->input('referensi') ?? $fee->referensi,
            ])->save();

            HistoriStatus::query()->create([
                'santri_id' => $fee->santri_id,
                'dari_status' => $fee->santri->status,
                'ke_status' => $fee->santri->status,
                'keterangan' => 'Bukti pembayaran pendaftaran diunggah, menunggu verifikasi admin / tata usaha.',
                'oleh' => (int) $wali->getKey(),
                'pada' => now(),
            ]);

            $this->audit->log(
                'pembayaran.pendaftaran.bukti_uploaded',
                'Wali mengunggah bukti pembayaran pendaftaran.',
                $fee,
                ['status' => $statusSebelumnya],
                ['status' => StatusPembayaran::MenungguVerifikasi->value],
                $request,
            );
        });

        $model = $this->cariMilik($wali, $pembayaran, ['santri.wali', 'santri.buktiPembayaran', 'pendaftaran']);

        return response()->json([
            'success' => true,
            'message' => 'Bukti pembayaran berhasil diunggah dan menunggu verifikasi admin / tata usaha.',
            'data' => new PembayaranResource($model),
        ]);
    }

    /**
     * Stream the uploaded proof for the signed-in guardian's own payment.
     *
     * The file lives on the private `local` disk; this route is the only way to
     * fetch it, and it is scoped to the guardian's own fee the same way the
     * reads are. The browser response is served inline so the SPA can preview
     * the proof, and the stored filename (server generated) is what names it.
     */
    public function unduhBukti(Request $request, int $pembayaran): BinaryFileResponse
    {
        /** @var User $wali */
        $wali = $request->user();

        $model = $this->cariMilik($wali, $pembayaran, ['santri.buktiPembayaran']);

        $bukti = $model->santri->buktiPembayaran;

        if (! $bukti instanceof Dokumen) {
            throw (new ModelNotFoundException)->setModel(Dokumen::class);
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($bukti->path)) {
            throw (new ModelNotFoundException)->setModel(Dokumen::class, [$bukti->getKey()]);
        }

        return response()->file(
            $disk->path($bukti->path),
            ['Content-Type' => $bukti->mime ?? ($bukti->mime_type ?? 'application/octet-stream')],
        );
    }

    /**
     * Fetch one of this guardian's own registration-fee rows, or 404.
     *
     * Both the owner and the kind are part of the lookup. An SPP invoice id is
     * a 404 on this surface for the same reason another guardian's id is: this
     * controller has no answer for it, and the SPP routes are where it belongs.
     *
     * @param  array<int, string>  $relations
     */
    private function cariMilik(User $wali, int $pembayaran, array $relations): Pembayaran
    {
        $model = Pembayaran::query()
            ->whereKey($pembayaran)
            ->whereHas('santri', $this->milik($wali))
            ->where('jenis_pembayaran', JenisPembayaran::Pendaftaran)
            ->with($relations)
            ->first();

        // Ownership was part of the lookup, so "absent" and "someone else's"
        // are the same answer here on purpose.
        if (! $model instanceof Pembayaran) {
            throw (new ModelNotFoundException)->setModel(Pembayaran::class, [$pembayaran]);
        }

        return $model;
    }

    /**
     * Whether proof may be uploaded for this fee right now.
     */
    private function pastikanDapatDiunggah(Pembayaran $fee): void
    {
        // The state machine owns "may this payment move". Re-sending proof for
        // an already settled or already submitted fee is refused here.
        if (! $fee->status->canTransitionTo(StatusPembayaran::MenungguVerifikasi)) {
            throw ValidationException::withMessages([
                'bukti' => 'Bukti pembayaran hanya dapat diunggah saat tagihan belum dibayar atau ditolak.',
            ]);
        }

        // The payment lane opens with an accepted registration and nothing else:
        // a fee for a submission still under review has no date to be "paid by".
        $pendaftaran = $fee->pendaftaran;

        if (! $pendaftaran instanceof Pendaftaran || $pendaftaran->status !== StatusPendaftaran::Diterima) {
            throw ValidationException::withMessages([
                'bukti' => 'Bukti pembayaran hanya dapat diunggah setelah pendaftaran diterima.',
            ]);
        }
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
