<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexPenggunaRequest;
use App\Http\Requests\Admin\UpdatePenggunaRequest;
use App\Http\Resources\PenggunaResource;
use App\Models\User;
use App\Services\AuditTrailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminPenggunaController extends Controller
{
    public function __construct(private readonly AuditTrailService $audit) {}

    public function index(IndexPenggunaRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $query = User::query()->orderBy('name')->orderBy('id');
        if (isset($filters['search']) && $filters['search'] !== '') {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%');
            });
        }
        if (isset($filters['role']) && $filters['role'] !== '') {
            $query->where('role', $filters['role']);
        }
        $perPage = (int) ($filters['per_page'] ?? 15);
        $pengguna = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Daftar pengguna.',
            'data' => PenggunaResource::collection($pengguna),
            'meta' => [
                'current_page' => $pengguna->currentPage(),
                'last_page' => $pengguna->lastPage(),
                'per_page' => $pengguna->perPage(),
                'total' => $pengguna->total(),
            ],
        ]);
    }

    public function show(User $pengguna): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Detail pengguna.',
            'data' => new PenggunaResource($pengguna),
        ]);
    }

    public function update(UpdatePenggunaRequest $request, User $pengguna): JsonResponse
    {
        $current = $request->user();

        if (! $current instanceof User) {
            abort(403);
        }

        $validated = $request->validated();
        $pengguna = DB::transaction(function () use ($validated, $pengguna, $current, $request): User {
            $pengguna = User::query()
                ->whereKey($pengguna->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $newRole = isset($validated['role'])
                ? UserRole::from((string) $validated['role'])
                : $pengguna->role;

            if ($pengguna->is($current) && $newRole !== UserRole::Admin) {
                throw ValidationException::withMessages([
                    'role' => 'Tidak dapat menurunkan peran akun sendiri.',
                ]);
            }

            if ($pengguna->role === UserRole::Admin && $newRole !== UserRole::Admin) {
                $adminIds = User::query()
                    ->where('role', UserRole::Admin)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->pluck('id');

                if ($adminIds->count() <= 1) {
                    throw ValidationException::withMessages([
                        'role' => 'Tidak boleh menghilangkan seluruh akun admin.',
                    ]);
                }
            }

            $old = [
                'name' => $pengguna->name,
                'role' => $pengguna->role->value,
            ];
            $data = array_intersect_key(
                $validated,
                array_flip(['name', 'email', 'no_hp', 'password', 'role']),
            );

            if (($data['password'] ?? null) === null) {
                unset($data['password']);
            }

            if (isset($data['role'])) {
                $data['role'] = $newRole;
            }

            $pengguna->fill($data);

            if (! $pengguna->isDirty()) {
                return $pengguna;
            }

            $pengguna->save();

            $this->audit->log('pengguna.update', 'Memperbarui pengguna', $pengguna, $old, [
                'name' => $pengguna->name,
                'role' => $pengguna->role->value,
            ], request: $request);

            return $pengguna;
        });

        return response()->json([
            'success' => true,
            'message' => 'Pengguna berhasil diperbarui.',
            'data' => new PenggunaResource($pengguna),
        ]);
    }
}
