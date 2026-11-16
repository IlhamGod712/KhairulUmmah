<?php

use App\Models\Santri;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Guardian claiming an existing student (Phase 7)
|--------------------------------------------------------------------------
|
| The claims defended here are:
|
|   - identification requires all three fields — number, name, birth date —
|     and a payload that fails any of them finds nothing (404, and the flow
|     stops there; there is no retry loop);
|   - registering links the student found by that same search to the signed-
|     in guardian, never to an id sent by the client, and never by creating a
|     new row or touching the student's status;
|   - a student already claimed by this guardian answers "sudah terdaftar pada
|     akun Anda", and one claimed by someone else answers "sudah terhubung
|     dengan akun lain" without moving or naming the other account;
|   - guests get 401 and admins get 403 from both endpoints, and the only data
|     ever returned is the safe student projection.
|
*/

beforeEach(function (): void {
    $this->wali = User::factory()->wali()->create(['name' => 'Bapak Pengadu']);
    $this->admin = User::factory()->admin()->create();
});

/**
 * The three identification fields, filled from a student's own record.
 *
 * @return array<string, string>
 */
function identifikasi(Santri $santri, string $nomor): array
{
    return [
        'nis_atau_nisn' => $nomor,
        'nama_lengkap' => $santri->nama_lengkap,
        'tanggal_lahir' => $santri->tanggal_lahir->toDateString(),
    ];
}

/*
|--------------------------------------------------------------------------
| Identification
|--------------------------------------------------------------------------
*/

test('a guardian finds an unowned student by NIS plus name and birth date', function (): void {
    $santri = Santri::factory()->tanpaWali()->aktif()->create();

    $this->actingAs($this->wali)
        ->postJson('/api/wali/santri-lama/match', identifikasi($santri, $santri->nis))
        ->assertOk()
        ->assertJsonPath('message', 'Data santri ditemukan.')
        ->assertJsonPath('data.id', $santri->id)
        ->assertJsonPath('data.nis', $santri->nis)
        ->assertJsonPath('data.nama_lengkap', $santri->nama_lengkap)
        ->assertJsonPath('data.tanggal_lahir', $santri->tanggal_lahir->toDateString());
});

test('a guardian finds the same student using the NISN instead of the NIS', function (): void {
    $santri = Santri::factory()->tanpaWali()->aktif()->create();

    $this->actingAs($this->wali)
        ->postJson('/api/wali/santri-lama/match', identifikasi($santri, $santri->nisn))
        ->assertOk()
        ->assertJsonPath('data.id', $santri->id);
});

test('a number, name, or birth date that does not match finds nothing', function (array $rusak): void {
    $santri = Santri::factory()->tanpaWali()->aktif()->create();
    $payload = array_merge(identifikasi($santri, $santri->nis), $rusak);

    $this->actingAs($this->wali)
        ->postJson('/api/wali/santri-lama/match', $payload)
        ->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Data Santri Tidak Ditemukan.')
        ->assertJsonPath('data', null);
})->with([
    'NIS/NISN berbeda' => [['nis_atau_nisn' => '999999']],
    'nama berbeda' => [['nama_lengkap' => 'Bukan Nama']],
    'tanggal lahir berbeda' => [['tanggal_lahir' => '2000-01-01']],
]);

test('registering with fields that match nothing stops with the same not-found answer', function (): void {
    $santri = Santri::factory()->tanpaWali()->aktif()->create();
    $payload = identifikasi($santri, $santri->nis);
    $payload['nama_lengkap'] = 'Bukan Nama';

    $this->actingAs($this->wali)
        ->postJson('/api/wali/santri-lama/register', $payload)
        ->assertNotFound()
        ->assertJsonPath('message', 'Data Santri Tidak Ditemukan.')
        ->assertJsonPath('data', null);
});

