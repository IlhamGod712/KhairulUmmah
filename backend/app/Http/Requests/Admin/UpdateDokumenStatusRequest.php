<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\StatusDokumen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateDokumenStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(StatusDokumen::class)->only([StatusDokumen::Diterima, StatusDokumen::Ditolak])],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Status verifikasi wajib diisi.',
            'status.*' => 'Status verifikasi tidak valid.',
            'catatan.max' => 'Catatan maksimal 1000 karakter.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $status = $this->input('status');
            if ($status === StatusDokumen::Ditolak->value && blank($this->input('catatan'))) {
                $validator->errors()->add('catatan', 'Catatan wajib diisi jika dokumen ditolak.');
            }
        });
    }
}
