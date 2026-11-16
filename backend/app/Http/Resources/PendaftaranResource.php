<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\StatusPembayaran;
use App\Models\Pendaftaran;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One registration attempt, as it appears in a list.
 *
 * Deliberately lighter than `PendaftaranDetailResource`: a list row carries
 * what a guardian needs to recognise their own submission, and no more. The
 * student's full address, their parents' details, and the guardian's contact
 * information are not repeated on every row of a list that can grow without
 * bound.
 *
 * `ringkasan_pembayaran` is a position, not a verdict on this registration.
 * `pembayarans` has no `jenis` column, so nothing in the schema distinguishes
 * the registration fee from an SPP instalment. Counting every payment row of
 * this student for the registration's academic year is the most the data can
 * honestly support, and it is labelled as that here rather than presented as
 * "the registration fee is paid" — which is a Phase 6 claim, not a Phase 5 one.
 *
 * @mixin Pendaftaran
 */
class PendaftaranResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nomor_pendaftaran' => $this->nomor_pendaftaran,
            'tanggal_daftar' => $this->tanggal_daftar->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'santri_id' => $this->santri_id,
            'nama_santri' => $this->santri->nama_lengkap,
            'jenis_kelamin' => $this->santri->jenis_kelamin->value,
            'santri_status' => $this->santri->status->value,
            'santri_status_label' => $this->santri->status->label(),
            'tahun_ajaran' => $this->whenLoaded('tahunAjaran', fn (): string => $this->tahunAjaran->nama),
            'wali' => $this->wali(),
            'ringkasan_pembayaran' => $this->ringkasanPembayaran(),
        ];
    }

    /**
     * The submitting guardian, identified but not described.
     *
     * The admin list has to answer "who submitted this" without opening every
     * row, so a name belongs here. Contact details do not: they are on the
     * detail resource, and only the detail resource is something a guardian can
     * reach for someone else's submission — which they cannot, but the shape
     * should not imply that an email address travels in a list of any size.
     *
     * A guardian listing their own submissions sees their own name, which is
     * redundant rather than revealing. A single consistent shape across the two
     * list endpoints is worth more than saving those bytes.
     *
     * @return array{id: int, name: string}|null
     */
    protected function wali(): ?array
    {
        if (! $this->santri->relationLoaded('wali')) {
            return null;
        }

        return [
            'id' => $this->santri->wali->id,
            'name' => $this->santri->wali->name,
        ];
    }

    /**
     * Payment position for this student's academic year.
     *
     * Read from the eager loaded `santri.pembayarans`, which keeps the whole
     * page to a constant number of queries instead of one count per row.
     *
     * @return array{total: int, lunas: int, status: string, status_label: string}
     */
    protected function ringkasanPembayaran(): array
    {
        $payments = $this->santri->pembayarans
            ->filter(fn ($pembayaran): bool => $pembayaran->tahun_ajaran_id === $this->tahun_ajaran_id);

        $lunas = $payments->where('status', StatusPembayaran::Lunas)->count();
        $total = $payments->count();
        $status = $total > 0 && $lunas === $total
            ? StatusPembayaran::Lunas
            : StatusPembayaran::Belum;

        return [
            'total' => $total,
            'lunas' => $lunas,
            'status' => $status->value,
            'status_label' => $status->label(),
        ];
    }
}
