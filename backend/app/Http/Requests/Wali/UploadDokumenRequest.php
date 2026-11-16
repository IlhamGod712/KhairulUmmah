<?php

declare(strict_types=1);

namespace App\Http\Requests\Wali;

use App\Enums\JenisDokumen;
use App\Support\MimeTerpercaya;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class UploadDokumenRequest extends FormRequest
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
            'berkas' => ['required', 'file', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'berkas.required' => 'Berkas dokumen wajib dipilih.',
            'berkas.file' => 'Berkas dokumen tidak valid.',
            'berkas.max' => 'Berkas dokumen maksimal 2 MB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $file = $this->file('berkas');

            if (! $file instanceof UploadedFile) {
                return;
            }

            $mime = MimeTerpercaya::of($file);

            if (! MimeTerpercaya::isAllowedForDocuments($mime)) {
                $validator->errors()->add('berkas', 'Berkas harus berupa PDF, JPG, atau PNG.');
            }
        });
    }

    public function jenis(): ?JenisDokumen
    {
        $jenis = $this->route('jenis');

        if (! is_string($jenis)) {
            return null;
        }

        return JenisDokumen::tryFrom($jenis);
    }

    public function mimeTerpercaya(): string
    {
        $file = $this->file('berkas');

        if (! $file instanceof UploadedFile) {
            return 'application/octet-stream';
        }

        return MimeTerpercaya::of($file);
    }
}
