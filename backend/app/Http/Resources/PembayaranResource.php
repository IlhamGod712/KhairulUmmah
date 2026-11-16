<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\StatusDokumen;
use App\Models\Dokumen;
use App\Models\Pembayaran;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A registration-fee payment, as the wali and admin fee surfaces show it.
 *
 * The kind is not a field the caller can vary: both controllers filter
 * `jenis_pembayaran = pendaftaran` in the query, so an SPP invoice never reaches
 * this resource. SPP has `TagihanSppResource` and its own list, detail, proof,
 * and verdict routes, which is why there is no `periode` or `jatuh_tempo` here.
 *
 * The shape is used by the wali list/detail and the admin list/detail, so the
 * eager-loaded relations decide how much of it is filled in. A barn-door list
 * row needs the student's name, the wali, and the proof status; the detail view
 * carries the same columns plus the contact details the list deliberately omits.
 *
 * `bukti` is the `dokumen` row of `jenis = bukti_pembayaran` for the student,
 * with a `url` to stream the file. The url is role-dependent on purpose: the
 * wali and admin download endpoints are separate routes with separate ownership
 * rules, and the resource builds the one that matches the caller instead of
 * leaking a route the caller could not use.
 *
 * @mixin Pembayaran
 */
class PembayaranResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pendaftaran_id' => $this->pendaftaran_id,
            'jenis_pembayaran' => $this->jenis_pembayaran->value,
            'jenis_pembayaran_label' => $this->jenis_pembayaran->label(),
            'jumlah' => $this->jumlah,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'referensi' => $this->referensi,
            'tanggal_bayar' => $this->tanggal_bayar?->toDateString(),
            'keterangan' => $this->keterangan,
            'metode' => $this->metode?->value,
            'metode_label' => $this->metode?->label(),
            'santri_id' => $this->santri_id,
            'nama_santri' => $this->whenLoaded('santri', fn (): string => $this->santri->nama_lengkap),
            'tahun_ajaran' => $this->whenLoaded('tahunAjaran', fn (): string => $this->tahunAjaran->nama),
            'nomor_pendaftaran' => $this->whenLoaded('pendaftaran', fn (): ?string => $this->pendaftaran?->nomor_pendaftaran),
            'pendaftaran_status' => $this->whenLoaded('pendaftaran', fn (): ?string => $this->pendaftaran?->status->value),
            'pendaftaran_status_label' => $this->whenLoaded('pendaftaran', fn (): ?string => $this->pendaftaran?->status->label()),
            'wali' => $this->whenLoaded('santri', fn (): ?array => $this->wali()),
            'bukti' => $this->bukti($request),
        ];
    }

    /**
     * The guardian of the paying student, or null when not loaded or unlinked.
     *
     * `wali_id` has been nullable since the santri-lama flow (Phase 7), so a
     * student row can legitimately have no guardian. The resource still reports
     * the payment; the contact block is simply absent instead of throwing.
     *
     * @return array{id: int, name: string, email: string, no_hp: string|null}|null
     */
    protected function wali(): ?array
    {
        if (! $this->santri->relationLoaded('wali')) {
            return null;
        }

        $wali = $this->santri->getRelation('wali');

        if (! $wali instanceof User) {
            return null;
        }

        return [
            'id' => $wali->id,
            'name' => $wali->name,
            'email' => $wali->email,
            'no_hp' => $wali->no_hp,
        ];
    }

    /**
     * The uploaded proof and where to fetch it, or null when none exists.
     *
     * @return array{id: int, nama_asli: string, mime_type: string|null, ukuran_bytes: int|null, status: string, status_label: string, catatan: string|null, url: string|null}|null
     */
    protected function bukti(Request $request): ?array
    {
        if (! $this->santri->relationLoaded('buktiPembayaran')) {
            return null;
        }

        $dokumen = $this->santri->buktiPembayaran;

        if (! $dokumen instanceof Dokumen) {
            return null;
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
            'url' => $this->urlBerkas($request),
        ];
    }

    /**
     * Which of the two download routes this caller may use to fetch the proof.
     *
     * The wali and admin stream endpoints are separate, with separate ownership
     * checks, so the resource answers differently per caller and only fills the
     * url when the proof exists at all.
     */
    protected function urlBerkas(Request $request): ?string
    {
        if ($request->routeIs('api.admin.*')) {
            return "/api/admin/pembayaran/{$this->id}/bukti";
        }

        if ($request->routeIs('api.wali.*')) {
            return "/api/wali/pembayaran/{$this->id}/bukti";
        }

        return null;
    }
}
