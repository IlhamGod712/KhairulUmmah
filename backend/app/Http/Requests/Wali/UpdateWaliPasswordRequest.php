<?php

declare(strict_types=1);

namespace App\Http\Requests\Wali;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateWaliPasswordRequest extends FormRequest
{
    /**
     * Handled by the `auth:sanctum` + `role:wali` middleware pair on the route.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The current password has to be proven before a new one is accepted, so a
     * borrowed unlocked session cannot take the account over. `current_password`
     * is Laravel's own rule and compares against the authenticated user's hash.
     * The new password uses the same rule as registration plus a "different
     * from the old one" check, so the old password genuinely stops working.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => [
                'required',
                'confirmed',
                'different:current_password',
                Password::min(8)->letters()->numbers(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Password lama wajib diisi.',
            'current_password.current_password' => 'Password lama salah.',
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
            'password.different' => 'Password baru harus berbeda dari password lama.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => 'password lama',
            'password' => 'password baru',
        ];
    }
}
