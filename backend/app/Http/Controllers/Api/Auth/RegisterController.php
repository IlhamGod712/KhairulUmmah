<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Guardian self registration.
 *
 * Only wali accounts can be created here. Admin / Tata Usaha accounts are
 * provisioned by seeding, so this endpoint can never mint one: the role is
 * assigned in code rather than read from the request body.
 */
class RegisterController extends Controller
{
    /**
     * Create a wali account and sign it in immediately.
     */
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->string('name')->trim()->toString(),
            'email' => $request->string('email')->lower()->toString(),
            'password' => $request->string('password')->toString(),
            'role' => UserRole::Wali,
        ]);

        // Two credentials, deliberately: the session cookie drives the SPA
        // (Sanctum's stateful mode), while the personal access token is what a
        // non-browser client would present as a bearer token. Both expire on
        // the schedule in config/sanctum.php.
        $token = $user->createToken('registrasi');

        // See AuthenticatedSessionController: a stateless caller gets the token
        // in the body, a stateful one is signed in with the cookie and does
        // not see it.
        $stateful = $request->hasSession();

        if ($stateful) {
            auth()->guard('web')->login($user);
            $request->session()->regenerate();
        }

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil. Selamat datang.',
            'data' => [
                'user' => new UserResource($user),
                'token' => $stateful ? null : $token->plainTextToken,
            ],
        ], JsonResponse::HTTP_CREATED);
    }
}
