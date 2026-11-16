<?php

use App\Enums\SantriStatus;
use App\Enums\StatusPembayaran;
use App\Enums\StatusPendaftaran;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\Santri;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Wali account and dashboard
|--------------------------------------------------------------------------
|
| Two properties matter more than anything else here, and both are about what a
| guardian cannot do rather than what they can.
|
| The first is scope. Every figure on the dashboard and every row in the student
| list comes from the signed-in guardian's own students, reached through the
| `wali_id` relation. Each test therefore seeds a second guardian with data that
| would be obvious if it leaked, and asserts it is absent. A test that only ever
| had one guardian in the database would pass just as happily against a query
| that returned everything.
|
| The second is the profile subject. There is no id in any profile route or
| payload, so a guardian cannot read or write someone else's account. The tests
| that matter are the ones that send a crafted `user_id` anyway and check that
| the response is still about the caller.
|
*/

beforeEach(function (): void {
    $this->wali = User::factory()->wali()->create([
        'name' => 'Bapak Ahmad',
        'email' => 'ahmad@example.com',
    ]);

    $this->waliLain = User::factory()->wali()->create();
});

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

test('the dashboard reports the guardians own student totals', function (): void {
    Santri::factory()->count(2)->untukWali($this->wali)->create(['status' => SantriStatus::Aktif]);
    Santri::factory()->untukWali($this->wali)->create(['status' => SantriStatus::Verifikasi]);

    $this->actingAs($this->wali)
        ->getJson('/api/wali/dashboard')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.ringkasan_santri.total', 3)
        ->assertJsonPath('data.ringkasan_santri.per_status.aktif', 2)
        ->assertJsonPath('data.ringkasan_santri.per_status.verifikasi', 1)
        // Every case of the enum is present, so the SPA renders a stable set of
        // tiles instead of tiles that appear and disappear as students move.
        ->assertJsonPath('data.ringkasan_santri.per_status.ditolak', 0)
        ->assertJsonStructure([
            'data' => [
                'wali' => ['id', 'name', 'email', 'no_hp', 'role', 'email_verified'],
                'ringkasan_santri' => ['total', 'per_status'],
                'ringkasan_pendaftaran' => ['total', 'per_status'],
                'ringkasan_tagihan' => ['total_tagihan', 'total_lunas', 'per_status'],
            ],
        ]);
});

test('the dashboard never counts another guardians students', function (): void {
    $santriAku = Santri::factory()->untukWali($this->wali)->create(['status' => SantriStatus::Aktif]);
    // (santri_id, tahun_ajaran_id) is unique, so each registration needs its
    // own student. Four students for the other guardian is also what makes a
    // leak unmissable rather than a difference of one.
    $santriDia = collect(range(1, 4))->map(
        fn (int $i) => Santri::factory()->untukWali($this->waliLain)->create()
    );

    Pendaftaran::factory()->createMany(
        $santriDia->map(fn (Santri $s): array => ['santri_id' => $s->id])->all()
    );
    Pembayaran::factory()->createMany(
        $santriDia->take(3)->map(fn (Santri $s): array => [
            'santri_id' => $s->id,
            'status' => StatusPembayaran::Belum,
            'jumlah' => '1000000.00',
        ])->all()
    );

    $response = $this->actingAs($this->wali)
        ->getJson('/api/wali/dashboard')
        ->assertOk();

    // The other guardian's data is loud enough that any leak is unmissable:
    // four students, four registrations, three million rupiah outstanding.
    $response->assertJsonPath('data.ringkasan_santri.total', 1)
        ->assertJsonPath('data.ringkasan_santri.per_status.aktif', 1)
        ->assertJsonPath('data.ringkasan_pendaftaran.total', 0)
        ->assertJsonPath('data.ringkasan_tagihan.total_tagihan', '0.00')
        ->assertJsonPath('data.ringkasan_tagihan.total_lunas', '0.00')
        ->assertJsonMissing(['nama_lengkap' => $santriDia->first()->nama_lengkap]);

    $this->assertDatabaseHas('santris', ['id' => $santriAku->id]);
});

