<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\StatusPembayaran;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The admin's verdict on an uploaded proof of the registration fee.
 *
 * Only two answers exist: the proof checks out (`lunas`) or it does not
 * (`ditolak`). A guardian cannot land the payment in `menunggu_verifikasi`
 * again — the state machine is what reopens that lane, and it only reopens it
 * upon a fresh upload, so the admin verdict here is always a terminal answer for
 * the current proof.
 */
class UpdatePembayaranStatusRequest extends FormRequest
{
    /**
     * Authorization is the `auth:sanctum` + `role:admin` pair on the route.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    StatusPembayaran::Lunas->value,
                    StatusPembayaran::Ditolak->value,
                ]),
            ],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Keputusan (status) wajib diisi.',
            'status.in' => 'Keputusan yang valid adalah "lunas" atau "ditolak".',
            'catatan.max' => 'Catatan maksimal 1000 karakter.',
        ];
    }
}
