<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Sign in and sign out.
 *
 * A wrong email and a wrong password get the same generic 401 on purpose, so
 * the endpoint cannot be used to enumerate which guardians are registered.
 *
 * The response adapts to how the caller reached us. Sanctum's stateful mode
 * only attaches the session middleware when the request carries an Origin or
 * Referer listed in `config/sanctum.php` -> stateful, which is true for the
 * browser SPA and false for curl, mobile or a test client. A stateful caller is
 * signed in with the session cookie and never sees a token; a stateless caller
 * is handed the personal access token to present as a bearer token.
 */
class AuthenticatedSessionController extends Controller
{
    /**
     * Verify credentials and open a session.
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email')->lower()->toString())->first();

        if (! $user instanceof User || ! Hash::check($request->string('password')->toString(), $user->password)) {
            return $this->invalidCredentials();
        }

        $stateful = $request->hasSession();
        $token = $user->createToken($this->tokenName($request));

        if ($stateful) {
            auth()->guard('web')->login($user, $request->boolean('remember'));
            $request->session()->regenerate();
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil masuk.',
            'data' => [
                'user' => new UserResource($user),
                // Only non-browser clients get a token to keep. Handing one to
                // the SPA as well would put a bearer credential in reach of any
                // script on the page, which the session cookie avoids.
                'token' => $stateful ? null : $token->plainTextToken,
            ],
        ]);
    }

    /**
     * Close the session and revoke every token the account holds.
     *
     * Revoking every token rather than "the current one" is deliberate. In
     * Sanctum's stateful mode `currentAccessToken()` is a TransientToken, not a
     * row, so there is no reliable way to tell which stored token belongs to
     * this browser. Failing closed here costs a user who is signed in on two
     * devices a re-login on the other one; not doing it would leave a bearer
     * token alive after the user believes they have signed out.
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            $user->tokens()->delete();
        }

        if ($request->hasSession()) {
            auth()->guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil keluar.',
            'data' => null,
        ]);
    }

    /**
     * Label the token so a row in personal_access_tokens is traceable.
     */
    private function tokenName(Request $request): string
    {
        $agent = $request->userAgent();

        return $agent === null ? 'login' : mb_substr('login: '.$agent, 0, 200);
    }

    /**
     * One message for both "no such email" and "wrong password".
     */
    private function invalidCredentials(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Email atau kata sandi salah.',
            'data' => null,
        ], JsonResponse::HTTP_UNAUTHORIZED);
    }
}
