<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\JenisPembayaran;
use App\Enums\StatusDokumen;
use App\Models\Dokumen;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use Illuminate\Http\Request;

/**
 * One registration attempt, in full, for a single view.
 *
 * Extends the list shape rather than duplicating it, so a field added to
 * `PendaftaranResource` cannot appear here with a different meaning.
 *
 * Two blocks are added on top of the list row:
 *
 *   - `calon_santri` — everything the guardian supplied, so they can read back
 *     what was actually recorded. This is their own child's data on their own
 *     submission, and the endpoint has already refused anyone else's.
 *   - `wali` — shown on the admin surface only, because a guardian already
 *     knows who they are and reading it back adds nothing.
 *
 * Phase 6 adds the verdict the schema was missing: `biaya_pendaftaran` is the
 * registration-fee payment for *this* registration (unlike the list's
 * `ringkasan_pembayaran`, which is a position over all of the student's
 * payments because it cannot tell fees apart). It is the honest "is the fee
 * paid" answer, paired with `alasan_ditolak` so a guardian reads back the
 * rejection reason in the same view.
 *
 * @mixin Pendaftaran
 */
class PendaftaranDetailResource extends PendaftaranResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'catatan' => $this->catatan,
            'alasan_ditolak' => $this->alasan_ditolak,
            'diproses_oleh' => $this->whenLoaded('diprosesOleh', fn (): ?string => $this->diprosesOleh?->name),
            'biaya_pendaftaran' => $this->biayaPendaftaran($request),
            'calon_santri' => [
                'id' => $this->santri->id,
                'nis' => $this->santri->nis,
                'nisn' => $this->santri->nisn,
                'nama_lengkap' => $this->santri->nama_lengkap,
                'jenis_kelamin' => $this->santri->jenis_kelamin->value,
                'jenis_kelamin_label' => $this->santri->jenis_kelamin->label(),
                'tempat_lahir' => $this->santri->tempat_lahir,
                'tanggal_lahir' => $this->santri->tanggal_lahir->toDateString(),
                'alamat' => $this->santri->alamat,
                'kelurahan' => $this->santri->kelurahan,
                'kecamatan' => $this->santri->kecamatan,
                'kabupaten' => $this->santri->kabupaten,
                'provinsi' => $this->santri->provinsi,
                'no_hp' => $this->santri->no_hp,
                'nama_ayah' => $this->santri->nama_ayah,
                'nama_ibu' => $this->santri->nama_ibu,
                'pekerjaan_ayah' => $this->santri->pekerjaan_ayah,
                'pekerjaan_ibu' => $this->santri->pekerjaan_ibu,
                'penghasilan_bulanan' => $this->santri->penghasilan_bulanan,
                'sekolah_asal' => $this->santri->sekolah_asal,
                'status' => $this->santri->status->value,
                'status_label' => $this->santri->status->label(),
            ],
            'wali' => $this->wali(),
        ];
    }

    /**
     * The submitting guardian, or null when the relation was not loaded.
     *
     * `relationLoaded()` takes a single relation name and does not understand
     * `santri.wali` — the key in the relations array is `santri`, not
     * `santri.wali` — so `whenLoaded('santri.wali')` would quietly answer false
     * forever and this field would always be missing. Asking the nested model
     * instead is the check that actually describes the situation.
     *
     * @return array{id: int, name: string, email: string, no_hp: string|null}|null
     */
    protected function wali(): ?array
    {
        if (! $this->santri->relationLoaded('wali')) {
            return null;
        }

        return [
            'id' => $this->santri->wali->id,
            'name' => $this->santri->wali->name,
            'email' => $this->santri->wali->email,
            'no_hp' => $this->santri->wali->no_hp,
        ];
    }

    /**
     * The registration-fee payment for this registration, and its proof.
     *
     * Unlike `ringkasanPembayaran()` on the list resource, this answers the
     * question the Phase 6 schema can now ask honestly: it looks for the single
     * `pendaftaran`-typed payment that points at this registration, rather than
     * counting every payment of the student's year. Null before the fee exists
     * (i.e. while the registration is undecided); the verdict on the fee once
     * the admin has opened the payment lane.
     *
     * @return array{id: int, jumlah: string, status: string, status_label: string, bukti: array<string, mixed>|null}|null
     */
    protected function biayaPendaftaran(Request $request): ?array
    {
        $fee = $this->santri->pembayarans
            ->first(fn ($pembayaran): bool => $pembayaran->jenis_pembayaran === JenisPembayaran::Pendaftaran
                && $pembayaran->tahun_ajaran_id === $this->tahun_ajaran_id);

        if (! $fee instanceof Pembayaran) {
            return null;
        }

        return [
            'id' => $fee->id,
            'jumlah' => $fee->jumlah,
            'status' => $fee->status->value,
            'status_label' => $fee->status->label(),
            'bukti' => $this->bukti($request, $fee),
        ];
    }

    /**
     * The uploaded proof for this registration, and where to fetch it.
     *
     * @return array{id: int, nama_asli: string, mime_type: string|null, ukuran_bytes: int|null, status: string, status_label: string, catatan: string|null, url: string|null}|null
     */
    protected function bukti(Request $request, Pembayaran $fee): ?array
    {
        $dokumen = $this->santri->buktiPembayaran;

        if (! $dokumen instanceof Dokumen) {
            return null;
        }

        $url = null;

        if ($request->routeIs('api.admin.*')) {
            $url = "/api/admin/pembayaran/{$fee->id}/bukti";
        } elseif ($request->routeIs('api.wali.*')) {
            $url = "/api/wali/pembayaran/{$fee->id}/bukti";
        }

        $status = $dokumen->status;
        $statusValue = $status instanceof StatusDokumen ? $status->value : $status;
        $statusLabel = $status instanceof StatusDokumen ? $status->label() : null;

        return [
            'id' => $dokumen->id,
            'nama_asli' => $dokumen->nama_asli,
            'mime_type' => $dokumen->mime_type,
            'ukuran_bytes' => $dokumen->ukuran_bytes,
            'status' => $statusValue,
            'status_label' => $statusLabel,
            'catatan' => $dokumen->catatan,
            'url' => $url,
        ];
    }
}
