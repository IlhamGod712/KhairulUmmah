<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePengaturanRequest extends FormRequest
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
            'pengaturan' => ['required', 'array'],
            'pengaturan.biaya_spp_bulanan' => ['sometimes', 'required', 'numeric', 'min:0'],
            'pengaturan.spp_tanggal_jatuh_tempo' => ['sometimes', 'required', 'integer', 'min:1', 'max:28'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pengaturan.required' => 'Pengaturan wajib diisi.',
            'pengaturan.array' => 'Format pengaturan tidak valid.',
            'pengaturan.biaya_spp_bulanan.required' => 'Biaya SPP bulanan wajib diisi.',
            'pengaturan.biaya_spp_bulanan.numeric' => 'Biaya SPP bulanan harus berupa angka.',
            'pengaturan.biaya_spp_bulanan.min' => 'Biaya SPP bulanan tidak boleh negatif.',
            'pengaturan.spp_tanggal_jatuh_tempo.required' => 'Tanggal jatuh tempo wajib diisi.',
            'pengaturan.spp_tanggal_jatuh_tempo.integer' => 'Tanggal jatuh tempo harus berupa angka bulat.',
            'pengaturan.spp_tanggal_jatuh_tempo.min' => 'Tanggal jatuh tempo minimal 1.',
            'pengaturan.spp_tanggal_jatuh_tempo.max' => 'Tanggal jatuh tempo maksimal 28.',
        ];
    }
}
