<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\StatusPembayaran;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The admin's verdict on an uploaded SPP proof.
 *
 * Only two answers exist: the proof checks out (`lunas`) or it does not
 * (`ditolak`) — the same two the registration-fee verdict offers, because the
 * business is the same question asked of a different invoice. `Rule::in` is the
 * whole allow-list: a payload naming `belum` or `menunggu_verifikasi` is
 * refused here rather than quietly coerced, so no client can talk its way to a
 * state the state machine would not have permitted from `menunggu_verifikasi`.
 *
 * The rules say nothing about which invoices may be touched or what the amount
 * is; those are the controller's job — ownership of the invoice, the period
 * uniqueness, and the fact that the nominal came from the school settings.
 */
class UpdateSppStatusRequest extends FormRequest
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
