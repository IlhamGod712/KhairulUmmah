import { useCallback, useState, type FormEvent } from 'react';

import { FormField } from '@/components/FormField';
import { SelectField } from '@/components/SelectField';
import { TextAreaField } from '@/components/TextAreaField';
import { describeError, fieldErrors, waliApi } from '@/lib/api';
import { JENIS_KELAMIN_LABELS, type JenisKelamin } from '@/types/auth';
import type { PendaftaranDetail, PendaftaranPayload } from '@/types/pendaftaran';

/**
 * The form's own value type: every field as the raw text an input holds.
 *
 * A mapped type rather than a hand-written list, so a field added to
 * `PendaftaranPayload` cannot be forgotten here — the compiler will report it as
 * missing from `KOSONG` instead of the form silently dropping the value. It
 * also removes the need to lie about `jenis_kelamin` being a `JenisKelamin`
 * before the guardian has picked one: in the form it is just text, and
 * `kePayload` is the single place that narrows and parses the lot.
 */
type FormState = { [K in keyof PendaftaranPayload]: string };

/**
 * Empty state of the form. Every optional column starts as an empty string,
 * which `kePayload` turns into `null` — the backend trims and nulls blanks, so
 * the difference is settled in one place rather than in seventeen inputs.
 *
 * Nothing is blocked client-side before submitting: the backend's validation
 * rules are the authority on what is missing, and a 422 that names the offending
 * field is a better answer than a button that silently does nothing.
 */
const KOSONG: FormState = {
    nama_lengkap: '',
    nisn: '',
    jenis_kelamin: '',
    tempat_lahir: '',
    tanggal_lahir: '',
    alamat: '',
    kelurahan: '',
    kecamatan: '',
    kabupaten: '',
    provinsi: '',
    no_hp: '',
    nama_ayah: '',
    nama_ibu: '',
    pekerjaan_ayah: '',
    pekerjaan_ibu: '',
    penghasilan_bulanan: '',
    sekolah_asal: '',
};

/**
 * The one place raw form text becomes the API's payload.
 *
 * Blanks become `null` because the API is explicit about nullability, and a
 * column that means "not given" should not be told it is the empty string. The
 * text is trimmed on the way through, so a field of nothing but spaces does not
 * reach validation looking like a value.
 *
 * `jenis_kelamin` is passed through unchecked: an unset select sends `''`, and
 * `required` on the server answers with a 422 that names the field. Guessing a
 * default here would be inventing data.
 */
function kePayload(nilai: FormState): PendaftaranPayload {
    const kosongkan = (teks: string): string | null => (teks.trim() === '' ? null : teks.trim());

    return {
        nama_lengkap: nilai.nama_lengkap.trim(),
        jenis_kelamin: nilai.jenis_kelamin as JenisKelamin,
        tempat_lahir: nilai.tempat_lahir.trim(),
        tanggal_lahir: nilai.tanggal_lahir,
        alamat: nilai.alamat.trim(),
        nisn: kosongkan(nilai.nisn),
        no_hp: kosongkan(nilai.no_hp),
        kelurahan: kosongkan(nilai.kelurahan),
        kecamatan: kosongkan(nilai.kecamatan),
        kabupaten: kosongkan(nilai.kabupaten),
        provinsi: kosongkan(nilai.provinsi),
        nama_ayah: kosongkan(nilai.nama_ayah),
        nama_ibu: kosongkan(nilai.nama_ibu),
        pekerjaan_ayah: kosongkan(nilai.pekerjaan_ayah),
        pekerjaan_ibu: kosongkan(nilai.pekerjaan_ibu),
        sekolah_asal: kosongkan(nilai.sekolah_asal),
        penghasilan_bulanan:
            nilai.penghasilan_bulanan.trim() === '' ? null : Number(nilai.penghasilan_bulanan),
    };
}

/** Per-field message from the last 422, or undefined when the field is fine. */
type Errors = Record<string, string[]>;

