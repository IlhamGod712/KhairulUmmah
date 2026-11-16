<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\JenisPembayaran;
use App\Enums\StatusDokumen;
use App\Enums\StatusPembayaran;
use App\Enums\StatusPendaftaran;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePembayaranStatusRequest;
use App\Http\Resources\PembayaranResource;
use App\Models\Dokumen;
use App\Models\HistoriStatus;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\User;
use App\Services\AktivasiSantri;
use App\Services\AuditTrailService;
use App\Services\NotifikasiService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The admin / Tata Usaha surface for the registration-fee payment.
 *
 * Unlike the wali controller there is no ownership predicate — reviewing every
 * payment is the job — so the `role:admin` middleware is the only thing between
 * a guardian and these rows, and a guardian reaching one gets a 403, not a 404,
 * because the routes exist for admins.
 *
 * The verdict endpoint is where the pipeline's one load-bearing invariant is
 * enforced: **a student becomes active only when the registration is accepted
 * AND the registration-fee payment is settled.** Both are re-read under a row
 * lock inside one transaction, so the two conditions cannot drift apart. There
 * is no public contract that says "set santri to aktif": the Santri transition
 * is a side effect of the payment being verified, produced here, and only here.
 */
class AdminPembayaranController extends Controller
{
    public function __construct(
        private AktivasiSantri $aktivasiSantri,
        private NotifikasiService $notifikasi,
        private AuditTrailService $audit,
    ) {}

    /**
     * Every registration-fee payment, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $pembayarans = Pembayaran::query()
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
     * One payment in full, for review.
     */
    public function show(Request $request, int $pembayaran): JsonResponse
    {
        $model = $this->cari($pembayaran, ['santri.wali', 'santri.buktiPembayaran', 'pendaftaran']);

        return response()->json([
            'success' => true,
            'message' => 'Detail pembayaran pendaftaran berhasil dimuat.',
            'data' => new PembayaranResource($model),
        ]);
    }

