<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Consume a reset link and set a new password.
 */
class NewPasswordController extends Controller
{
    /**
     * Reset the password for the token's account.
     *
     * The `hashed` cast on User does the hashing; this controller never sees a
     * plaintext password beyond the request lifetime.
     */
    public function __invoke(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                // Any token issued before the reset is assumed compromised.
                $user->tokens()->delete();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'success' => false,
                'message' => $this->messageFor($status),
                'data' => null,
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kata sandi berhasil diperbarui. Silakan masuk.',
            'data' => null,
        ]);
    }

    /**
     * Translate a broker status into something a person can act on.
     */
    private function messageFor(string $status): string
    {
        return match ($status) {
            Password::INVALID_TOKEN => 'Tautan reset kata sandi tidak valid atau sudah dipakai. Silakan minta tautan baru.',
            Password::INVALID_USER => 'Email tersebut tidak terdaftar.',
            Password::RESET_THROTTLED => 'Terlalu banyak percobaan. Coba lagi beberapa saat lagi.',
            default => 'Kata sandi gagal diperbarui. Silakan coba lagi.',
        };
    }
}
