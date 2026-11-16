<?php

declare(strict_types=1);

namespace App\Http\Requests\Wali;

use App\Enums\JenisKelamin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The guardian's registration form for a new student.
 *
 * Every field here maps one to one onto a column that already exists on
 * `santris`, and every column a guardian could plausibly supply is either
 * declared below or deliberately left out on purpose. Nothing was invented for
 * this form and no migration was needed: Phase 2 designed the table for exactly
 * this submission.
 *
 * The fields that are *absent* are the security-relevant ones:
 *
 *   - `wali_id` — ownership comes from the session, never from the payload.
 *   - `status` — a new registration is `SantriStatus::Pendaftaran`, never
 *     `aktif`. A client asking for `aktif` gets it dropped, not obeyed.
 *   - `nis` — the school issues this one, so it is generated server side.
 *   - `tahun_ajaran_id` — resolved from the active academic year, so a
 *     guardian cannot file last year's registration.
 *   - `nomor_pendaftaran`, `tanggal_daftar`, `diproses_oleh`, `catatan` —
 *     all assigned by the server or left for Phase 6.
 *
 * The controller assigns attributes one by one and never calls `fill()`, so a
 * field that is missing from `rules()` here cannot reach the model even though
 * `$request->all()` would happily contain it.
 */
class StorePendaftaranRequest extends FormRequest
{
    /**
     * Authorization is the `auth:sanctum` + `role:wali` pair on the route. The
     * acting guardian is always `$request->user()`, so there is no id in this
     * payload that could point at another account.
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
            'nama_lengkap' => ['required', 'string', 'min:3', 'max:255'],
            // Ten digits, per the column comment on `santris.nisn`. Unique
            // school-wide, which is what makes it usable as a natural key for
            // "is this student already on file".
            'nisn' => ['nullable', 'digits:10', Rule::unique('santris', 'nisn')],
            'jenis_kelamin' => ['required', Rule::in(array_column(JenisKelamin::cases(), 'value'))],
            'tempat_lahir' => ['required', 'string', 'max:255'],
            // A student cannot be born in the future, and the lower bound is
            // there to catch a typo rather than to express an age policy. The
            // minimum and maximum age for admission is an administrative
            // decision, not something to bury in a validation rule.
            'tanggal_lahir' => ['required', 'date', 'before:today', 'after:1900-01-01'],

            'alamat' => ['required', 'string', 'max:2000'],
            'kelurahan' => ['nullable', 'string', 'max:255'],
            'kecamatan' => ['nullable', 'string', 'max:255'],
            'kabupaten' => ['nullable', 'string', 'max:255'],
            'provinsi' => ['nullable', 'string', 'max:255'],
            'no_hp' => ['nullable', 'string', 'max:25', 'regex:/^[0-9+\-\s()]+$/'],

            'nama_ayah' => ['nullable', 'string', 'max:255'],
            'nama_ibu' => ['nullable', 'string', 'max:255'],
            'pekerjaan_ayah' => ['nullable', 'string', 'max:255'],
            'pekerjaan_ibu' => ['nullable', 'string', 'max:255'],
            'penghasilan_bulanan' => ['nullable', 'integer', 'min:0'],
            'sekolah_asal' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama_lengkap.required' => 'Nama lengkap calon santri wajib diisi.',
            'nama_lengkap.min' => 'Nama lengkap minimal 3 karakter.',
            'nama_lengkap.max' => 'Nama lengkap maksimal 255 karakter.',
            'nisn.digits' => 'NISN harus terdiri dari 10 angka.',
            'nisn.unique' => 'NISN ini sudah terdaftar. Gunakan NISN yang berbeda atau kosongkan bila belum punya.',
            'jenis_kelamin.required' => 'Jenis kelamin wajib dipilih.',
            'jenis_kelamin.in' => 'Jenis kelamin tidak valid.',
            'tempat_lahir.required' => 'Tempat lahir wajib diisi.',
            'tanggal_lahir.required' => 'Tanggal lahir wajib diisi.',
            'tanggal_lahir.date' => 'Tanggal lahir tidak valid.',
            'tanggal_lahir.before' => 'Tanggal lahir tidak boleh di masa depan.',
            'tanggal_lahir.after' => 'Tanggal lahir tidak valid.',
            'alamat.required' => 'Alamat wajib diisi.',
            'alamat.max' => 'Alamat maksimal 2000 karakter.',
            'no_hp.max' => 'Nomor HP maksimal 25 karakter.',
            'no_hp.regex' => 'Nomor HP hanya boleh berisi angka, spasi, dan tanda + - ( ).',
            'penghasilan_bulanan.integer' => 'Penghasilan bulanan harus berupa angka.',
            'penghasilan_bulanan.min' => 'Penghasilan bulanan tidak boleh negatif.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nama_lengkap' => 'nama lengkap calon santri',
            'nisn' => 'NISN',
            'jenis_kelamin' => 'jenis kelamin',
            'tempat_lahir' => 'tempat lahir',
            'tanggal_lahir' => 'tanggal lahir',
            'alamat' => 'alamat',
            'no_hp' => 'nomor HP',
            'nama_ayah' => 'nama ayah',
            'nama_ibu' => 'nama ibu',
            'pekerjaan_ayah' => 'pekerjaan ayah',
            'pekerjaan_ibu' => 'pekerjaan ibu',
            'penghasilan_bulanan' => 'penghasilan bulanan',
            'sekolah_asal' => 'sekolah asal',
        ];
    }

    /**
     * The student columns a guardian is allowed to set, copied out of the
     * validated payload by the controller one attribute at a time.
     *
     * @return array<int, string>
     */
    public function kolomSantri(): array
    {
        return [
            'nama_lengkap',
            'nisn',
            'jenis_kelamin',
            'tempat_lahir',
            'tanggal_lahir',
            'alamat',
            'kelurahan',
            'kecamatan',
            'kabupaten',
            'provinsi',
            'no_hp',
            'nama_ayah',
            'nama_ibu',
            'pekerjaan_ayah',
            'pekerjaan_ibu',
            'penghasilan_bulanan',
            'sekolah_asal',
        ];
    }
}
