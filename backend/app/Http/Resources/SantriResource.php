<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Santri;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A student, as seen by their own wali.
 *
 * Deliberately a read-only projection of the identifying fields a guardian
 * already knows. Administrative detail — registration history, payments,
 * document review — belongs to the admin surface and is not exposed here.
 *
 * @mixin Santri
 */
class SantriResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nis' => $this->nis,
            'nisn' => $this->nisn,
            'nama_lengkap' => $this->nama_lengkap,
            'jenis_kelamin' => $this->jenis_kelamin->value,
            'tempat_lahir' => $this->tempat_lahir,
            'tanggal_lahir' => $this->tanggal_lahir->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'tahun_ajaran' => $this->whenLoaded(
                'tahunAjaran',
                fn (): string => $this->tahunAjaran->nama,
            ),
        ];
    }
}
