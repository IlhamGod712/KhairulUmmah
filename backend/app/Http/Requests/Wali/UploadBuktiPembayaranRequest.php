<?php

declare(strict_types=1);

namespace App\Http\Requests\Wali;

use App\Support\MimeTerpercaya;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * The guardian uploading proof that the registration fee was paid.
 *
 * The whitelist is enforced by sniffing the *bytes* of the uploaded file with
 * finfo, not by trusting the client's declared MIME or the filename.
 */
class UploadBuktiPembayaranRequest extends FormRequest
{
    /**
     * Authorization is the `auth:sanctum` + `role:wali` pair on the route, and
     * ownership is checked by the controller against the session's guardian.
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
            'bukti' => [
                'required',
                'file',
                'max:2048',
            ],
            'referensi' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bukti.required' => 'Berkas bukti pembayaran wajib dipilih.',
            'bukti.file' => 'Berkas bukti pembayaran tidak valid.',
            'bukti.max' => 'Berkas bukti pembayaran maksimal 2 MB.',
            'referensi.max' => 'Referensi maksimal 100 karakter.',
        ];
    }

    /**
     * Refuse any file whose real bytes are not one of the three allowed kinds.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $file = $this->file('bukti');

            if (! $file instanceof UploadedFile) {
                return;
            }

            $mime = MimeTerpercaya::of($file);

            if (! MimeTerpercaya::isAllowedForDocuments($mime)) {
                $validator->errors()->add('bukti', 'Berkas bukti pembayaran harus berupa PDF, JPG, atau PNG.');
            }
        });
    }

    /**
     * The MIME type read from the file's content, ignoring what the client claimed.
     */
    public function mimeTerpercaya(): string
    {
        $file = $this->file('bukti');

        if (! $file instanceof UploadedFile) {
            return 'application/octet-stream';
        }

        return MimeTerpercaya::of($file);
    }
}
