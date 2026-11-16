<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PengaturanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $item = $this->resource;

        return [
            'kunci' => $item->kunci ?? $item['kunci'] ?? null,
            'nilai' => $item->nilai ?? $item['nilai'] ?? null,
            'tipe' => $item->tipe ?? $item['tipe'] ?? null,
        ];
    }
}