test('the dashboard reports registration progress for the guardians students', function (): void {
    $santri = Santri::factory()->untukWali($this->wali)->create();
    $tahunKedua = TahunAjaran::factory()->create();

    // (santri_id, tahun_ajaran_id) is unique, so a second registration for the
    // same student has to be for a different academic year.
    Pendaftaran::factory()->create([
        'santri_id' => $santri->id,
        'status' => StatusPendaftaran::Diajukan,
    ]);
    Pendaftaran::factory()->diterima()->create([
        'santri_id' => $santri->id,
        'tahun_ajaran_id' => $tahunKedua->id,
    ]);

    $this->actingAs($this->wali)
        ->getJson('/api/wali/dashboard')
        ->assertOk()
        ->assertJsonPath('data.ringkasan_pendaftaran.total', 2)
        ->assertJsonPath('data.ringkasan_pendaftaran.per_status.diajukan', 1)
        ->assertJsonPath('data.ringkasan_pendaftaran.per_status.diterima', 1)
        ->assertJsonPath('data.ringkasan_pendaftaran.per_status.ditolak', 0);
});

test('the dashboard sums outstanding and settled bills separately', function (): void {
    $santri = Santri::factory()->untukWali($this->wali)->create();

    // 1.500,75 outstanding and 500,25 settled. The fractional parts are the
    // point: summing decimals as floats is how a total ends up a cent off.
    Pembayaran::factory()->create([
        'santri_id' => $santri->id,
        'status' => StatusPembayaran::Belum,
        'jumlah' => '1500.75',
    ]);
    Pembayaran::factory()->lunasTunai()->create([
        'santri_id' => $santri->id,
        'jumlah' => '500.25',
    ]);

    $this->actingAs($this->wali)
        ->getJson('/api/wali/dashboard')
        ->assertOk()
        ->assertJsonPath('data.ringkasan_tagihan.total_tagihan', '1500.75')
        ->assertJsonPath('data.ringkasan_tagihan.total_lunas', '500.25')
        ->assertJsonPath('data.ringkasan_tagihan.per_status.belum', 1)
        ->assertJsonPath('data.ringkasan_tagihan.per_status.lunas', 1);
});

test('the dashboard of a guardian with no students reports zeros not an error', function (): void {
    // Someone else's data exists, so "all zeros" can only mean correct scoping.
    Santri::factory()->count(3)->untukWali($this->waliLain)->create();

    $this->actingAs($this->wali)
        ->getJson('/api/wali/dashboard')
        ->assertOk()
        ->assertJsonPath('data.ringkasan_santri.total', 0)
        ->assertJsonPath('data.ringkasan_pendaftaran.total', 0)
        ->assertJsonPath('data.ringkasan_tagihan.total_tagihan', '0.00')
        ->assertJsonPath('data.ringkasan_tagihan.total_lunas', '0.00');
});

/*
|--------------------------------------------------------------------------
| Profile: reading
|--------------------------------------------------------------------------
*/

test('a wali reads their own profile', function (): void {
    $this->wali->forceFill(['no_hp' => '081234567890'])->save();

    $this->actingAs($this->wali)
        ->getJson('/api/wali/profile')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $this->wali->id)
        ->assertJsonPath('data.name', 'Bapak Ahmad')
        ->assertJsonPath('data.email', 'ahmad@example.com')
        ->assertJsonPath('data.no_hp', '081234567890')
        ->assertJsonPath('data.role', 'wali')
        ->assertJsonPath('data.email_verified', true);
});

test('the profile never exposes password material', function (): void {
    $this->actingAs($this->wali)
        ->getJson('/api/wali/profile')
        ->assertOk()
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.remember_token');
});

test('a crafted user id in the query string does not change whose profile is returned', function (): void {
    $response = $this->actingAs($this->wali)
        ->getJson('/api/wali/profile?user_id='.$this->waliLain->id)
        ->assertOk()
        ->assertJsonPath('data.id', $this->wali->id)
        ->assertJsonPath('data.email', 'ahmad@example.com');

    expect($response->getContent())->not->toContain($this->waliLain->email);
});

/*
|--------------------------------------------------------------------------
| Profile: updating
|--------------------------------------------------------------------------
*/

