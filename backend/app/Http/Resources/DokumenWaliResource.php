<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\JenisDokumen;
use App\Enums\StatusDokumen;
use App\Models\Dokumen;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Dokumen
 */
class DokumenWaliResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $jenis = $this->jenis;
        $nama = $jenis instanceof JenisDokumen ? $jenis->label() : $jenis;

        return [
            'id' => $this->id,
            'jenis' => $jenis instanceof \BackedEnum ? $jenis->value : $jenis,
            'nama' => $nama,
            'wajib' => $this->isWajib(),
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'status_label' => $this->status instanceof StatusDokumen ? $this->status->label() : null,
            'nama_asli' => $this->nama_asli,
            'ukuran' => $this->ukuran,
            'mime' => $this->mime,
            'catatan' => $this->catatan,
            'diverifikasi_pada' => $this->diverifikasi_pada,
            'diverifikasi_oleh' => $this->whenLoaded('diverifikasiOleh', fn () => [
                'id' => $this->diverifikasiOleh->id,
                'name' => $this->diverifikasiOleh->name,
            ]),
            'uploaded_at' => $this->created_at,
            'berkas_url' => null,
        ];
    }

    private function isWajib(): bool
    {
        $jenis = $this->jenis;
        if ($jenis instanceof JenisDokumen) {
            return $jenis->isRequired();
        }
        $e = JenisDokumen::tryFrom($jenis);

        return $e?->isRequired() ?? false;
    }
}
