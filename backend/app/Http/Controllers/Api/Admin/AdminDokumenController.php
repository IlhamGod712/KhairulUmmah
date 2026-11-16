<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\JenisDokumen;
use App\Enums\StatusDokumen;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterDokumenRequest;
use App\Http\Requests\Admin\UpdateDokumenStatusRequest;
use App\Http\Resources\DokumenAdminResource;
use App\Models\Dokumen;
use App\Models\User;
use App\Services\AktivasiSantri;
use App\Services\AuditTrailService;
use App\Services\NotifikasiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminDokumenController extends Controller
{
    public function __construct(
        private AktivasiSantri $aktivasiSantri,
        private NotifikasiService $notifikasi,
        private AuditTrailService $audit,
    ) {}

    public function index(FilterDokumenRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $q = Dokumen::query()->with(['santri', 'diverifikasiOleh'])->whereNot('jenis', JenisDokumen::BuktiPembayaran->value);

        if (! empty($validated['status'])) {
            $q->where('status', $validated['status']);
        }
        if (! empty($validated['jenis'])) {
            $q->where('jenis', $validated['jenis']);
        }

        $items = $q->orderByRaw("CASE WHEN status = '".StatusDokumen::Menunggu->value."' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar dokumen persyaratan.',
            'data' => DokumenAdminResource::collection($items)->resolve($request),
        ]);
    }

    public function show(Request $request, Dokumen $dokumen): JsonResponse
    {
        if ($dokumen->jenis === JenisDokumen::BuktiPembayaran) {
            abort(404);
        }
        $dokumen->load(['santri', 'diverifikasiOleh']);

        return response()->json([
            'success' => true,
            'message' => 'Detail dokumen.',
            'data' => (new DokumenAdminResource($dokumen))->resolve($request),
        ]);
    }

    public function updateStatus(UpdateDokumenStatusRequest $request, Dokumen $dokumen): JsonResponse
    {
        if ($dokumen->jenis === JenisDokumen::BuktiPembayaran) {
            abort(404);
        }

        $target = StatusDokumen::from($request->validated()['status']);
        $catatan = $request->input('catatan');

        $dokumen = DB::transaction(function () use ($dokumen, $target, $catatan, $request): ?Dokumen {
            $dokumen = Dokumen::query()->lockForUpdate()->findOrFail($dokumen->getKey());

            if ($dokumen->jenis === JenisDokumen::BuktiPembayaran) {
                abort(404);
            }

            $status = $dokumen->status instanceof \BackedEnum ? $dokumen->status->value : (string) $dokumen->status;
            if ($status !== StatusDokumen::Menunggu->value) {
                return null;
            }

            $jenis = $dokumen->jenis instanceof \BackedEnum
                ? $dokumen->jenis->value
                : (string) $dokumen->jenis;

            $dokumen->status = $target;
            $dokumen->catatan = $target === StatusDokumen::Ditolak ? $catatan : null;
            $dokumen->diverifikasi_oleh = $request->user()->id;
            $dokumen->diverifikasi_pada = now();
            $dokumen->save();

            $this->audit->log(
                $target === StatusDokumen::Diterima ? 'dokumen.approved' : 'dokumen.rejected',
                $target === StatusDokumen::Diterima
                    ? 'Admin menyetujui dokumen persyaratan.'
                    : 'Admin menolak dokumen persyaratan.',
                $dokumen,
                ['jenis' => $jenis, 'status' => $status],
                ['jenis' => $jenis, 'status' => $target->value],
                $request,
            );

            return $dokumen;
        });

        if ($dokumen === null) {
            return response()->json(['success' => false, 'message' => 'Hanya dokumen berstatus menunggu yang dapat diverifikasi ulang.', 'data' => null], 422);
        }

        $dokumen->load(['santri', 'diverifikasiOleh']);
        $aktivasi = null;
        if ($dokumen->santri && $dokumen->santri->wali_id) {
            $wali = User::find($dokumen->santri->wali_id);
            if ($wali) {
                if ($target === StatusDokumen::Diterima) {
                    $aktivasi = $this->aktivasiSantri->cobaAktifkan($dokumen->santri);
                    $this->notifikasi->kirim($wali, 'dokumen_disetujui', 'Dokumen Disetujui', 'Salah satu dokumen persyaratan telah disetujui.', '/wali/pendaftaran', ['dokumen_id' => $dokumen->id, 'santri_id' => $dokumen->santri_id]);
                    if (is_array($aktivasi) && ($aktivasi['diaktifkan'] ?? false)) {
                        $wali2 = User::find($dokumen->santri->wali_id);
                        if ($wali2) {
                            $this->notifikasi->kirim($wali2, 'santri_aktif', 'Santri Diaktifkan', 'Santri telah diaktifkan.', '/wali/santri', ['santri_id' => $dokumen->santri_id]);
                        }
                    }
                } elseif ($target === StatusDokumen::Ditolak) {
                    $this->notifikasi->kirim($wali, 'dokumen_ditolak', 'Dokumen Ditolak', 'Salah satu dokumen persyaratan ditolak. Silakan unggah ulang.', '/wali/pendaftaran', ['dokumen_id' => $dokumen->id, 'santri_id' => $dokumen->santri_id]);
                }
            }
        } elseif ($target === StatusDokumen::Diterima && $dokumen->santri) {
            $aktivasi = $this->aktivasiSantri->cobaAktifkan($dokumen->santri);
        }

        return response()->json([
            'success' => true,
            'message' => $target === StatusDokumen::Diterima ? 'Dokumen disetujui.' : 'Dokumen ditolak.',
            'data' => [
                'dokumen' => (new DokumenAdminResource($dokumen))->resolve($request),
                'aktivasi' => $aktivasi,
            ],
        ]);
    }

    public function berkas(Request $request, Dokumen $dokumen): StreamedResponse
    {
        if ($dokumen->jenis === JenisDokumen::BuktiPembayaran) {
            abort(404);
        }
        if (! Storage::disk('local')->exists($dokumen->path)) {
            abort(404);
        }
        $mime = $dokumen->mime ?? ($dokumen->mime_type ?? 'application/octet-stream');
        $name = $dokumen->nama_asli ?: basename($dokumen->path);

        return Storage::disk('local')->response($dokumen->path, $name, [
            'Content-Type' => $mime,
        ]);
    }
}
