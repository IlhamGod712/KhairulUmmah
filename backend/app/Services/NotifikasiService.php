<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Notifikasi;
use App\Models\User;

class NotifikasiService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function kirim(User $user, string $tipe, string $judul, string $pesan, ?string $url = null, array $data = []): Notifikasi
    {
        $key = $this->kunciDedupe($user, $tipe, $url, $data);
        if ($key) {
            $existing = Notifikasi::query()->where('user_id', $user->id)->where('tipe', $tipe)->where('data->dedupe_key', $key)->whereNull('dibaca_pada')->first();
            if ($existing) {
                return $existing;
            }
        }

        $data['dedupe_key'] = $key;

        return Notifikasi::create([
            'user_id' => $user->id,
            'tipe' => $tipe,
            'judul' => $judul,
            'pesan' => $pesan,
            'url' => $url,
            'data' => $data,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function kunciDedupe(User $user, string $tipe, ?string $url, array $data): ?string
    {
        if (isset($data['dedupe_key'])) {
            return (string) $data['dedupe_key'];
        }
        if (isset($data['pembayaran_id'])) {
            return $tipe.':'.$data['pembayaran_id'];
        }
        if (isset($data['dokumen_id'])) {
            return $tipe.':'.$data['dokumen_id'];
        }
        if (isset($data['pendaftaran_id'])) {
            return $tipe.':'.$data['pendaftaran_id'];
        }
        if (isset($data['santri_id'])) {
            return $tipe.':'.$data['santri_id'];
        }

        return null;
    }
}