    /**
     * Confirm the proof (lunas) or refuse it (ditolak).
     */
    public function putStatus(UpdatePembayaranStatusRequest $request, int $pembayaran): JsonResponse
    {
        $admin = $request->user();

        $keputusan = StatusPembayaran::tryFrom($request->validated('status'));

        $this->cari($pembayaran, ['pendaftaran', 'santri']);

        DB::transaction(function () use ($request, $pembayaran, $keputusan, $admin): void {
            $fee = Pembayaran::query()
                ->whereKey($pembayaran)
                ->where('jenis_pembayaran', JenisPembayaran::Pendaftaran)
                ->lockForUpdate()
                ->with(['pendaftaran', 'santri'])
                ->firstOrFail();

            if (! $fee->status->canTransitionTo($keputusan)) {
                throw ValidationException::withMessages([
                    'status' => 'Keputusan ini tidak valid untuk status pembayaran saat ini.',
                ]);
            }

            $statusSebelumnya = $fee->status->value;
            $pendaftaran = $fee->pendaftaran;
            $santri = $fee->santri;

            if (! $pendaftaran instanceof Pendaftaran || $pendaftaran->status !== StatusPendaftaran::Diterima) {
                throw ValidationException::withMessages([
                    'status' => 'Pembayaran hanya dapat diverifikasi setelah pendaftaran diterima.',
                ]);
            }

            $bukti = $santri->buktiPembayaran;

            if ($keputusan === StatusPembayaran::Lunas) {
                if (! $bukti instanceof Dokumen || $bukti->status !== StatusDokumen::Menunggu) {
                    throw ValidationException::withMessages([
                        'status' => 'Tidak ada bukti pembayaran menunggu verifikasi untuk dikonfirmasi.',
                    ]);
                }

                $fee->forceFill([
                    'status' => StatusPembayaran::Lunas,
                    'tanggal_bayar' => now()->toDateString(),
                    'keterangan' => $request->input('catatan') ?? $fee->keterangan,
                    'dicatat_oleh' => $admin->getKey(),
                ])->save();

                $bukti->forceFill([
                    'status' => StatusDokumen::Diterima,
                    'diverifikasi_oleh' => $admin->getKey(),
                    'diverifikasi_pada' => now(),
                    'catatan' => $request->input('catatan'),
                ])->save();
            } else {
                $catatan = $request->input('catatan');

                $fee->forceFill([
                    'status' => StatusPembayaran::Ditolak,
                    'keterangan' => $catatan ?? $fee->keterangan,
                    'dicatat_oleh' => $admin->getKey(),
                ])->save();

                if ($bukti instanceof Dokumen) {
                    $bukti->forceFill([
                        'status' => StatusDokumen::Ditolak,
                        'diverifikasi_oleh' => $admin->getKey(),
                        'diverifikasi_pada' => now(),
                        'catatan' => $catatan,
                    ])->save();
                }

                HistoriStatus::query()->create([
                    'santri_id' => $santri->getKey(),
                    'dari_status' => $santri->status,
                    'ke_status' => $santri->status,
                    'keterangan' => $catatan !== null
                        ? 'Pembayaran pendaftaran ditolak admin: '.$catatan
                        : 'Pembayaran pendaftaran ditolak admin.',
                    'oleh' => (int) $admin->getKey(),
                    'pada' => now(),
                ]);
            }

            $this->audit->log(
                $keputusan === StatusPembayaran::Lunas
                    ? 'pembayaran.pendaftaran.verified'
                    : 'pembayaran.pendaftaran.rejected',
                $keputusan === StatusPembayaran::Lunas
                    ? 'Admin memverifikasi pembayaran pendaftaran.'
                    : 'Admin menolak bukti pembayaran pendaftaran.',
                $fee,
                ['status' => $statusSebelumnya],
                ['status' => $keputusan->value],
                $request,
            );
        });

        $model = $this->cari($pembayaran, ['santri.wali', 'santri.buktiPembayaran', 'pendaftaran']);
        $aktivasi = null;
        if ($model->santri && $model->santri->wali_id) {
            if ($keputusan === StatusPembayaran::Lunas) {
                $aktivasi = $this->aktivasiSantri->cobaAktifkan($model->santri);
                $wali = User::find($model->santri->wali_id);
                if ($wali) {
                    $this->notifikasi->kirim($wali, 'pembayaran_lunas', 'Pembayaran Diterima', 'Pembayaran pendaftaran telah diverifikasi dan dinyatakan lunas.', '/wali/pembayaran/'.$model->id, ['pembayaran_id' => $model->id, 'santri_id' => $model->santri_id]);
                }
                if (is_array($aktivasi) && ($aktivasi['diaktifkan'] ?? false)) {
                    $wali2 = User::find($model->santri->wali_id);
                    if ($wali2) {
                        $this->notifikasi->kirim($wali2, 'santri_aktif', 'Santri Diaktifkan', 'Santri telah diaktifkan.', '/wali/santri', ['santri_id' => $model->santri_id]);
                    }
                }
            } elseif ($keputusan === StatusPembayaran::Ditolak) {
                $wali = User::find($model->santri->wali_id);
                if ($wali) {
                    $this->notifikasi->kirim($wali, 'pembayaran_ditolak', 'Pembayaran Ditolak', 'Pembayaran pendaftaran ditolak. Silakan unggah ulang bukti pembayaran.', '/wali/pembayaran/'.$model->id, ['pembayaran_id' => $model->id, 'santri_id' => $model->santri_id]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => $keputusan?->isPaid()
                ? 'Pembayaran pendaftaran dikonfirmasi LUNAS.'
                : 'Bukti pembayaran ditolak. Wali dapat mengunggah ulang bukti.',
            'data' => [
                'pembayaran' => new PembayaranResource($model),
                'aktivasi' => $aktivasi,
            ],
        ]);
    }

    /**
     * Stream the proof for a registration-fee payment, for the admin's review.
     */
    public function unduhBukti(Request $request, int $pembayaran): BinaryFileResponse
    {
        $model = $this->cari($pembayaran, ['santri.buktiPembayaran']);

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
     * One registration-fee payment by key, or 404.
     *
     * @param  array<int, string>  $relations
     */
    private function cari(int $pembayaran, array $relations): Pembayaran
    {
        $model = Pembayaran::query()
            ->whereKey($pembayaran)
            ->where('jenis_pembayaran', JenisPembayaran::Pendaftaran)
            ->with($relations)
            ->first();

        if (! $model instanceof Pembayaran) {
            throw (new ModelNotFoundException)->setModel(Pembayaran::class, [$pembayaran]);
        }

        return $model;
    }
}
