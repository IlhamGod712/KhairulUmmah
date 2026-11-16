<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

/**
 * Email a password reset link.
 */
class PasswordResetLinkController extends Controller
{
    /**
     * Send the reset link, always answering the same thing.
     *
     * The endpoint is deliberately uninformative. Whether the address is
     * registered, unknown, or currently throttled, the response body, status
     * code, and timing are the same, because telling the caller an address is
     * unregistered is the same account enumeration leak the login endpoint
     * avoids.
     */
    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        return response()->json([
            'success' => true,
            'message' => 'Jika email tersebut terdaftar, tautan reset kata sandi sudah dikirim.',
            'data' => [
                'email' => $request->string('email')->lower()->toString(),
            ],
        ]);
    }
}
