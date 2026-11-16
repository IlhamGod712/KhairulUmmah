<?php

namespace Database\Seeders;

use App\Enums\JenisKelamin;
use App\Enums\SantriStatus;
use App\Models\Santri;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Seeder;

class SantriSeeder extends Seeder
{
    /**
     * Create one wali with two students and a second wali with one, so the
     * wali -> students relationship is visible in a fresh install.
     */
    public function run(): void
    {
        $tahunAjaran = TahunAjaran::query()
            ->where('is_active', true)
            ->firstOrFail();

        $waliPertama = User::query()->firstOrCreate(
            ['email' => 'wali.ahmad@example.com'],
            ['name' => 'Ahmad Fauzi', 'password' => 'password', 'role' => 'wali'],
        );

        $waliKedua = User::query()->firstOrCreate(
            ['email' => 'wali.siti@example.com'],
            ['name' => 'Siti Aminah', 'password' => 'password', 'role' => 'wali'],
        );

        $data = [
            [
                'wali' => $waliPertama,
                'nis' => '260001',
                'nisn' => '31512026001',
                'nama_lengkap' => 'Rizky Maulana',
                'jenis_kelamin' => JenisKelamin::LakiLaki,
                'tempat_lahir' => 'Bandung',
                'tanggal_lahir' => '2013-05-17',
                'alamat' => 'Jl. Melati No. 12, RT 02 RW 05',
                'kelurahan' => 'Cibeunying Kidul',
                'kecamatan' => 'Cibeunying Kidul',
                'kabupaten' => 'Bandung',
                'provinsi' => 'Jawa Barat',
                'no_hp' => '081200000001',
                'nama_ayah' => 'Ahmad Fauzi',
                'nama_ibu' => 'Dewi Lestari',
                'pekerjaan_ayah' => 'Petani',
                'pekerjaan_ibu' => 'Ibu Rumah Tangga',
                'penghasilan_bulanan' => 2500000,
                'sekolah_asal' => 'SDN Cibaduyut 01',
                'status' => SantriStatus::Aktif,
            ],
            [
                'wali' => $waliPertama,
                'nis' => '260002',
                'nisn' => '31512026002',
                'nama_lengkap' => 'Nabila Putri',
                'jenis_kelamin' => JenisKelamin::Perempuan,
                'tempat_lahir' => 'Sumedang',
                'tanggal_lahir' => '2015-09-03',
                'alamat' => 'Kp. Gedebage RT 01 RW 02',
                'kelurahan' => 'Cibaduyut',
                'kecamatan' => 'Astanaanyar',
                'kabupaten' => 'Bandung',
                'provinsi' => 'Jawa Barat',
                'no_hp' => '081200000002',
                'nama_ayah' => 'Ahmad Fauzi',
                'nama_ibu' => 'Dewi Lestari',
                'pekerjaan_ayah' => 'Petani',
                'pekerjaan_ibu' => 'Ibu Rumah Tangga',
                'penghasilan_bulanan' => 2500000,
                'sekolah_asal' => 'SDN Cibaduyut 01',
                'status' => SantriStatus::MenungguPembayaran,
            ],
            [
                'wali' => $waliKedua,
                'nis' => '260003',
                'nisn' => '31512026003',
                'nama_lengkap' => 'Fahri Ramadhan',
                'jenis_kelamin' => JenisKelamin::LakiLaki,
                'tempat_lahir' => 'Garut',
                'tanggal_lahir' => '2014-01-22',
                'alamat' => 'Jl. Patriot No. 45',
                'kelurahan' => 'Sukagalih',
                'kecamatan' => 'Tarogong Kidul',
                'kabupaten' => 'Garut',
                'provinsi' => 'Jawa Barat',
                'no_hp' => '081200000003',
                'nama_ayah' => 'Hendra Gunawan',
                'nama_ibu' => 'Nur Aisyah',
                'pekerjaan_ayah' => 'Guru',
                'pekerjaan_ibu' => 'Pedagang',
                'penghasilan_bulanan' => 4200000,
                'sekolah_asal' => 'MIN Tarogong 02',
                'status' => SantriStatus::Verifikasi,
            ],
        ];

        foreach ($data as $item) {
            $wali = $item['wali'];
            unset($item['wali']);

            Santri::query()->updateOrCreate(
                ['nis' => $item['nis']],
                $item + [
                    'wali_id' => $wali->id,
                    'tahun_ajaran_id' => $tahunAjaran->id,
                ],
            );
        }
    }
}