test('a wali updates their name and phone number', function (): void {
    $this->actingAs($this->wali)
        ->putJson('/api/wali/profile', [
            'name' => 'Bapak Ahmad Fauzi',
            'no_hp' => '0812 3456 7890',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Bapak Ahmad Fauzi')
        ->assertJsonPath('data.no_hp', '0812 3456 7890');

    $this->assertDatabaseHas('users', [
        'id' => $this->wali->id,
        'name' => 'Bapak Ahmad Fauzi',
        'no_hp' => '0812 3456 7890',
    ]);
});

test('a wali may clear their phone number', function (): void {
    $this->wali->forceFill(['no_hp' => '081234567890'])->save();

    $this->actingAs($this->wali)
        ->putJson('/api/wali/profile', ['name' => 'Bapak Ahmad', 'no_hp' => null])
        ->assertOk()
        ->assertJsonPath('data.no_hp', null);

    $this->assertDatabaseHas('users', ['id' => $this->wali->id, 'no_hp' => null]);
});

test('a blank phone number is stored as null rather than an empty string', function (): void {
    $this->wali->forceFill(['no_hp' => '081234567890'])->save();

    $this->actingAs($this->wali)
        ->putJson('/api/wali/profile', ['name' => 'Bapak Ahmad', 'no_hp' => '   '])
        ->assertOk()
        ->assertJsonPath('data.no_hp', null);

    $this->assertDatabaseHas('users', ['id' => $this->wali->id, 'no_hp' => null]);
});

test('a crafted payload cannot escalate a wali to admin', function (): void {
    $this->actingAs($this->wali)
        ->putJson('/api/wali/profile', [
            'name' => 'Bapak Ahmad',
            'no_hp' => '081234567890',
            'role' => 'admin',
            'email' => 'attacker@example.com',
            'id' => $this->waliLain->id,
        ])
        ->assertOk()
        // Unvalidated keys are dropped, so the response is the caller's own row.
        ->assertJsonPath('data.id', $this->wali->id)
        ->assertJsonPath('data.role', 'wali')
        ->assertJsonPath('data.email', 'ahmad@example.com');

    $this->assertDatabaseHas('users', [
        'id' => $this->wali->id,
        'role' => 'wali',
        'email' => 'ahmad@example.com',
    ]);

    $this->assertDatabaseHas('users', [
        'id' => $this->waliLain->id,
        'email' => $this->waliLain->email,
    ]);
});

test('a crafted payload cannot change another guardians password', function (): void {
    $sebelum = $this->waliLain->password;

    $this->actingAs($this->wali)
        ->putJson('/api/wali/profile', [
            'name' => 'Bapak Ahmad',
            'password' => 'dirampas123',
        ])
        ->assertOk();

    $this->assertDatabaseHas('users', [
        'id' => $this->waliLain->id,
        'password' => $sebelum,
    ]);
});

test('a wali cannot sign in with the old password after a password change', function (): void {
    $this->actingAs($this->wali)
        ->putJson('/api/wali/password', [
            'current_password' => 'password',
            'password' => 'barubaru123',
            'password_confirmation' => 'barubaru123',
        ])
        ->assertOk();

    // The `actingAs` above leaves the user on the default auth guard, which
    // makes this guest-only login route redirect (302) instead of answering.
    // Clear the acting state so the login endpoint runs as a guest. The
    // explicit 'web' guard matters: the authenticated PUT above leaves the
    // default guard switched to Sanctum's, so clearing the default would hit
    // the wrong guard.
    $this->actingAsGuest('web');

    $this->postJson('/api/auth/login', [
        'email' => 'ahmad@example.com',
        'password' => 'password',
    ])->assertUnauthorized();

    $this->postJson('/api/auth/login', [
        'email' => 'ahmad@example.com',
        'password' => 'barubaru123',
    ])->assertOk();
});

test('the profile update rejects an invalid name and phone number', function (string $name, string $noHp, array $expected): void {
    $this->actingAs($this->wali)
        ->putJson('/api/wali/profile', ['name' => $name, 'no_hp' => $noHp])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($expected)
        ->assertJsonPath('success', false);
})->with([
    'name too short' => ['Ab', '081234567890', ['name']],
    'name missing' => ['', '081234567890', ['name']],
    'phone too long' => ['Bapak Ahmad', '0812-3456-7890-1234-5678-9012', ['no_hp']],
    'phone with letters' => ['Bapak Ahmad', '0812abc3456', ['no_hp']],
    'both invalid' => ['Ab', 'bukan-nomor', ['name', 'no_hp']],
]);

test('a wali cannot update their email through the profile endpoint', function (): void {
    $this->actingAs($this->wali)
        ->putJson('/api/wali/profile', [
            'name' => 'Bapak Ahmad',
            'email' => 'baru@example.com',
        ])
        ->assertOk();

    $this->assertDatabaseHas('users', [
        'id' => $this->wali->id,
        'email' => 'ahmad@example.com',
    ]);
});

/*
|--------------------------------------------------------------------------
| Password
|--------------------------------------------------------------------------
*/

test('a wali changes their password and the new one works', function (): void {
    $this->actingAs($this->wali)
        ->putJson('/api/wali/password', [
            'current_password' => 'password',
            'password' => 'rahasia-baru-456',
            'password_confirmation' => 'rahasia-baru-456',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertTrue(Hash::check('rahasia-baru-456', $this->wali->fresh()->password));
});

test('changing a password requires the correct current password', function (): void {
    $this->actingAs($this->wali)
        ->putJson('/api/wali/password', [
            'current_password' => 'password-keluar-salah',
            'password' => 'rahasia-baru-456',
            'password_confirmation' => 'rahasia-baru-456',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['current_password']);

    // Nothing was written: the old password still authenticates.
    // The `actingAs` above leaves the user on the default auth guard, which
    // makes this guest-only login route redirect (302) instead of answering.
    // Clear the acting state so the login endpoint runs as a guest. The
    // explicit 'web' guard matters: the authenticated PUT above leaves the
    // default guard switched to Sanctum's, so clearing the default would hit
    // the wrong guard.
    $this->actingAsGuest('web');

    $this->postJson('/api/auth/login', [
        'email' => 'ahmad@example.com',
        'password' => 'password',
    ])->assertOk();
});

test('the new password must be confirmed', function (): void {
    $this->actingAs($this->wali)
        ->putJson('/api/wali/password', [
            'current_password' => 'password',
            'password' => 'rahasia-baru-456',
            'password_confirmation' => 'rahasia-baru-999',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

test('the new password must be different from the current one', function (): void {
    $this->actingAs($this->wali)
        ->putJson('/api/wali/password', [
            'current_password' => 'password',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

test('a weak new password is rejected', function (): void {
    $this->actingAs($this->wali)
        ->putJson('/api/wali/password', [
            'current_password' => 'password',
            'password' => 'pendek',
            'password_confirmation' => 'pendek',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

test('a password change revokes other sessions for that guardian', function (): void {
    $this->actingAs($this->wali);

    // createToken() persists as a side effect; the returned NewAccessToken is
    // only a handle, not a model.
    $this->wali->createToken('browser')->plainTextToken;
    $this->assertSame(1, $this->wali->tokens()->count());

    $this->putJson('/api/wali/password', [
        'current_password' => 'password',
        'password' => 'rahasia-baru-456',
        'password_confirmation' => 'rahasia-baru-456',
    ])->assertOk();

    $this->assertSame(0, $this->wali->tokens()->count());
});

/*
|--------------------------------------------------------------------------
| Student list
|--------------------------------------------------------------------------
*/

test('a wali lists their own students', function (): void {
    Santri::factory()->untukWali($this->wali)->create(['nama_lengkap' => 'Anak Aku']);

    $this->actingAs($this->wali)
        ->getJson('/api/wali/santri')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nama_lengkap', 'Anak Aku')
        ->assertJsonStructure([
            'data' => [['id', 'nis', 'nisn', 'nama_lengkap', 'jenis_kelamin', 'status', 'status_label', 'tahun_ajaran']],
        ]);
});

test('the student list excludes every other guardians students', function (): void {
    Santri::factory()->untukWali($this->wali)->create(['nama_lengkap' => 'Anak Aku']);
    Santri::factory()->untukWali($this->waliLain)->create(['nama_lengkap' => 'Anak Orang Lain']);

    $this->actingAs($this->wali)
        ->getJson('/api/wali/santri')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nama_lengkap', 'Anak Aku')
        ->assertJsonMissing(['nama_lengkap' => 'Anak Orang Lain']);
});

test('the student list exposes no password material', function (): void {
    Santri::factory()->untukWali($this->wali)->create();

    $this->actingAs($this->wali)
        ->getJson('/api/wali/santri')
        ->assertOk()
        ->assertJsonMissingPath('data.0.wali.password')
        ->assertJsonMissingPath('data.0.wali.remember_token');
});

/*
|--------------------------------------------------------------------------
| Authorization
|--------------------------------------------------------------------------
*/

test('every wali endpoint refuses a guest', function (string $method, string $uri): void {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['GET', '/api/wali/dashboard'],
    ['GET', '/api/wali/profile'],
    ['PUT', '/api/wali/profile'],
    ['PUT', '/api/wali/password'],
    ['GET', '/api/wali/santri'],
]);

test('every wali endpoint refuses an admin', function (string $method, string $uri): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->json($method, $uri)
        ->assertForbidden()
        ->assertJsonPath('success', false);
})->with([
    ['GET', '/api/wali/dashboard'],
    ['GET', '/api/wali/profile'],
    ['PUT', '/api/wali/profile'],
    ['PUT', '/api/wali/password'],
    ['GET', '/api/wali/santri'],
]);

test('no wali endpoint exists for another guardians account', function (): void {
    // There is no route taking a user id, so this 404s rather than 403ing. A
    // 403 would mean a route exists and merely checks the role; a 404 is the
    // stronger statement that the surface was never built.
    $this->actingAs($this->wali)
        ->getJson('/api/wali/profile/'.$this->waliLain->id)
        ->assertNotFound();
});