test('matcher identification requires all three fields', function (): void {
    $this->actingAs($this->wali)
        ->postJson('/api/wali/santri-lama/match', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nis_atau_nisn', 'nama_lengkap', 'tanggal_lahir'])
        ->assertJsonPath('data.errors.nis_atau_nisn.0', 'NIS/NISN wajib diisi.')
        ->assertJsonPath('data.errors.nama_lengkap.0', 'Nama santri wajib diisi.')
        ->assertJsonPath('data.errors.tanggal_lahir.0', 'Tanggal lahir wajib diisi.');
});

/*
|--------------------------------------------------------------------------
| Linking
|--------------------------------------------------------------------------
*/

test('a guardian registers the found student, which links instead of recreating', function (): void {
    $santri = Santri::factory()->tanpaWali()->aktif()->create();
    $sebelum = Santri::query()->count();

    $this->actingAs($this->wali)
        ->postJson('/api/wali/santri-lama/register', identifikasi($santri, $santri->nis))
        ->assertOk()
        ->assertJsonPath('message', 'Data Santri berhasil dihubungkan ke akun Anda.')
        ->assertJsonPath('data.id', $santri->id)
        ->assertJsonPath('data.status', 'aktif');

    // Same row claimed, with the same active status: no clone, no demotion.
    $this->assertDatabaseHas('santris', [
        'id' => $santri->id,
        'wali_id' => $this->wali->id,
        'status' => 'aktif',
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $this->wali->getKey(),
        'action' => 'wali.santri_linked',
        'subject_type' => Santri::class,
        'subject_id' => $santri->id,
    ]);

    expect(Santri::query()->count())->toBe($sebelum);
});

test('linking an already-active student leaves their status untouched', function (): void {
    $santri = Santri::factory()->tanpaWali()->aktif()->create();

    $this->actingAs($this->wali)
        ->postJson('/api/wali/santri-lama/register', identifikasi($santri, $santri->nis))
        ->assertOk()
        ->assertJsonPath('data.status', 'aktif');

    $this->assertDatabaseHas('santris', ['id' => $santri->id, 'status' => 'aktif']);
});

test('a student already registered to the same guardian is not linked twice', function (): void {
    $santri = Santri::factory()->untukWali($this->wali)->aktif()->create();

    $this->actingAs($this->wali)
        ->postJson('/api/wali/santri-lama/register', identifikasi($santri, $santri->nis))
        ->assertStatus(409)
        ->assertJsonPath('message', 'Data Santri sudah terdaftar pada akun Anda.')
        ->assertJsonPath('data', null);

    $this->assertDatabaseHas('santris', ['id' => $santri->id, 'wali_id' => $this->wali->id]);
});

test('a student already registered to another guardian is refused without being moved', function (): void {
    $pemilik = User::factory()->wali()->create();
    $santri = Santri::factory()->untukWali($pemilik)->aktif()->create();

    $this->actingAs($this->wali)
        ->postJson('/api/wali/santri-lama/register', identifikasi($santri, $santri->nis))
        ->assertStatus(409)
        ->assertJsonPath('message', 'Data Santri sudah terhubung dengan akun lain.')
        ->assertJsonPath('data', null);

    $this->assertDatabaseHas('santris', ['id' => $santri->id, 'wali_id' => $pemilik->id]);
});

test('a client-supplied wali_id or santri_id is ignored', function (): void {
    $santri = Santri::factory()->tanpaWali()->aktif()->create();
    $targetPalsu = Santri::factory()->tanpaWali()->create();
    $waliPalsu = User::factory()->wali()->create();

    $payload = identifikasi($santri, $santri->nis) + [
        'wali_id' => $waliPalsu->id,
        'user_id' => $waliPalsu->id,
        'santri_id' => $targetPalsu->id,
    ];

    $this->actingAs($this->wali)
        ->postJson('/api/wali/santri-lama/register', $payload)
        ->assertOk()
        ->assertJsonPath('data.id', $santri->id);

    // The matched student is claimed by the session user, not by the forged id.
    $this->assertDatabaseHas('santris', ['id' => $santri->id, 'wali_id' => $this->wali->id]);
    // The decoy student and owner are untouched.
    $this->assertDatabaseHas('santris', ['id' => $targetPalsu->id, 'wali_id' => null]);
    $this->assertDatabaseMissing('santris', ['id' => $santri->id, 'wali_id' => $waliPalsu->id]);
});

test('the linked student appears on the guardian student list afterwards', function (): void {
    $santri = Santri::factory()->tanpaWali()->aktif()->create(['nama_lengkap' => 'Anak Aku']);

    $this->actingAs($this->wali)
        ->postJson('/api/wali/santri-lama/register', identifikasi($santri, $santri->nis))
        ->assertOk();

    $this->actingAs($this->wali)
        ->getJson('/api/wali/santri')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nama_lengkap', 'Anak Aku');
});

/*
|--------------------------------------------------------------------------
| Surface area
|--------------------------------------------------------------------------
*/

test('match and register expose only safe student fields, never owner or account data', function (): void {
    $pemilik = User::factory()->wali()->create();
    $santri = Santri::factory()->untukWali($pemilik)->aktif()->create();

    // Matching someone else's student still works (there is no 404 to filter
    // on), but the answer must contain only the identity the caller typed.
    $this->actingAs($this->wali)
        ->postJson('/api/wali/santri-lama/match', identifikasi($santri, $santri->nis))
        ->assertOk()
        ->assertJsonMissingPath('data.wali_id')
        ->assertJsonMissingPath('data.user')
        ->assertJsonMissingPath('data.email')
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissing(['wali' => null]);

    $this->actingAs($this->wali)
        ->postJson('/api/wali/santri-lama/register', identifikasi($santri, $santri->nis))
        ->assertStatus(409)
        ->assertJsonMissingPath('data.wali_id')
        ->assertJsonMissingPath('data.user');
});

test('guests cannot match or register santri lama', function (string $uri): void {
    $this->postJson($uri, [])
        ->assertUnauthorized()
        ->assertJsonPath('success', false);
})->with([
    'match' => ['/api/wali/santri-lama/match'],
    'register' => ['/api/wali/santri-lama/register'],
]);

test('an admin cannot use the wali santri-lama endpoints', function (string $uri): void {
    $this->actingAs($this->admin)
        ->postJson($uri, [])
        ->assertForbidden()
        ->assertJsonPath('success', false);
})->with([
    'match' => ['/api/wali/santri-lama/match'],
    'register' => ['/api/wali/santri-lama/register'],
]);
