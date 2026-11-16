<?php

use App\Models\AuditLog;
use App\Models\Pembayaran;
use App\Models\Pengaturan;
use App\Models\Santri;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\SppGenerator;
use Illuminate\Support\Carbon;

test('admin can get and update allowed pengaturan', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->getJson('/api/admin/pengaturan')->assertOk()->assertJsonPath('success', true);

    $this->actingAs($admin)
        ->putJson('/api/admin/pengaturan', ['pengaturan' => ['biaya_spp_bulanan' => '250000', 'spp_tanggal_jatuh_tempo' => 15]])
        ->assertOk()
        ->assertJsonPath('data.biaya_spp_bulanan.nilai', '250000.00')
        ->assertJsonPath('data.spp_tanggal_jatuh_tempo.nilai', '15');

    $logs = AuditLog::query()->where('action', 'pengaturan.updated')->orderBy('subject_id')->get();
    expect($logs)->toHaveCount(2)
        ->and($logs->pluck('user_id')->unique()->all())->toBe([$admin->getKey()])
        ->and($logs->pluck('old_values')->all())->toBe([
            ['kunci' => 'biaya_spp_bulanan', 'nilai' => null],
            ['kunci' => 'spp_tanggal_jatuh_tempo', 'nilai' => null],
        ]);
});

test('wali cannot access admin pengaturan', function (): void {
    $wali = User::factory()->wali()->create();
    $this->actingAs($wali)->getJson('/api/admin/pengaturan')->assertStatus(403);
    $this->actingAs($wali)->putJson('/api/admin/pengaturan', ['pengaturan' => ['biaya_spp_bulanan' => 100]])->assertStatus(403);
});

test('guest cannot access admin pengaturan', function (): void {
    $this->getJson('/api/admin/pengaturan')->assertStatus(401);
});

test('invalid pengaturan values rejected', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->putJson('/api/admin/pengaturan', ['pengaturan' => ['biaya_spp_bulanan' => -1]])->assertStatus(422);
    $this->actingAs($admin)->putJson('/api/admin/pengaturan', ['pengaturan' => ['spp_tanggal_jatuh_tempo' => 0]])->assertStatus(422);
    $this->actingAs($admin)->putJson('/api/admin/pengaturan', ['pengaturan' => ['spp_tanggal_jatuh_tempo' => 31]])->assertStatus(422);
});

test('disallowed keys ignored', function (): void {
    $admin = User::factory()->admin()->create();
    Pengaturan::write('biaya_spp_bulanan', '100000.00', 'number');
    $this->actingAs($admin)->putJson('/api/admin/pengaturan', ['pengaturan' => ['biaya_spp_bulanan' => '200000', 'rahasia' => 'x']])->assertOk();
    $this->assertDatabaseHas('pengaturan', ['kunci' => 'biaya_spp_bulanan', 'nilai' => '200000.00']);
});

test('old spp tagihan unchanged after settings change', function (): void {
    $admin = User::factory()->admin()->create();
    $ta = TahunAjaran::factory()->create();
    $santri = Santri::factory()->aktif()->create(['tahun_ajaran_id' => $ta->id]);

    Pengaturan::write('biaya_spp_bulanan', '100000.00', 'number');
    Pengaturan::write('spp_tanggal_jatuh_tempo', '10', 'number');

    $gen = app(SppGenerator::class);
    $gen->jalankan($ta, Carbon::create(2026, 9, 1));

    $bill = Pembayaran::query()->first();
    expect($bill->jumlah)->toBe('100000.00');

    $this->actingAs($admin)->putJson('/api/admin/pengaturan', ['pengaturan' => ['biaya_spp_bulanan' => '300000', 'spp_tanggal_jatuh_tempo' => '20']])->assertOk();

    $bill->refresh();
    expect($bill->jumlah)->toBe('100000.00');
    expect($bill->jatuh_tempo->format('Y-m-d'))->toBe('2026-09-10');
});

test('new spp tagihan uses latest settings', function (): void {
    $admin = User::factory()->admin()->create();
    $ta = TahunAjaran::factory()->create();
    $santri = Santri::factory()->aktif()->create(['tahun_ajaran_id' => $ta->id]);

    $this->actingAs($admin)->putJson('/api/admin/pengaturan', ['pengaturan' => ['biaya_spp_bulanan' => '275000', 'spp_tanggal_jatuh_tempo' => '15']])->assertOk();

    $gen = app(SppGenerator::class);
    $res = $gen->jalankan($ta, Carbon::create(2026, 10, 1));
    expect($res['nominal'])->toBe('275000.00');
    expect($res['jatuh_tempo'])->toBe('2026-10-15');

    $bill = Pembayaran::query()->first();
    expect($bill->jumlah)->toBe('275000.00');
});
