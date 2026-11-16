<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in account. Available to every role.
 */
class ProfileController extends Controller
{
    /**
     * Return the authenticated user.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil dimuat.',
            'data' => new UserResource($request->user()),
        ]);
    }
}
