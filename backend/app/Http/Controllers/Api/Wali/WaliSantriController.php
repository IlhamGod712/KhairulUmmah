<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Wali;

use App\Http\Controllers\Controller;
use App\Http\Resources\SantriResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The students linked to the signed-in guardian's account.
 *
 * The list comes from the `wali_id` relation on the authenticated user, so the
 * scope is the query itself rather than a filter applied afterwards. There is no
 * parameter to widen it, and no way to ask for another guardian's students.
 *
 * This replaces the Phase 3 probe at `/api/wali/anak`, which existed only to
 * give the `role:wali` guard something to protect. The guard, the scoping, and
 * the isolation between guardians are unchanged; only the URL and the shape of
 * the surrounding work grew.
 */
class WaliSantriController extends Controller
{
    /**
     * List the students this guardian is responsible for.
     */
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $wali */
        $wali = $request->user();

        $santris = $wali->santris()
            ->with('tahunAjaran')
            ->orderBy('nama_lengkap')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Data santri berhasil dimuat.',
            'data' => SantriResource::collection($santris),
        ]);
    }
}
