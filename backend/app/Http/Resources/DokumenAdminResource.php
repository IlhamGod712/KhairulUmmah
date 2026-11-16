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
class DokumenAdminResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $jenis = $this->jenis;
        $status = $this->status;

        return [
            'id' => $this->id,
            'santri_id' => $this->santri_id,
            'santri' => $this->whenLoaded('santri', fn () => [
                'id' => $this->santri->id,
                'nama_lengkap' => $this->santri->nama_lengkap,
                'nis' => $this->santri->nis,
            ]),
            'jenis' => $jenis instanceof \BackedEnum ? $jenis->value : $jenis,
            'nama' => $jenis instanceof JenisDokumen ? $jenis->label() : null,
            'wajib' => $this->isWajib(),
            'status' => $status instanceof \BackedEnum ? $status->value : $status,
            'status_label' => $status instanceof StatusDokumen ? $status->label() : null,
            'nama_asli' => $this->nama_asli,
            'ukuran' => $this->ukuran,
            'mime' => $this->mime,
            'catatan' => $this->catatan,
            'diverifikasi_pada' => $this->diverifikasi_pada,
            'diverifikasi_oleh' => $this->whenLoaded('diverifikasiOleh', fn () => $this->diverifikasiOleh ? [
                'id' => $this->diverifikasiOleh->id,
                'name' => $this->diverifikasiOleh->name,
            ] : null),
            'uploaded_at' => $this->created_at,
            'berkas_url' => $this->id ? route('admin.dokumen.berkas', $this->id) : null,
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
