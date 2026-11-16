<?php

use App\Enums\SantriStatus;
use App\Models\AuditLog;
use App\Models\Santri;
use App\Models\TahunAjaran;
use App\Models\User;

test('admin can list and view santri', function (): void {
    $admin = User::factory()->admin()->create();
    $wali = User::factory()->wali()->create();
    $ta = TahunAjaran::factory()->create();
    $santri = Santri::factory()->create([
        'wali_id' => $wali->id,
        'tahun_ajaran_id' => $ta->id,
        'nama_lengkap' => 'Santri Test',
        'nis' => 'NIS-TEST-001',
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin/santri')
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->actingAs($admin)
        ->getJson("/api/admin/santri/{$santri->id}")
        ->assertOk()
        ->assertJsonPath('data.nama_lengkap', 'Santri Test');
});

test('wali cannot access admin santri endpoints', function (): void {
    $wali = User::factory()->wali()->create();
    $santri = Santri::factory()->create();

    $this->actingAs($wali)->getJson('/api/admin/santri')->assertStatus(403);
    $this->actingAs($wali)->getJson("/api/admin/santri/{$santri->id}")->assertStatus(403);
});

test('guest cannot access admin santri endpoints', function (): void {
    $santri = Santri::factory()->create();
    $this->getJson('/api/admin/santri')->assertStatus(401);
    $this->getJson("/api/admin/santri/{$santri->id}")->assertStatus(401);
});

test('admin can search and filter santri', function (): void {
    $admin = User::factory()->admin()->create();
    $ta1 = TahunAjaran::factory()->create();
    $ta2 = TahunAjaran::factory()->create();
    $s1 = Santri::factory()->create(['nama_lengkap' => 'Ahmad Zaki', 'nis' => '12345', 'tahun_ajaran_id' => $ta1->id, 'status' => SantriStatus::Aktif]);
    $s2 = Santri::factory()->create(['nama_lengkap' => 'Budi', 'nis' => '54321', 'tahun_ajaran_id' => $ta2->id, 'status' => SantriStatus::Pendaftaran]);

    $this->actingAs($admin)->getJson('/api/admin/santri?q=Ahmad')->assertOk()->assertJsonCount(1, 'data.items');
    $this->actingAs($admin)->getJson('/api/admin/santri?status=aktif')->assertOk();
    $this->actingAs($admin)->getJson("/api/admin/santri?tahun_ajaran_id={$ta1->id}")->assertOk();
});

test('admin can update allowed fields only', function (): void {
    $admin = User::factory()->admin()->create();
    $santri = Santri::factory()->create([
        'nama_lengkap' => 'Old Name',
        'wali_id' => null,
        'status' => SantriStatus::Pendaftaran,
        'nis' => 'NIS-OLD',
    ]);

    $this->actingAs($admin)
        ->putJson("/api/admin/santri/{$santri->id}", [
            'nama_lengkap' => 'New Name',
            'no_hp' => '08123456789',
            'nisn' => '1234567890',
            'wali_id' => 999, // should be ignored
            'status' => 'aktif', // should be ignored
            'nis' => 'HACKED', // should be ignored
        ])
        ->assertOk()
        ->assertJsonPath('data.nama_lengkap', 'New Name');

    $santri->refresh();
    expect($santri->nama_lengkap)->toBe('New Name');
    expect($santri->wali_id)->toBeNull();
    expect($santri->status->value ?? $santri->status)->toBe('pendaftaran');
    expect($santri->nis)->toBe('NIS-OLD');

    $audit = AuditLog::query()->where('action', 'santri.updated')->sole();
    expect($audit->old_values)->toBe(['nama_lengkap' => 'Old Name'])
        ->and($audit->new_values)->toBe(['nama_lengkap' => 'New Name']);
});

test('admin santri update without changes does not write an audit entry', function (): void {
    $admin = User::factory()->admin()->create();
    $santri = Santri::factory()->create(['nama_lengkap' => 'Nama Tetap']);

    $this->actingAs($admin)
        ->putJson("/api/admin/santri/{$santri->id}", ['nama_lengkap' => 'Nama Tetap'])
        ->assertOk()
        ->assertJsonPath('data.nama_lengkap', 'Nama Tetap');

    expect(AuditLog::query()->count())->toBe(0);
});

test('santri update audit excludes sensitive fields', function (): void {
    $admin = User::factory()->admin()->create();
    $santri = Santri::factory()->create([
        'alamat' => 'Alamat Lama',
        'no_hp' => '081200000001',
        'nisn' => '1111111111',
        'tanggal_lahir' => '2010-01-01',
    ]);

    $this->actingAs($admin)
        ->putJson("/api/admin/santri/{$santri->id}", [
            'alamat' => 'Alamat Baru',
            'no_hp' => '081200000002',
            'nisn' => '2222222222',
            'tanggal_lahir' => '2010-02-03',
        ])
        ->assertOk();

    $audit = AuditLog::query()->where('action', 'santri.updated')->sole();
    expect($audit->old_values)->not->toHaveKey('alamat')
        ->and($audit->new_values)->not->toHaveKey('alamat')
        ->and($audit->old_values)->not->toHaveKey('no_hp')
        ->and($audit->new_values)->not->toHaveKey('no_hp')
        ->and($audit->old_values)->not->toHaveKey('nisn')
        ->and($audit->new_values)->not->toHaveKey('nisn')
        ->and($audit->old_values)->not->toHaveKey('tanggal_lahir')
        ->and($audit->new_values)->not->toHaveKey('tanggal_lahir')
        ->and($audit->toJson())->not->toContain('Alamat Baru')
        ->and($audit->toJson())->not->toContain('081200000002')
        ->and($audit->toJson())->not->toContain('2222222222')
        ->and($audit->toJson())->not->toContain('2010-02-03');
});

test('pagination works for admin santri list', function (): void {
    $admin = User::factory()->admin()->create();
    Santri::factory()->count(20)->create();

    $res = $this->actingAs($admin)->getJson('/api/admin/santri?per_page=5&page=2')->assertOk()->json();
    expect($res['data']['pagination']['current_page'])->toBe(2);
    expect($res['data']['pagination']['per_page'])->toBe(5);
});
