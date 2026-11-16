<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterSantriRequest;
use App\Http\Requests\Admin\UpdateSantriRequest;
use App\Http\Resources\SantriAdminResource;
use App\Models\Santri;
use App\Services\AuditTrailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminSantriController extends Controller
{
    public function __construct(private readonly AuditTrailService $audit) {}

    public function index(FilterSantriRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $q = Santri::query()->with(['wali', 'tahunAjaran']);

        if (! empty($validated['q'])) {
            $search = trim((string) $validated['q']);
            $q->where(function ($sub) use ($search): void {
                $sub->where('nama_lengkap', 'like', '%'.$search.'%')
                    ->orWhere('nis', 'like', '%'.$search.'%')
                    ->orWhere('nisn', 'like', '%'.$search.'%');
            });
        }

        if (! empty($validated['status'])) {
            $q->where('status', $validated['status']);
        }

        if (! empty($validated['tahun_ajaran_id'])) {
            $q->where('tahun_ajaran_id', (int) $validated['tahun_ajaran_id']);
        }

        $perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : 15;
        $perPage = $perPage > 100 ? 100 : ($perPage < 1 ? 15 : $perPage);

        $items = $q->orderByDesc('id')->paginate($perPage)->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Daftar santri.',
            'data' => [
                'items' => SantriAdminResource::collection($items->items())->resolve($request),
                'pagination' => [
                    'current_page' => $items->currentPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                    'last_page' => $items->lastPage(),
                ],
            ],
        ]);
    }

    public function show(Request $request, Santri $santri): JsonResponse
    {
        $santri->load(['wali', 'tahunAjaran']);

        return response()->json([
            'success' => true,
            'message' => 'Detail santri.',
            'data' => (new SantriAdminResource($santri))->resolve($request),
        ]);
    }

    public function update(UpdateSantriRequest $request, Santri $santri): JsonResponse
    {
        $validated = $request->validated();

        // Whitelist only allowed fields; never allow changing sensitive fields
        $allowed = [
            'nama_lengkap',
            'jenis_kelamin',
            'tempat_lahir',
            'tanggal_lahir',
            'alamat',
            'kelurahan',
            'kecamatan',
            'kabupaten',
            'provinsi',
            'no_hp',
            'nama_ayah',
            'nama_ibu',
            'pekerjaan_ayah',
            'pekerjaan_ibu',
            'penghasilan_bulanan',
            'sekolah_asal',
            'nisn',
        ];

        $update = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $validated)) {
                $update[$key] = $validated[$key];
            }
        }

        if (! empty($update)) {
            $santri = DB::transaction(function () use ($santri, $update, $request): Santri {
                $santri = Santri::query()
                    ->whereKey($santri->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $santri->fill($update);

                if (! $santri->isDirty()) {
                    return $santri;
                }

                $oldValues = [];
                $newValues = [];

                foreach (array_keys($santri->getDirty()) as $key) {
                    $oldValues[$key] = $santri->getOriginal($key);
                    $newValues[$key] = $santri->getAttribute($key);
                }

                $santri->save();

                $this->audit->log(
                    'santri.updated',
                    'Admin memperbarui data santri.',
                    $santri,
                    $oldValues,
                    $newValues,
                    $request,
                );

                return $santri;
            });
        }

        $santri->load(['wali', 'tahunAjaran']);

        return response()->json([
            'success' => true,
            'message' => 'Data santri berhasil diperbarui.',
            'data' => (new SantriAdminResource($santri))->resolve($request),
        ]);
    }
}
