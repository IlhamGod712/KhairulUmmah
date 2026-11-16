<?php

declare(strict_types=1);

namespace App\Http\Requests\Wali;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWaliProfileRequest extends FormRequest
{
    /**
     * Authorization is handled by the `auth:sanctum` + `role:wali` middleware
     * pair on the route, not here. The acting user always comes from the
     * session, so there is no id in this payload that could point somewhere
     * else.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The guardian may correct their name and phone number. `email` is
     * deliberately absent: it is the login identity and the destination for
     * password reset links, so changing it needs confirmation of the new
     * address. `role` and `id` are never accepted from a client at all, so a
     * crafted payload cannot promote a guardian to admin.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'no_hp' => ['nullable', 'string', 'max:25', 'regex:/^[0-9+\-\s()]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.min' => 'Nama lengkap minimal 3 karakter.',
            'name.max' => 'Nama lengkap maksimal 255 karakter.',
            'no_hp.max' => 'Nomor HP maksimal 25 karakter.',
            'no_hp.regex' => 'Nomor HP hanya boleh berisi angka, spasi, dan tanda + - ( ).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama lengkap',
            'no_hp' => 'nomor HP',
        ];
    }
}
