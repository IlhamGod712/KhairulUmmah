<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Which month of SPP to bill, and for which academic year.
 *
 * `periode` is `YYYY-MM` — a month *and* a year, never a bare month name, so
 * "Juli" in 2026 and "Juli" in 2027 are different requests and cannot be
 * confused. The controller normalises it to the first day of that month, which
 * is the value stored in `pembayarans.periode`.
 *
 * There is deliberately no `jumlah` or `nominal` rule. The amount is the
 * school's `biaya_spp_bulanan` setting, read by the controller at generation
 * time; accepting a price here would let the caller invent what a student owes,
 * which is the one thing an invoice must never take from a client.
 */
class GenerateSppRequest extends FormRequest
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
            'periode' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            // Optional: omitted means "the school's active academic year".
            'tahun_ajaran_id' => [
                'nullable',
                'integer',
                Rule::exists('tahun_ajaran', 'id'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'periode.required' => 'Periode tagihan SPP wajib diisi.',
            'periode.regex' => 'Periode harus dalam format YYYY-MM, misalnya 2026-07.',
            'tahun_ajaran_id.exists' => 'Tahun ajaran tidak ditemukan.',
        ];
    }
}
