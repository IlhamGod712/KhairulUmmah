<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\JenisPembayaran;
use App\Models\Pembayaran;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A monthly SPP invoice as it appears on the wali and admin SPP surfaces.
 *
 * The invoice is a `pembayarans` row (`jenis_pembayaran = 'spp'` with a
 * `periode`), not a second kind of row: Phase 6 already made `jenis_pembayaran`
 * the discriminator, and reusing it is what keeps "one table of money" true.
 * What this resource adds over `PembayaranResource` is the invoice's own
 * dimensions — the billed month, the due date, the verdict, and the proof that
 * belongs to *this* invoice rather than to the student.
 *
 * `bukti` is deliberately not read from the student's `bukti_pembayaran`
 * document. A student has exactly one of those and it belongs to the
 * registration fee; Juli's proof and Agustus's proof cannot share a column, so
 * each invoice carries its own. See the Phase 8 migration for why that column
 * lives here rather than in `dokumen`.
 *
 * @mixin Pembayaran
 */
class TagihanSppResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'jenis_pembayaran' => JenisPembayaran::Spp->value,
            'jenis_pembayaran_label' => JenisPembayaran::Spp->label(),

            // Both the machine value and the printed month travel together, so a
            // reader can never confuse a July in one academic year with a July
            // in the next.
            'periode' => $this->periode?->toDateString(),
            'periode_label' => $this->periodeLabel(),
            'jatuh_tempo' => $this->jatuh_tempo?->toDateString(),

            'jumlah' => $this->jumlah,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'metode' => $this->metode?->value,
            'metode_label' => $this->metode?->label(),
            'tanggal_bayar' => $this->tanggal_bayar?->toDateString(),
            'referensi' => $this->referensi,
            'keterangan' => $this->keterangan,
            'diverifikasi_pada' => $this->diverifikasi_pada?->toDateString(),

            'santri_id' => $this->santri_id,
            'nama_santri' => $this->whenLoaded('santri', fn (): string => $this->santri->nama_lengkap),
            // `nis` is a non-null unique column, unlike `nisn` which is nullable,
            // so this one is always a string.
            'nis' => $this->whenLoaded('santri', fn (): string => $this->santri->nis),
            'status_santri' => $this->whenLoaded('santri', fn (): string => $this->santri->status->value),
            'status_santri_label' => $this->whenLoaded('santri', fn (): string => $this->santri->status->label()),
            'tahun_ajaran_id' => $this->tahun_ajaran_id,
            'tahun_ajaran' => $this->whenLoaded('tahunAjaran', fn (): string => $this->tahunAjaran->nama),

            'wali' => $this->whenLoaded('santri', fn (): ?array => $this->wali()),
            'bukti' => $this->bukti($request),
        ];
    }

    /**
     * The guardian of the billed student, or null when not loaded.
     *
     * Only ever filled for an admin caller: the wali list is already scoped to
     * the session's own students, so naming a guardian back to them is noise.
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
     * This invoice's own proof, and where to stream it.
     *
     * @return array{nama_asli: string, mime_type: string|null, ukuran_bytes: int|null, url: string|null}|null
     */
    protected function bukti(Request $request): ?array
    {
        $path = $this->bukti_path;

        if (! is_string($path) || $path === '') {
            return null;
        }

        return [
            'nama_asli' => $this->bukti_nama_asli,
            'mime_type' => $this->bukti_mime_type,
            'ukuran_bytes' => $this->bukti_ukuran_bytes,
            'url' => $this->urlBerkas($request),
        ];
    }

    /**
     * Which of the two download routes this caller may use to fetch the proof.
     *
     * The wali and admin stream endpoints are separate, with separate ownership
     * checks, so the resource answers per caller and only fills the url when
     * the caller is on one of the two SPP surfaces.
     */
    protected function urlBerkas(Request $request): ?string
    {
        if ($request->routeIs('api.admin.spp.*')) {
            return "/api/admin/spp/{$this->id}/bukti";
        }

        if ($request->routeIs('api.wali.spp.*')) {
            return "/api/wali/spp/{$this->id}/bukti";
        }

        return null;
    }
}
