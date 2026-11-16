<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotifikasiResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $n = $this->resource;

        return [
            'id' => $n->id,
            'tipe' => $n->tipe,
            'judul' => $n->judul,
            'pesan' => $n->pesan,
            'url' => $n->url,
            'data' => $n->data,
            'dibaca' => $n->dibaca_pada !== null,
            'dibaca_pada' => $n->dibaca_pada ? $n->dibaca_pada->toISOString() : null,
            'created_at' => $n->created_at ? $n->created_at->toISOString() : null,
        ];
    }
}
