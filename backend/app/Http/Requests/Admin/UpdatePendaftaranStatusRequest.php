<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\StatusPendaftaran;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The admin's decision on a registration submission.
 *
 * The payload is deliberately tiny: a target status from a two-value whitelist
 * and, when the answer is no, the reason. There is no id in here — the subject
 * is whichever `pendaftaran` the route bound to — and there is no arbitrary
 * status a crafted request could smuggle through (`diajukan`, `diverifikasi`,
 * `aktif` are all refused by `Rule::in` below). "Diterima" does not mean
 * "aktif": the student's activation stays Phase 6, decided by the payment lane
 * in `AdminPembayaranController`, never by this endpoint.
 */
class UpdatePendaftaranStatusRequest extends FormRequest
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
                    StatusPendaftaran::Diterima->value,
                    StatusPendaftaran::Ditolak->value,
                ]),
            ],
            // A rejection needs a reason the guardian can read; an acceptance
            // contradicts one, so supplying it is itself a validation error.
            'alasan_ditolak' => [
                'nullable',
                'string',
                'max:1000',
                Rule::requiredIf(fn (): bool => $this->input('status') === StatusPendaftaran::Ditolak->value),
                Rule::prohibitedIf(fn (): bool => $this->input('status') === StatusPendaftaran::Diterima->value),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Keputusan (status) wajib diisi.',
            'status.in' => 'Keputusan yang valid adalah "diterima" atau "ditolak".',
            'alasan_ditolak.required' => 'Alasan penolakan wajib diisi saat menolak pendaftaran.',
            'alasan_ditolak.prohibited' => 'Alasan penolakan tidak berlaku saat pendaftaran diterima.',
            'alasan_ditolak.max' => 'Alasan penolakan maksimal 1000 karakter.',
        ];
    }
}
