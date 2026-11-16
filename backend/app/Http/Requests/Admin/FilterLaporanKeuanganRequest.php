<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\JenisPembayaran;
use App\Enums\StatusPembayaran;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterLaporanKeuanganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tanggal_awal' => ['nullable', 'date'],
            'tanggal_akhir' => ['nullable', 'date', 'after_or_equal:tanggal_awal'],
            'jenis_pembayaran' => ['nullable', Rule::enum(JenisPembayaran::class)],
            'status' => ['nullable', Rule::enum(StatusPembayaran::class)],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal_akhir.after_or_equal' => 'Tanggal akhir harus sama atau setelah tanggal awal.',
        ];
    }
}
