<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\JenisDokumen;
use App\Enums\StatusDokumen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterDokumenRequest extends FormRequest
{
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
            'status' => ['nullable', Rule::enum(StatusDokumen::class)],
            'jenis' => ['nullable', Rule::enum(JenisDokumen::class)->except([JenisDokumen::BuktiPembayaran])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.*' => 'Status dokumen tidak valid.',
            'jenis.*' => 'Jenis dokumen tidak valid.',
        ];
    }
}
