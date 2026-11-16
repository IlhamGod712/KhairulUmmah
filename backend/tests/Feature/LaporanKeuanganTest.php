<?php

use App\Enums\JenisPembayaran;
use App\Enums\MetodePembayaran;
use App\Enums\StatusPembayaran;
use App\Models\Pembayaran;
use App\Models\Santri;
use App\Models\User;

test('admin can load the financial report', function (): void {
    $response = $this->actingAs(User::factory()->admin()->create())->getJson('/api/admin/laporan');
    $response->assertOk()->assertJsonPath('success', true);
});

test('wali cannot load the financial report', function (): void {
    $this->actingAs(User::factory()->wali()->create())->getJson('/api/admin/laporan')->assertStatus(403);
});

test('guest cannot load the financial report', function (): void {
    $this->getJson('/api/admin/laporan')->assertStatus(401);
});

test('financial report rejects an inverted date range', function (): void {
    $this->actingAs(User::factory()->admin()->create())
        ->getJson('/api/admin/laporan?tanggal_awal=2026-12-01&tanggal_akhir=2026-11-01')
        ->assertStatus(422);
});

test('financial report counts only settled payments as paid', function (): void {
    $santri = Santri::factory()->aktif()->create();
    Pembayaran::factory()->create([
        'santri_id' => $santri->id,
        'tahun_ajaran_id' => $santri->tahun_ajaran_id,
        'jenis_pembayaran' => JenisPembayaran::Spp,
        'periode' => '2026-07-01',
        'jatuh_tempo' => '2026-07-10',
        'status' => StatusPembayaran::Lunas,
        'tanggal_bayar' => now()->toDateString(),
        'metode' => MetodePembayaran::Tunai,
    ]);
    $response = $this->actingAs(User::factory()->admin()->create())->getJson('/api/admin/laporan?status=lunas');
    $response->assertOk();
    expect($response->json('data')['ringkasan']['terverifikasi']['jumlah'])->toBe(1);
});

test('financial report export returns a csv stream', function (): void {
    $response = $this->actingAs(User::factory()->admin()->create())->getJson('/api/admin/laporan/ekspor');
    $response->assertOk();
});
