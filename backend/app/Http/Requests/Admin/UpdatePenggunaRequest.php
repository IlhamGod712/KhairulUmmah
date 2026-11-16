<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePenggunaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => mb_strtolower(trim($email))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $pengguna = $this->route('pengguna');
        $userId = $pengguna instanceof User
            ? $pengguna->getKey()
            : (is_numeric($pengguna) ? (int) $pengguna : null);

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'no_hp' => ['sometimes', 'nullable', 'string', 'max:30'],
            'role' => ['sometimes', 'required', Rule::enum(UserRole::class)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
        ];
    }
}
