<?php

use App\Models\Santri;
use App\Models\TahunAjaran;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Role authorization
|--------------------------------------------------------------------------
|
| The point of these tests is the 403 boundary: a guardian must not reach the
| administrative surface, and an administrator must not reach the guardian
| surface. Every endpoint here is a deliberate Phase 3 probe, not Phase 4
| business logic, so the assertions stay about status codes and scoping.
|
*/

test('a wali is refused the admin surface', function () {
    $wali = User::factory()->wali()->create();

    $this->actingAs($wali)
        ->getJson('/api/admin/overview')
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('data.your_role', 'wali')
        ->assertJsonPath('data.required_roles', ['admin']);
});

test('an admin is refused the wali surface', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->getJson('/api/wali/santri')
        ->assertForbidden()
        ->assertJsonPath('data.your_role', 'admin')
        ->assertJsonPath('data.required_roles', ['wali']);
});

test('an admin reaches the admin surface', function () {
    $admin = User::factory()->admin()->create();
    Santri::factory()->count(2)->create();

    $this->actingAs($admin)
        ->getJson('/api/admin/overview')
        ->assertOk()
        ->assertJsonPath('data.total_santri', 2)
        ->assertJsonStructure(['data' => ['tahun_ajaran_aktif', 'total_santri', 'total_pendaftaran', 'total_pembayaran']]);
});

test('the admin overview reports the active academic year', function () {
    $admin = User::factory()->admin()->create();
    TahunAjaran::factory()->aktif()->create(['nama' => '2026/2027']);

    $this->actingAs($admin)
        ->getJson('/api/admin/overview')
        ->assertOk()
        ->assertJsonPath('data.tahun_ajaran_aktif', '2026/2027');
});

test('a wali reaches their own students', function () {
    $wali = User::factory()->wali()->create();
    Santri::factory()->count(2)->untukWali($wali)->create(['nama_lengkap' => 'Anak Aku']);

    $this->actingAs($wali)
        ->getJson('/api/wali/santri')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.nama_lengkap', 'Anak Aku')
        ->assertJsonStructure([
            'data' => [['id', 'nis', 'nama_lengkap', 'status', 'status_label', 'tahun_ajaran']],
        ]);
});

test('a wali only ever sees their own students', function () {
    $wali = User::factory()->wali()->create();
    $orangLain = User::factory()->wali()->create();

    Santri::factory()->untukWali($wali)->create(['nama_lengkap' => 'Anak Aku']);
    Santri::factory()->untukWali($orangLain)->create(['nama_lengkap' => 'Anak Orang Lain']);

    $response = $this->actingAs($wali)
        ->getJson('/api/wali/santri')
        ->assertOk();

    $response->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nama_lengkap', 'Anak Aku')
        ->assertJsonMissing(['nama_lengkap' => 'Anak Orang Lain']);
});

test('a wali with no students gets an empty list rather than an error', function () {
    $wali = User::factory()->wali()->create();
    Santri::factory()->count(2)->create();

    $this->actingAs($wali)
        ->getJson('/api/wali/santri')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('the wali students endpoint exposes no password material', function () {
    $wali = User::factory()->wali()->create();
    Santri::factory()->untukWali($wali)->create();

    $response = $this->actingAs($wali)->getJson('/api/wali/santri')->assertOk();

    $response->assertJsonMissingPath('data.0.wali.password')
        ->assertJsonMissingPath('data.0.wali.remember_token');
});

test('both roles can read their own profile', function () {
    $wali = User::factory()->wali()->create(['role' => 'wali']);
    $admin = User::factory()->admin()->create(['role' => 'admin']);

    $this->actingAs($wali)->getJson('/api/user')->assertOk()->assertJsonPath('data.role', 'wali');
    $this->actingAs($admin)->getJson('/api/user')->assertOk()->assertJsonPath('data.role', 'admin');
});