function pesan(errors: Errors, nama: string): string | undefined {
    return errors[nama]?.[0];
}

interface PendaftaranFormProps {
    /**
     * Called with the registration the server created. The whole object, not
     * just its id: the response already carries the assigned registration
     * number and both statuses, so the caller can show the guardian their actual
     * receipt instead of a success message with a placeholder where the number
     * should be.
     */
    onSuccess: (pendaftaran: PendaftaranDetail) => void;
}

export function PendaftaranForm({ onSuccess }: PendaftaranFormProps) {
    const [nilai, setNilai] = useState<FormState>(KOSONG);
    const [errors, setErrors] = useState<Errors>({});
    const [error, setError] = useState<string | null>(null);
    const [sedangMengirim, setSedangMengirim] = useState(false);

    const ubah = useCallback((nama: keyof FormState) => (e: { target: { value: string } }) => {
        setNilai((sebelumnya) => ({ ...sebelumnya, [nama]: e.target.value }));
    }, []);

    const kirim = useCallback(
        async (event: FormEvent<HTMLFormElement>) => {
            event.preventDefault();

            // The button is already disabled, but a keyboard repeat or a
            // scripted second call would still land here, and the server's own
            // duplicate check would answer with a 422 the guardian cannot act
            // on. Refusing early is kinder than surfacing that.
            if (sedangMengirim) {
                return;
            }

            setSedangMengirim(true);
            setError(null);
            setErrors({});

            try {
                const pendaftaran = await waliApi.daftarPendaftaran(kePayload(nilai));
                setNilai(KOSONG);
                onSuccess(pendaftaran);
            } catch (cause: unknown) {
                setErrors(fieldErrors(cause));
                setError(describeError(cause));
            } finally {
                setSedangMengirim(false);
            }
        },
        [nilai, sedangMengirim, onSuccess],
    );

    const dinonaktifkan = sedangMengirim;

    return (
        <form onSubmit={kirim} noValidate className="space-y-6">
            {error !== null && (
                <div role="alert" className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                    {error}
                </div>
            )}

            <fieldset disabled={dinonaktifkan} className="space-y-6">
                <legend className="text-sm font-semibold text-slate-800">Identitas calon Santri</legend>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="sm:col-span-2">
                        <FormField
                            label="Nama lengkap"
                            name="nama_lengkap"
                            value={nilai.nama_lengkap}
                            onChange={ubah('nama_lengkap')}
                            error={pesan(errors, 'nama_lengkap')}
                            required
                            autoComplete="off"
                        />
                    </div>
                    <FormField
                        label="NISN"
                        name="nisn"
                        value={nilai.nisn}
                        onChange={ubah('nisn')}
                        error={pesan(errors, 'nisn')}
                        hint="10 angka. Kosongkan bila belum memiliki NISN."
                        inputMode="numeric"
                        maxLength={10}
                    />
                    <SelectField
                        label="Jenis kelamin"
                        name="jenis_kelamin"
                        value={nilai.jenis_kelamin}
                        onChange={ubah('jenis_kelamin')}
                        error={pesan(errors, 'jenis_kelamin')}
                        placeholder="Pilih jenis kelamin"
                        required
                    >
                        {Object.entries(JENIS_KELAMIN_LABELS).map(([nilaiEnum, label]) => (
                            <option key={nilaiEnum} value={nilaiEnum}>
                                {label}
                            </option>
                        ))}
                    </SelectField>
                    <FormField
                        label="Tempat lahir"
                        name="tempat_lahir"
                        value={nilai.tempat_lahir}
                        onChange={ubah('tempat_lahir')}
                        error={pesan(errors, 'tempat_lahir')}
                        required
                    />
                    <FormField
                        label="Tanggal lahir"
                        name="tanggal_lahir"
                        type="date"
                        value={nilai.tanggal_lahir}
                        onChange={ubah('tanggal_lahir')}
                        error={pesan(errors, 'tanggal_lahir')}
                        required
                    />
                </div>

                <legend className="text-sm font-semibold text-slate-800">Alamat</legend>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="sm:col-span-2">
                        <TextAreaField
                            label="Alamat"
                            name="alamat"
                            rows={3}
                            value={nilai.alamat}
                            onChange={ubah('alamat')}
                            error={pesan(errors, 'alamat')}
                            hint="Tulis nama jalan, nomor, RT/RW, dan desa atau kelurahan."
                            required
                        />
                    </div>
                    <FormField
                        label="Kelurahan / Desa"
                        name="kelurahan"
                        value={nilai.kelurahan}
                        onChange={ubah('kelurahan')}
                        error={pesan(errors, 'kelurahan')}
                    />
                    <FormField
                        label="Kecamatan"
                        name="kecamatan"
                        value={nilai.kecamatan}
                        onChange={ubah('kecamatan')}
                        error={pesan(errors, 'kecamatan')}
                    />
                    <FormField
                        label="Kabupaten / Kota"
                        name="kabupaten"
                        value={nilai.kabupaten}
                        onChange={ubah('kabupaten')}
                        error={pesan(errors, 'kabupaten')}
                    />
                    <FormField
                        label="Provinsi"
                        name="provinsi"
                        value={nilai.provinsi}
                        onChange={ubah('provinsi')}
                        error={pesan(errors, 'provinsi')}
                    />
                    <FormField
                        label="Nomor HP"
                        name="no_hp"
                        value={nilai.no_hp}
                        onChange={ubah('no_hp')}
                        error={pesan(errors, 'no_hp')}
                        inputMode="tel"
                    />
                </div>

                <legend className="text-sm font-semibold text-slate-800">
                    Data orang tua <span className="font-normal text-slate-500">(boleh dikosongkan)</span>
                </legend>
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="Nama ayah"
                        name="nama_ayah"
                        value={nilai.nama_ayah}
                        onChange={ubah('nama_ayah')}
                        error={pesan(errors, 'nama_ayah')}
                    />
                    <FormField
                        label="Pekerjaan ayah"
                        name="pekerjaan_ayah"
                        value={nilai.pekerjaan_ayah}
                        onChange={ubah('pekerjaan_ayah')}
                        error={pesan(errors, 'pekerjaan_ayah')}
                    />
                    <FormField
                        label="Nama ibu"
                        name="nama_ibu"
                        value={nilai.nama_ibu}
                        onChange={ubah('nama_ibu')}
                        error={pesan(errors, 'nama_ibu')}
                    />
                    <FormField
                        label="Pekerjaan ibu"
                        name="pekerjaan_ibu"
                        value={nilai.pekerjaan_ibu}
                        onChange={ubah('pekerjaan_ibu')}
                        error={pesan(errors, 'pekerjaan_ibu')}
                    />
                    <FormField
                        label="Penghasilan bulanan (Rp)"
                        name="penghasilan_bulanan"
                        value={nilai.penghasilan_bulanan}
                        onChange={ubah('penghasilan_bulanan')}
                        error={pesan(errors, 'penghasilan_bulanan')}
                        inputMode="numeric"
                    />
                    <FormField
                        label="Sekolah asal"
                        name="sekolah_asal"
                        value={nilai.sekolah_asal}
                        onChange={ubah('sekolah_asal')}
                        error={pesan(errors, 'sekolah_asal')}
                    />
                </div>
            </fieldset>

            <div className="flex flex-col gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-xs leading-relaxed text-slate-500">
                    Setelah dikirim, pendaftaran berstatus <strong>Diajukan</strong> dan menunggu verifikasi admin /
                    tata usaha. Pendaftaran yang diajukan belum menjadikannya Santri Aktif.
                </p>
                <button
                    type="submit"
                    disabled={dinonaktifkan}
                    className="shrink-0 rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:bg-slate-300"
                >
                    {sedangMengirim ? 'Mengirim…' : 'Kirim Pendaftaran'}
                </button>
            </div>
        </form>
    );
}
