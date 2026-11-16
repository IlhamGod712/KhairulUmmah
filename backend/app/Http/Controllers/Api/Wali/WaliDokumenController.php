<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Wali;

use App\Enums\JenisDokumen;
use App\Enums\SantriStatus;
use App\Enums\StatusDokumen;
use App\Enums\StatusPendaftaran;
use App\Http\Controllers\Controller;
use App\Http\Requests\Wali\UploadDokumenRequest;
use App\Http\Resources\DokumenWaliResource;
use App\Models\Dokumen;
use App\Models\Pendaftaran;
use App\Models\Santri;
use App\Services\AuditTrailService;
use App\Support\MimeTerpercaya;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WaliDokumenController extends Controller
{
    public function __construct(private readonly AuditTrailService $audit) {}

    public function index(Request $request, Pendaftaran $pendaftaran): JsonResponse
    {
        $user = $request->user();
        $santri = $this->resolveSantriForPendaftaran($pendaftaran, $user);

        $santri->load('dokumen');
        $docs = $santri->dokumen;

        $wajib = [JenisDokumen::KartuKeluarga, JenisDokumen::AktaLahir, JenisDokumen::Ijazah, JenisDokumen::IdentitasWali, JenisDokumen::PasFoto];
        $map = [];
        foreach ($docs as $d) {
            $key = $d->jenis instanceof \BackedEnum ? $d->jenis->value : (string) $d->jenis;
            $map[$key] = $d;
        }

        $slots = [];
        $disetujui = 0;
        $menunggu = 0;
        $ditolak = 0;
        $belum = 0;
        foreach ($wajib as $j) {
            $k = $j->value;
            $d = $map[$k] ?? null;
            if (! $d) {
                $belum++;
                $slots[] = [
                    'jenis' => $k,
                    'nama' => $j->label(),
                    'wajib' => true,
                    'status' => 'belum',
                    'status_label' => 'Belum diunggah',
                    'ada' => false,
                ];

                continue;
            }
            $status = $d->status instanceof \BackedEnum ? $d->status->value : (string) $d->status;
            if ($status === StatusDokumen::Diterima->value) {
                $disetujui++;
            } elseif ($status === StatusDokumen::Menunggu->value) {
                $menunggu++;
            } elseif ($status === StatusDokumen::Ditolak->value) {
                $ditolak++;
            }
            $slots[] = (new DokumenWaliResource($d))->resolve($request);
            $slots[count($slots) - 1]['ada'] = true;
        }

        $total = count($wajib);
        $lengkap = $disetujui === $total;

        return response()->json([
            'success' => true,
            'message' => 'Daftar dokumen persyaratan.',
            'data' => [
                'dokumen' => $slots,
                'kelengkapan' => [
                    'total' => $total,
                    'disetujui' => $disetujui,
                    'menunggu' => $menunggu,
                    'ditolak' => $ditolak,
                    'belum' => $belum,
                    'lengkap' => $lengkap,
                ],
            ],
        ]);
    }

    public function upload(UploadDokumenRequest $request, Pendaftaran $pendaftaran, string $jenis): JsonResponse
    {
        $user = $request->user();
        $santri = $this->resolveSantriForPendaftaran($pendaftaran, $user);

        // Cannot upload if pendaftaran rejected or santri terminal
        if ($pendaftaran->status === StatusPendaftaran::Ditolak) {
            return response()->json(['success' => false, 'message' => 'Tidak dapat mengunggah dokumen untuk pendaftaran yang ditolak.', 'data' => null], 422);
        }
        if (in_array($santri->status?->value ?? (string) $santri->status, [SantriStatus::Aktif->value, SantriStatus::Ditolak->value, SantriStatus::Dibatalkan->value], true)) {
            return response()->json(['success' => false, 'message' => 'Tidak dapat mengunggah dokumen untuk santri dengan status saat ini.', 'data' => null], 422);
        }

        $jenisEnum = JenisDokumen::tryFrom($jenis);
        if (! $jenisEnum || ! $jenisEnum->isRequired()) {
            abort(404);
        }

        $file = $request->file('berkas');
        if (! $file) {
            return response()->json(['success' => false, 'message' => 'Berkas dokumen wajib dipilih.', 'data' => null], 422);
        }

        $mime = $request->mimeTerpercaya();
        if (! MimeTerpercaya::isAllowedForDocuments($mime)) {
            return response()->json(['success' => false, 'message' => 'Berkas harus berupa PDF, JPG, atau PNG.', 'data' => null], 422);
        }

        $original = Str::limit(str_replace(["\n", "\r", "\t"], ' ', $file->getClientOriginalName()), 150, '');
        $ext = $this->extensionForMime($mime);
        $filename = (string) Str::uuid().($ext ? '.'.$ext : '');
        $path = $file->storeAs('dokumen', $filename, 'local');
        if ($path === false) {
            throw new \RuntimeException('Gagal menyimpan dokumen.');
        }

        try {
            /** @var array{dokumen: ?Dokumen, old_path: ?string, message: ?string} $hasil */
            $hasil = DB::transaction(function () use ($santri, $jenisEnum, $path, $mime, $original, $file, $request): array {
                $dokumen = Dokumen::query()
                    ->where('santri_id', $santri->getKey())
                    ->where('jenis', $jenisEnum->value)
                    ->lockForUpdate()
                    ->first();

                if ($dokumen) {
                    $status = $dokumen->status instanceof \BackedEnum
                        ? $dokumen->status->value
                        : (string) $dokumen->status;

                    if ($status === StatusDokumen::Diterima->value) {
                        return ['dokumen' => null, 'old_path' => null, 'message' => 'Dokumen yang sudah disetujui tidak dapat diganti.'];
                    }

                    if ($status === StatusDokumen::Menunggu->value) {
                        return ['dokumen' => null, 'old_path' => null, 'message' => 'Dokumen sedang menunggu verifikasi, tidak dapat diganti.'];
                    }

                    $statusSebelumnya = $status;
                    $oldPath = $dokumen->path;

                    $dokumen->path = $path;
                    $dokumen->nama_asli = $original;
                    $dokumen->mime_type = $mime;
                    $dokumen->ukuran_bytes = $file->getSize();
                    $dokumen->status = StatusDokumen::Menunggu;
                    $dokumen->catatan = null;
                    $dokumen->diverifikasi_oleh = null;
                    $dokumen->diverifikasi_pada = null;
                    $dokumen->save();
                } else {
                    $statusSebelumnya = null;
                    $oldPath = null;

                    $dokumen = Dokumen::create([
                        'santri_id' => $santri->getKey(),
                        'jenis' => $jenisEnum->value,
                        'path' => $path,
                        'nama_asli' => $original,
                        'mime_type' => $mime,
                        'ukuran_bytes' => $file->getSize(),
                        'status' => StatusDokumen::Menunggu,
                    ]);
                }

                $this->audit->log(
                    'dokumen.uploaded',
                    'Wali mengunggah dokumen persyaratan.',
                    $dokumen,
                    ['jenis' => $jenisEnum->value, 'status' => $statusSebelumnya],
                    ['jenis' => $jenisEnum->value, 'status' => StatusDokumen::Menunggu->value],
                    $request,
                );

                return ['dokumen' => $dokumen, 'old_path' => $oldPath, 'message' => null];
            });
        } catch (UniqueConstraintViolationException) {
            Storage::disk('local')->delete($path);

            return response()->json(['success' => false, 'message' => 'Dokumen sedang menunggu verifikasi, tidak dapat diganti.', 'data' => null], 422);
        } catch (\Throwable $e) {
            if ($path && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }

        if ($hasil['dokumen'] === null) {
            Storage::disk('local')->delete($path);

            return response()->json(['success' => false, 'message' => $hasil['message'], 'data' => null], 422);
        }

        $dokumen = $hasil['dokumen'];
        $oldPath = $hasil['old_path'];

        if ($oldPath && $oldPath !== $path && Storage::disk('local')->exists($oldPath)) {
            Storage::disk('local')->delete($oldPath);
        }

        $dokumen->load('diverifikasiOleh');

        return response()->json([
            'success' => true,
            'message' => 'Dokumen berhasil diunggah, menunggu verifikasi.',
            'data' => (new DokumenWaliResource($dokumen))->resolve($request),
        ], 201);
    }

    public function berkas(Request $request, Pendaftaran $pendaftaran, string $jenis): StreamedResponse
    {
        $user = $request->user();
        $santri = $this->resolveSantriForPendaftaran($pendaftaran, $user);

        $jenisEnum = JenisDokumen::tryFrom($jenis);
        if (! $jenisEnum || ! $jenisEnum->isRequired()) {
            abort(404);
        }

        $dokumen = Dokumen::query()->where('santri_id', $santri->getKey())->where('jenis', $jenisEnum->value)->first();
        if (! $dokumen) {
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

    private function resolveSantriForPendaftaran(Pendaftaran $pendaftaran, $user): Santri
    {
        $santri = $pendaftaran->santri;
        if (! $santri) {
            abort(404);
        }
        if ((int) $santri->wali_id !== (int) $user->id) {
            abort(404);
        }

        return $santri;
    }

    private function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => '',
        };
    }
}
