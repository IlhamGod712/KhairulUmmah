<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\StatusPembayaran;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The optional filters on the admin's SPP review queue.
 *
 * Every filter is validated rather than coerced, and the reason is that a queue
 * that silently ignores a filter is worse than one that refuses it. `index`
 * narrowed the query from the raw request value, so `?periode=bla` or
 * `?status=menunggu` — a client that spelled a status from the label rather than
 * the wire value — used to answer `200` with a short list, which reads to the
 * admin as "nothing is outstanding" when it in fact means "the filter matched
 * nothing because it was not a filter". A `422` says the filter was wrong.
 *
 * The `periode` shape is the same `YYYY-MM` the generate endpoint takes, so the
 * two screens cannot disagree about what a month is.
 */
class FilterSppRequest extends FormRequest
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
            'tahun_ajaran_id' => [
                'nullable',
                'integer',
                Rule::exists('tahun_ajaran', 'id'),
            ],
            'periode' => ['nullable', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            // `Rule::enum` is the same allow-list the write endpoints use, so the
            // queue cannot be filtered by a status that no invoice can hold.
            'status' => ['nullable', Rule::enum(StatusPembayaran::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tahun_ajaran_id.exists' => 'Tahun ajaran tidak ditemukan.',
            'periode.regex' => 'Periode harus dalam format YYYY-MM, misalnya 2026-07.',
            'status.enum' => 'Status pembayaran tidak dikenal.',
        ];
    }
}
