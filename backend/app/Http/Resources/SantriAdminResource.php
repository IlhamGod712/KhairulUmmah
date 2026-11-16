<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SantriAdminResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $santri = $this->resource;

        return [
            'id' => $santri->id,
            'nis' => $santri->nis,
            'nisn' => $santri->nisn,
            'nama_lengkap' => $santri->nama_lengkap,
            'jenis_kelamin' => $santri->jenis_kelamin instanceof \BackedEnum ? $santri->jenis_kelamin->value : $santri->jenis_kelamin,
            'jenis_kelamin_label' => ($santri->jenis_kelamin instanceof \BackedEnum && method_exists($santri->jenis_kelamin, 'label')) ? $santri->jenis_kelamin->label() : null,
            'tempat_lahir' => $santri->tempat_lahir,
            'tanggal_lahir' => $santri->tanggal_lahir ? (is_object($santri->tanggal_lahir) && method_exists($santri->tanggal_lahir, 'format') ? $santri->tanggal_lahir->format('Y-m-d') : (string) $santri->tanggal_lahir) : null,
            'alamat' => $santri->alamat,
            'kelurahan' => $santri->kelurahan,
            'kecamatan' => $santri->kecamatan,
            'kabupaten' => $santri->kabupaten,
            'provinsi' => $santri->provinsi,
            'no_hp' => $santri->no_hp,
            'nama_ayah' => $santri->nama_ayah,
            'nama_ibu' => $santri->nama_ibu,
            'pekerjaan_ayah' => $santri->pekerjaan_ayah,
            'pekerjaan_ibu' => $santri->pekerjaan_ibu,
            'penghasilan_bulanan' => $santri->penghasilan_bulanan,
            'sekolah_asal' => $santri->sekolah_asal,
            'foto_path' => $santri->foto_path,
            'status' => $santri->status instanceof \BackedEnum ? $santri->status->value : $santri->status,
            'status_label' => ($santri->status instanceof \BackedEnum && method_exists($santri->status, 'label')) ? $santri->status->label() : null,
            'wali_id' => $santri->wali_id,
            'tahun_ajaran_id' => $santri->tahun_ajaran_id,
            'wali' => ($santri->relationLoaded('wali') && $santri->wali) ? [
                'id' => $santri->wali->id,
                'name' => $santri->wali->name,
                'email' => $santri->wali->email,
                'no_hp' => $santri->wali->no_hp,
            ] : null,
            'tahun_ajaran' => ($santri->relationLoaded('tahunAjaran') && $santri->tahunAjaran) ? [
                'id' => $santri->tahunAjaran->id,
                'nama' => $santri->tahunAjaran->nama,
                'aktif' => $santri->tahunAjaran->aktif,
            ] : null,
            'created_at' => ($santri->created_at && is_object($santri->created_at) && method_exists($santri->created_at, 'toISOString')) ? $santri->created_at->toISOString() : null,
            'updated_at' => ($santri->updated_at && is_object($santri->updated_at) && method_exists($santri->updated_at, 'toISOString')) ? $santri->updated_at->toISOString() : null,
        ];
    }
}
