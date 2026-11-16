<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Pembayaran;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Pembayaran
 */
class LaporanKeuanganResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tanggal_transaksi' => $this->tanggal_bayar?->toDateString() ?? $this->diverifikasi_pada?->toDateString() ?? $this->created_at?->toDateString(),
            'jenis_pembayaran' => $this->jenis_pembayaran->value,
            'jenis_pembayaran_label' => $this->jenis_pembayaran->label(),
            'periode' => $this->when($this->periode !== null, $this->periode?->format('Y-m')),
            'periode_label' => $this->when($this->periode !== null, fn (): ?string => $this->periodeLabel()),
            'santri_id' => $this->santri_id,
            'nama_santri' => $this->whenLoaded('santri', fn (): string => $this->santri->nama_lengkap),
            'nis' => $this->whenLoaded('santri', fn (): string => $this->santri->nis),
            'jumlah' => $this->jumlah,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'metode' => $this->metode?->value,
            'metode_label' => $this->metode?->label(),
            'keterangan' => $this->keterangan,
            'diverifikasi_pada' => $this->diverifikasi_pada?->toDateTimeString(),
        ];
    }

    private function periodeLabel(): ?string
    {
        if ($this->periode === null) {
            return null;
        }

        return $this->periode->translatedFormat('F Y');
    }
}
