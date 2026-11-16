<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSantriRequest extends FormRequest
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
            'nama_lengkap' => ['sometimes', 'required', 'string', 'max:150'],
            'jenis_kelamin' => ['sometimes', 'required', Rule::in(['laki_laki', 'perempuan'])],
            'tempat_lahir' => ['sometimes', 'required', 'string', 'max:100'],
            'tanggal_lahir' => ['sometimes', 'required', 'date'],
            'alamat' => ['sometimes', 'required', 'string'],
            'kelurahan' => ['sometimes', 'nullable', 'string', 'max:100'],
            'kecamatan' => ['sometimes', 'nullable', 'string', 'max:100'],
            'kabupaten' => ['sometimes', 'nullable', 'string', 'max:100'],
            'provinsi' => ['sometimes', 'nullable', 'string', 'max:100'],
            'no_hp' => ['sometimes', 'nullable', 'string', 'max:30'],
            'nama_ayah' => ['sometimes', 'nullable', 'string', 'max:150'],
            'nama_ibu' => ['sometimes', 'nullable', 'string', 'max:150'],
            'pekerjaan_ayah' => ['sometimes', 'nullable', 'string', 'max:100'],
            'pekerjaan_ibu' => ['sometimes', 'nullable', 'string', 'max:100'],
            'penghasilan_bulanan' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'sekolah_asal' => ['sometimes', 'nullable', 'string', 'max:150'],
            'nisn' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
