<?php

namespace Database\Seeders;

use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class TahunAjaranSeeder extends Seeder
{
    /**
     * Seed three academic years, with the middle one marked active.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $tahun = [
            [
                'nama' => ($now->year - 1).'/'.$now->year,
                'tanggal_mulai' => $now->copy()->subYear()->startOfYear(),
                'tanggal_selesai' => $now->copy()->subYear()->endOfYear(),
                'is_active' => false,
            ],
            [
                'nama' => $now->year.'/'.($now->year + 1),
                'tanggal_mulai' => $now->copy()->startOfYear(),
                'tanggal_selesai' => $now->copy()->endOfYear(),
                'is_active' => true,
            ],
            [
                'nama' => ($now->year + 1).'/'.($now->year + 2),
                'tanggal_mulai' => $now->copy()->addYear()->startOfYear(),
                'tanggal_selesai' => $now->copy()->addYear()->endOfYear(),
                'is_active' => false,
            ],
        ];

        foreach ($tahun as $data) {
            TahunAjaran::query()->updateOrCreate(
                ['nama' => $data['nama']],
                $data,
            );
        }
    }
}
