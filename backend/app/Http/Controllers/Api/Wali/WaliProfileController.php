<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Wali;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wali\UpdateWaliProfileRequest;
use App\Http\Resources\WaliProfileResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The guardian's own profile.
 *
 * There is no route parameter and no query parameter anywhere in this class.
 * The subject is always the authenticated user, so there is no endpoint a
 * guardian could point at another account — `GET /api/wali/profile/123` does not
 * exist, and adding it would reintroduce exactly the bug the brief warns about.
 */
class WaliProfileController extends Controller
{
    /**
     * Show the signed-in guardian's profile.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil dimuat.',
            'data' => new WaliProfileResource($this->wali($request)),
        ]);
    }

    /**
     * Update the fields a guardian is allowed to change.
     *
     * Only `name` and `no_hp` are read from the validated payload, and each is
     * assigned explicitly rather than mass assigned. That is belt and braces:
     * the form request already rejects `role`, `id`, and `email`, but assigning
     * attribute by attribute means a future change to that request cannot
     * accidentally widen what gets written to a security-relevant column.
     */
    public function update(UpdateWaliProfileRequest $request): JsonResponse
    {
        $wali = $this->wali($request);
        $validated = $request->validated();

        $wali->name = $validated['name'];
        $wali->no_hp = $this->normalisePhone($validated['no_hp'] ?? null);
        $wali->save();

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data' => new WaliProfileResource($wali->refresh()),
        ]);
    }

    /**
     * The authenticated guardian.
     */
    private function wali(Request $request): User
    {
        /** @var User $wali */
        $wali = $request->user();

        return $wali;
    }

    /**
     * Trim a phone number and collapse an all-whitespace value to null.
     *
     * Validation allows a nullable string, so `""` and `"   "` both pass. Storing
     * those would make "not provided" and "provided but blank" two different
     * states that both look empty in the UI.
     */
    private function normalisePhone(?string $noHp): ?string
    {
        if ($noHp === null) {
            return null;
        }

        $trimmed = trim($noHp);

        return $trimmed === '' ? null : $trimmed;
    }
}
