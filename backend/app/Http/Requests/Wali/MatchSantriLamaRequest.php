<?php

declare(strict_types=1);

namespace App\Http\Requests\Wali;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The identification a guardian provides to find an existing student.
 *
 * Three fields, all required, all used: the school number (NIS or NISN, the
 * guardian types whichever they know), the full name, and the date of birth.
 * The three together are what pins down one student, so a value that matches a
 * record's number but not its name or birth date finds nothing.
 *
 * Security relevant fields are absent on purpose: there is no `santri_id`, no
 * `wali_id`, and no `user_id` anywhere in this payload. The server identifies
 * the student by re-running the search, and takes the guardian from the
 * session, so a crafted value cannot point at a row the owner did not find
 * through the identification flow.
 *
 * Both endpoints — match and register — validate the same three fields. The
 * register call repeats them so the server can re-derive the student
 * deterministically instead of trusting a handle produced by a previous
 * request.
 */
class MatchSantriLamaRequest extends FormRequest
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
            // NIS is max 30 characters and NISN is exactly 10 digits; the
            // guardian may type either, so the union is bounded by the wider one.
            'nis_atau_nisn' => ['required', 'string', 'max:30'],
            'nama_lengkap' => ['required', 'string', 'min:3', 'max:255'],
            // Same bounds as a new registration: already on file for a person
            // who cannot be born in the future.
            'tanggal_lahir' => ['required', 'date', 'before:today', 'after:1900-01-01'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nis_atau_nisn.required' => 'NIS/NISN wajib diisi.',
            'nis_atau_nisn.max' => 'NIS/NISN maksimal 30 karakter.',
            'nama_lengkap.required' => 'Nama santri wajib diisi.',
            'nama_lengkap.min' => 'Nama santri minimal 3 karakter.',
            'nama_lengkap.max' => 'Nama santri maksimal 255 karakter.',
            'tanggal_lahir.required' => 'Tanggal lahir wajib diisi.',
            'tanggal_lahir.date' => 'Tanggal lahir tidak valid.',
            'tanggal_lahir.before' => 'Tanggal lahir tidak boleh di masa depan.',
            'tanggal_lahir.after' => 'Tanggal lahir tidak valid.',
        ];
    }
}
