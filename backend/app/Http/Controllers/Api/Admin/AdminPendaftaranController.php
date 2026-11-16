<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\JenisPembayaran;
use App\Enums\SantriStatus;
use App\Enums\StatusPembayaran;
use App\Enums\StatusPendaftaran;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePendaftaranStatusRequest;
use App\Http\Resources\PendaftaranDetailResource;
use App\Http\Resources\PendaftaranResource;
use App\Models\HistoriStatus;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\Pengaturan;
use App\Models\Santri;
use App\Models\User;
use App\Services\AuditTrailService;
use App\Services\NotifikasiService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The admin / Tata Usaha view of registration submissions, and the verdict.
 *
 * `index` and `show` are reads; `putStatus` is the only write, and it exists so
 * a submission can move to `diterima` or `ditolak` under the rules in
 * `StatusPendaftaran::canTransitionTo()`. There is no bare `PUT` or `DELETE` on
 * the resource: acceptance is what raises the registration-fee invoice and
 * writes the history row, so a generic update endpoint would let a client move
 * a status while skipping both. Reviewing the individual documents is not part
 * of this phase and has no route yet.
 *
 * Unlike the wali surface, this one is not scoped to anyone: seeing every
 * registration is the entire point of the job. The `role:admin` middleware is
 * what keeps it away from guardians, and it is a 403 there rather than a 404
 * because the route is meant for admins and the SPA explains the refusal.
 */
class AdminPendaftaranController extends Controller
{
    public function __construct(
        private NotifikasiService $notifikasi,
        private AuditTrailService $audit,
    ) {}

