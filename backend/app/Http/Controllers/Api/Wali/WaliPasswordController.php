<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Wali;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wali\UpdateWaliPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Change the signed-in guardian's password.
 *
 * The hash is produced by the model's `hashed` cast, so plaintext is never
 * written and never compared outside `Hash::check`. The current password is
 * verified by Laravel's `current_password` rule before the new one is accepted,
 * which means an unattended unlocked session cannot be used to take the account
 * over.
 */
class WaliPasswordController extends Controller
{
    /**
     * Replace the authenticated guardian's password.
     */
    public function __invoke(UpdateWaliPasswordRequest $request): JsonResponse
    {
        /** @var User $wali */
        $wali = $request->user();

        $wali->password = $request->string('password')->toString();
        $wali->save();

        // Every stored token is revoked, for the same reason logout does it: in
        // Sanctum's stateful mode there is no way to tell which stored token
        // belongs to this browser, so a password change has to fail closed. The
        // session cookie is left intact, so the guardian who just made the
        // change is not signed out of the tab they are using.
        $wali->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah. Sesi lain telah dikeluarkan.',
            'data' => null,
        ]);
    }
}
