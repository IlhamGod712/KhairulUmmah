<?php

use App\Enums\JenisPembayaran;
use App\Enums\StatusPembayaran;
use App\Models\Pembayaran;
use App\Models\Pengaturan;
use App\Models\Santri;
use App\Models\TahunAjaran;
use App\Models\User;
use Database\Seeders\DemoPembayaranPendaftaranSeeder;

beforeEach(function (): void {
    TahunAjaran::factory()->aktif()->create(['nama' => '2026/2027']);
    Pengaturan::write('biaya_pendaftaran', '500000', 'integer');
});

test('the demo seeder creates one unpaid registration fee and repeats safely', function (): void {
    $this->seed(DemoPembayaranPendaftaranSeeder::class);

    $santri = Santri::query()->where('nis', 'DEMO-0001')->sole();
    $tahun = TahunAjaran::active()->sole();

    // A real SPP invoice in the same table, to prove the demo seeder leaves
    // every other kind of payment row alone.
    $spp = Pembayaran::factory()->spp('2026-09-01')->create([
        'santri_id' => $santri->getKey(),
        'tahun_ajaran_id' => $tahun->getKey(),
    ]);

    $this->seed(DemoPembayaranPendaftaranSeeder::class);

    expect(User::query()->where('email', 'demo.wali@khairulummah.test')->count())->toBe(1)
        ->and(Santri::query()->where('nis', 'DEMO-0001')->count())->toBe(1)
        ->and(Pembayaran::query()->where('jenis_pembayaran', JenisPembayaran::Pendaftaran)->count())->toBe(1)
        ->and(Pembayaran::query()->whereKey($spp->getKey())->value('jenis_pembayaran'))->toBe(JenisPembayaran::Spp);

    $fee = Pembayaran::query()
        ->where('jenis_pembayaran', JenisPembayaran::Pendaftaran)
        ->sole();

    expect($fee->status)->toBe(StatusPembayaran::Belum)
        ->and($fee->jumlah)->toBe('500000.00')
        ->and($fee->periode)->toBeNull()
        ->and($fee->pendaftaran?->nomor_pendaftaran)->toBe('DEMO-0001')
        ->and($fee->santri->wali?->email)->toBe('demo.wali@khairulummah.test');
});

test('the demo seeder does nothing without an active academic year', function (): void {
    TahunAjaran::query()->update(['is_active' => false]);

    $this->seed(DemoPembayaranPendaftaranSeeder::class);

    expect(Pembayaran::query()->count())->toBe(0)
        ->and(Santri::query()->count())->toBe(0);
});
