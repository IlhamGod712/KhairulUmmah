<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FilterSantriRequest extends FormRequest
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
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', 'string', 'max:30'],
            'tahun_ajaran_id' => ['sometimes', 'nullable', 'integer', 'exists:tahun_ajaran,id'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