    /**
     * Every registration, newest first.
     *
     * The guardian is eager loaded because the admin has to be able to answer
     * "who submitted this" without opening each row, and the student because
     * a list that showed only registration numbers would not be reviewable.
     */
    public function index(Request $request): JsonResponse
    {
        $pendaftarans = Pendaftaran::query()
            ->with(['santri.wali', 'santri.pembayarans', 'tahunAjaran'])
            ->latest('tanggal_daftar')
            ->latest('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar seluruh pendaftaran berhasil dimuat.',
            'data' => PendaftaranResource::collection($pendaftarans),
        ]);
    }

    /**
     * One registration in full, for review.
     */
    public function show(Request $request, int $pendaftaran): JsonResponse
    {
        $model = Pendaftaran::query()
            ->whereKey($pendaftaran)
            ->with(['santri.wali', 'santri.pembayarans', 'santri.buktiPembayaran', 'tahunAjaran', 'diprosesOleh'])
            ->first();

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
     * Accept or reject a registration in one transaction.
     *
     * The two answers are whitelisted in the request and owned by the enum state
     * machine; the subject is whoever the route bound to, never something a
     * payload carries. Accepted, the student moves to `menunggu_pembayaran` and
     * a registration-fee row is created from `biaya_pendaftaran` if it does not
     * exist yet. Rejected, the reason is stored and shown back to the guardian.
     *
     * **Accepting is not activating.** The fee row this creates is the first
     * step of the payment lane; turning it `lunas` — and only then moving the
     * student to `aktif` — is `AdminPembayaranController::putStatus`.
     */
    public function putStatus(UpdatePendaftaranStatusRequest $request, int $pendaftaran): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $keputusan = StatusPendaftaran::tryFrom($request->validated('status'));
        $alasan = $keputusan === StatusPendaftaran::Ditolak
            ? $request->validated('alasan_ditolak')
            : null;

        Pendaftaran::query()->whereKey($pendaftaran)->firstOrFail();

        DB::transaction(function () use ($request, $pendaftaran, $keputusan, $admin, $alasan): void {
            $model = Pendaftaran::query()
                ->whereKey($pendaftaran)
                ->lockForUpdate()
                ->with(['santri'])
                ->firstOrFail();

            if (! $model->status->canTransitionTo($keputusan)) {
                throw ValidationException::withMessages([
                    'status' => 'Pendaftaran ini sudah diputuskan dan tidak dapat diubah lagi.',
                ]);
            }

            $santri = $model->santri;
            $sekarang = $santri->status;
            $statusPendaftaranSebelumnya = $model->status->value;
            $statusSantriSebelumnya = $sekarang->value;

            if ($keputusan === StatusPendaftaran::Diterima) {
                $target = SantriStatus::MenungguPembayaran;
                $this->bukaPembayaran($model, $santri, $admin);
            } else {
                $target = SantriStatus::Ditolak;
            }

            $model->forceFill([
                'status' => $keputusan,
                'diproses_oleh' => $admin->getKey(),
                'alasan_ditolak' => $alasan,
            ])->save();

            if ($sekarang !== $target) {
                $santri->update(['status' => $target]);
            }

            HistoriStatus::query()->create([
                'santri_id' => $santri->getKey(),
                'dari_status' => $sekarang,
                'ke_status' => $target,
                'keterangan' => $keputusan === StatusPendaftaran::Diterima
                    ? 'Pendaftaran diterima; santri menunggu pembayaran pendaftaran.'
                    : 'Pendaftaran ditolak'.($alasan !== null ? ': '.$alasan : ''),
                'oleh' => (int) $admin->getKey(),
                'pada' => now(),
            ]);

            $this->audit->log(
                $keputusan === StatusPendaftaran::Diterima ? 'pendaftaran.accepted' : 'pendaftaran.rejected',
                $keputusan === StatusPendaftaran::Diterima
                    ? 'Admin menerima pendaftaran santri.'
                    : 'Admin menolak pendaftaran santri.',
                $model,
                [
                    'status_pendaftaran' => $statusPendaftaranSebelumnya,
                    'status_santri' => $statusSantriSebelumnya,
                ],
                [
                    'status_pendaftaran' => $keputusan->value,
                    'status_santri' => $target->value,
                ],
                $request,
            );
        });

        $model = Pendaftaran::query()
            ->whereKey($pendaftaran)
            ->with(['santri.wali', 'santri.pembayarans', 'santri.buktiPembayaran', 'tahunAjaran', 'diprosesOleh'])
            ->firstOrFail();

        if ($keputusan === StatusPendaftaran::Diterima && $model->santri && $model->santri->wali_id) {
            $wali = User::find($model->santri->wali_id);
            if ($wali) {
                $this->notifikasi->kirim($wali, 'pendaftaran_diterima', 'Pendaftaran Diterima', 'Pendaftaran santri Anda telah diterima. Silakan lakukan pembayaran biaya pendaftaran.', '/wali/pendaftaran/'.$model->id, ['pendaftaran_id' => $model->id, 'santri_id' => $model->santri_id]);
            }
        } elseif ($keputusan === StatusPendaftaran::Ditolak && $model->santri && $model->santri->wali_id) {
            $wali = User::find($model->santri->wali_id);
            if ($wali) {
                $this->notifikasi->kirim($wali, 'pendaftaran_ditolak', 'Pendaftaran Ditolak', 'Pendaftaran santri Anda ditolak.'.($alasan ? ' Alasan: '.$alasan : ''), '/wali/pendaftaran/'.$model->id, ['pendaftaran_id' => $model->id, 'santri_id' => $model->santri_id]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => $keputusan === StatusPendaftaran::Diterima
                ? 'Pendaftaran diterima. Uang pendaftaran kini dapat dibayarkan oleh wali.'
                : 'Pendaftaran ditolak. Wali dapat melihat alasan di detail pendaftaran.',
            'data' => new PendaftaranDetailResource($model),
        ]);
    }

    /**
     * Open the payment lane for an accepted registration.
     *
     * A registration owes exactly one fee. If no `pendaftaran`-typed fee exists
     * for this student's academic year it is created from `biaya_pendaftaran`;
     * otherwise (a Phase 2 row already there) the link to this registration is
     * backfilled. The amount comes from the school's settings — the payload can
     * carry no price.
     */
    private function bukaPembayaran(Pendaftaran $pendaftaran, Santri $santri, User $admin): void
    {
        $fee = $santri->pembayarans()
            ->where('tahun_ajaran_id', $pendaftaran->tahun_ajaran_id)
            ->where('jenis_pembayaran', JenisPembayaran::Pendaftaran)
            ->first();

        if ($fee instanceof Pembayaran) {
            if ($fee->pendaftaran_id === null) {
                $fee->update(['pendaftaran_id' => $pendaftaran->getKey()]);
            }

            return;
        }

        $biaya = Pengaturan::read('biaya_pendaftaran');

        $santri->pembayarans()->create([
            'pendaftaran_id' => $pendaftaran->getKey(),
            'tahun_ajaran_id' => $pendaftaran->tahun_ajaran_id,
            'jenis_pembayaran' => JenisPembayaran::Pendaftaran,
            'jumlah' => number_format(max(0, (float) $biaya), 2, '.', ''),
            'status' => StatusPembayaran::Belum,
            'dicatat_oleh' => $admin->getKey(),
        ]);
    }
}
