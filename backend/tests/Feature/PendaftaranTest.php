<?php

use App\Enums\SantriStatus;
use App\Enums\StatusPendaftaran;
use App\Models\AuditLog;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\Santri;
use App\Models\TahunAjaran;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Guardian registration
|--------------------------------------------------------------------------
|
| Three claims are being defended here, and they are all negative ones.
|
| The first is ownership. A submission has to land on the account that made
| it, which means every test that creates a registration also creates a second
| guardian holding data that would be impossible to miss if it leaked, and then
| asserts its absence from the first guardian's view.
|
| The second is that nothing authoritative arrives in the body. `wali_id`,
| `user_id`, `role`, `status`, `nis`, and `tahun_ajaran_id` are all accepted
| from nobody, and the tests that prove it send them anyway rather than leaving
| them out to be polite.
|
| The third is that registering is not the same as being enrolled. Every status
| assertion here is a negative one: the student is not active, the registration
| is not accepted, and nothing about this endpoint can promote either. That
| pipeline runs through acceptance and payment, which is Phase 6.
|
*/

beforeEach(function (): void {
    $this->tahunAjaran = TahunAjaran::factory()->aktif()->create();
    $this->tahunAjaranLama = TahunAjaran::factory()->create(['is_active' => false]);

    $this->wali = User::factory()->wali()->create(['name' => 'Bapak Ahmad']);
    $this->waliLain = User::factory()->wali()->create(['name' => 'Ibu Siti']);
    $this->admin = User::factory()->admin()->create();
});

/**
 * A complete, valid submission. Only the columns the form actually owns.
 *
 * @param  array<string, mixed>  $ubah
 * @return array<string, mixed>
 */
function formPendaftaran(array $ubah = []): array
{
    return array_merge([
        'nama_lengkap' => 'Ahmad Fauzi',
        'jenis_kelamin' => 'laki_laki',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2013-05-17',
        'alamat' => 'Jl. Merdeka No. 17, RT 02 RW 05',
    ], $ubah);
}

/*
|--------------------------------------------------------------------------
| Creating a registration
|--------------------------------------------------------------------------
*/

test('a wali can register a new student', function (): void {
    $response = $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran([
            'nisn' => '1234567890',
            'kelurahan' => 'Sukajadi',
            'kecamatan' => 'Sukajadi',
            'kabupaten' => 'Bandung',
            'provinsi' => 'Jawa Barat',
            'no_hp' => '08123456789',
            'nama_ayah' => 'Ahmad Sudirman',
            'nama_ibu' => 'Siti Aminah',
            'pekerjaan_ayah' => 'Guru',
            'pekerjaan_ibu' => 'Pedagang',
            'penghasilan_bulanan' => 3_500_000,
            'sekolah_asal' => 'SDNtest 01',
        ]));

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.nama_santri', 'Ahmad Fauzi')
        ->assertJsonPath('data.tahun_ajaran', $this->tahunAjaran->nama)
        ->assertJsonPath('data.calon_santri.nisn', '1234567890')
        ->assertJsonPath('data.calon_santri.alamat', 'Jl. Merdeka No. 17, RT 02 RW 05')
        ->assertJsonPath('data.calon_santri.nama_ayah', 'Ahmad Sudirman')
        ->assertJsonPath('data.calon_santri.penghasilan_bulanan', 3_500_000)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'nomor_pendaftaran',
                'tanggal_daftar',
                'status',
                'status_label',
                'santri_id',
                'nama_santri',
                'tahun_ajaran',
                'ringkasan_pembayaran' => ['total', 'lunas', 'status', 'status_label'],
                'calon_santri' => ['id', 'nis', 'nisn', 'nama_lengkap', 'jenis_kelamin', 'status', 'status_label'],
                'wali' => ['id', 'name', 'email', 'no_hp'],
            ],
        ]);

    expect(Santri::query()->count())->toBe(1);
    expect(Pendaftaran::query()->count())->toBe(1);

    $audit = AuditLog::query()->where('action', 'pendaftaran.submitted')->sole();
    expect($audit->user_id)->toBe($this->wali->getKey())
        ->and($audit->subject_type)->toBe(Pendaftaran::class)
        ->and($audit->subject_id)->toBe($response->json('data.id'))
        ->and($audit->new_values)->toBe(['status' => 'diajukan']);
});

test('a guest cannot register a student', function (): void {
    $this->postJson('/api/wali/pendaftaran', formPendaftaran())
        ->assertUnauthorized()
        ->assertJsonPath('success', false);

    expect(Santri::query()->count())->toBe(0);
    expect(Pendaftaran::query()->count())->toBe(0);
});

test('an admin cannot use the wali registration endpoint', function (): void {
    $this->actingAs($this->admin)
        ->postJson('/api/wali/pendaftaran', formPendaftaran())
        ->assertForbidden()
        ->assertJsonPath('success', false);

    expect(Santri::query()->count())->toBe(0);
});

test('a guest cannot list registrations', function (): void {
    $this->getJson('/api/wali/pendaftaran')->assertUnauthorized();
});

test('an admin cannot list registrations through the wali endpoint', function (): void {
    $this->actingAs($this->admin)->getJson('/api/wali/pendaftaran')->assertForbidden();
});

test('the registration is attached to the authenticated wali', function (): void {
    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran())
        ->assertCreated()
        ->assertJsonPath('data.wali.id', $this->wali->id);

    $santri = Santri::query()->firstOrFail();
    $pendaftaran = Pendaftaran::query()->firstOrFail();

    expect($santri->wali_id)->toBe($this->wali->id)
        ->and($santri->wali->name)->toBe('Bapak Ahmad')
        ->and($pendaftaran->santri_id)->toBe($santri->id);
});

test('an id in the request cannot take over ownership', function (): void {
    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran([
            // Every one of these is ignored. Not rejected — ignored, so the
            // guardian is not told which of them the server happens to read.
            'wali_id' => $this->waliLain->id,
            'user_id' => $this->waliLain->id,
            'role' => 'admin',
        ]))
        ->assertCreated()
        ->assertJsonPath('data.wali.id', $this->wali->id);

    $santri = Santri::query()->firstOrFail();

    expect($santri->wali_id)->toBe($this->wali->id)
        ->and($santri->wali_id)->not->toBe($this->waliLain->id);
});

test('a client supplied status cannot activate the student', function (): void {
    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran([
            'status' => 'aktif',
            'santri_status' => 'aktif',
        ]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'diajukan')
        ->assertJsonPath('data.santri_status', 'pendaftaran')
        ->assertJsonPath('data.calon_santri.status', 'pendaftaran');

    expect(Santri::query()->firstOrFail()->status)->toBe(SantriStatus::Pendaftaran);
});

test('server issued numbers cannot be supplied by the client', function (): void {
    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran([
            'nis' => 'MILIK-SAYA',
            'nomor_pendaftaran' => 'NOMOR-SAYA',
            'tanggal_daftar' => '1999-01-01',
            'diproses_oleh' => $this->admin->id,
        ]))
        ->assertCreated();

    $santri = Santri::query()->firstOrFail();
    $pendaftaran = Pendaftaran::query()->firstOrFail();

    expect($santri->nis)->not->toBe('MILIK-SAYA')
        ->and($pendaftaran->nomor_pendaftaran)->not->toBe('NOMOR-SAYA')
        ->and($pendaftaran->tanggal_daftar->toDateString())->toBe(now()->toDateString())
        ->and($pendaftaran->diproses_oleh)->toBeNull();
});

test('a registration always lands in the active academic year', function (): void {
    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran([
            'tahun_ajaran_id' => $this->tahunAjaranLama->id,
        ]))
        ->assertCreated()
        ->assertJsonPath('data.tahun_ajaran', $this->tahunAjaran->nama);

    expect(Santri::query()->firstOrFail()->tahun_ajaran_id)->toBe($this->tahunAjaran->id)
        ->and(Pendaftaran::query()->firstOrFail()->tahun_ajaran_id)->toBe($this->tahunAjaran->id);
});

test('registration is refused when no academic year is active', function (): void {
    $this->tahunAjaran->update(['is_active' => false]);

    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran())
        ->assertStatus(409)
        ->assertJsonPath('success', false);

    // Refusing before writing anything matters as much as the 409 itself: a
    // student row with no registration behind it would be invisible in the
    // admin queue and impossible to explain.
    expect(Santri::query()->count())->toBe(0);
    expect(Pendaftaran::query()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Initial status — the point of the whole phase
|--------------------------------------------------------------------------
*/

test('a new registration starts as diajukan with a student in pendaftaran', function (): void {
    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran())
        ->assertCreated()
        // "Menunggu verifikasi" in the brief's terms.
        ->assertJsonPath('data.status', 'diajukan')
        ->assertJsonPath('data.status_label', 'Diajukan')
        ->assertJsonPath('data.catatan', null)
        ->assertJsonPath('data.diproses_oleh', null)
        ->assertJsonPath('data.ringkasan_pembayaran.total', 0)
        ->assertJsonPath('data.ringkasan_pembayaran.status', 'belum');

    $pendaftaran = Pendaftaran::query()->firstOrFail();

    expect($pendaftaran->status)->toBe(StatusPendaftaran::Diajukan)
        ->and($pendaftaran->status->isDecided())->toBeFalse();
});

test('registering never produces an active student', function (): void {
    $this->actingAs($this->wali)->postJson('/api/wali/pendaftaran', formPendaftaran())->assertCreated();
    $this->actingAs($this->waliLain)
        ->postJson('/api/wali/pendaftaran', formPendaftaran(['nama_lengkap' => 'Siti Aminah']))
        ->assertCreated();

    $santris = Santri::query()->get();

    expect($santris)->toHaveCount(2)
        // `pluck()` on an enum-cast column hands back the enum, not its value,
        // so compare on the values.
        ->and($santris->map(fn (Santri $santri): string => $santri->status->value)->unique()->all())
        ->toBe([SantriStatus::Pendaftaran->value])
        ->and(Santri::query()->aktif()->count())->toBe(0)
        ->and(Pendaftaran::query()->where('status', StatusPendaftaran::Diterima->value)->count())->toBe(0)
        // Nothing has been paid, so nothing is recorded as paid.
        ->and(Pembayaran::query()->count())->toBe(0);
});

test('the dashboard reports the new student as registering rather than active', function (): void {
    $this->actingAs($this->wali)->postJson('/api/wali/pendaftaran', formPendaftaran())->assertCreated();

    $this->actingAs($this->wali)
        ->getJson('/api/wali/dashboard')
        ->assertOk()
        ->assertJsonPath('data.ringkasan_santri.total', 1)
        ->assertJsonPath('data.ringkasan_santri.per_status.pendaftaran', 1)
        ->assertJsonPath('data.ringkasan_santri.per_status.aktif', 0)
        ->assertJsonPath('data.ringkasan_pendaftaran.total', 1)
        ->assertJsonPath('data.ringkasan_pendaftaran.per_status.diajukan', 1)
        ->assertJsonPath('data.ringkasan_pendaftaran.per_status.diterima', 0);
});

/*
|--------------------------------------------------------------------------
| Ownership on read
|--------------------------------------------------------------------------
*/

test('a wali sees their own registrations and nobody else', function (): void {
    $this->actingAs($this->waliLain)
        ->postJson('/api/wali/pendaftaran', formPendaftaran(['nama_lengkap' => 'Milik Wali Lain']))
        ->assertCreated();

    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran())
        ->assertCreated();

    $response = $this->actingAs($this->wali)->getJson('/api/wali/pendaftaran');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.nama_santri', 'Ahmad Fauzi')
        ->assertJsonCount(1, 'data');

    $response->assertJsonMissing(['nama_santri' => 'Milik Wali Lain']);
});

test('a wali cannot open another wali registration', function (): void {
    $this->actingAs($this->waliLain)
        ->postJson('/api/wali/pendaftaran', formPendaftaran())
        ->assertCreated();

    $pendaftaranLain = Pendaftaran::query()->firstOrFail();

    // 404 and not 403. A 403 would confirm the id exists, which is itself a
    // disclosure about another guardian's account.
    $this->actingAs($this->wali)
        ->getJson("/api/wali/pendaftaran/{$pendaftaranLain->id}")
        ->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('data', null);

    // And the row is still there. Not found means "not yours to see", not
    // "gone".
    expect(Pendaftaran::query()->count())->toBe(1);
});

test('a wali can open their own registration', function (): void {
    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran(['nisn' => '2223334445']))
        ->assertCreated();

    $pendaftaran = Pendaftaran::query()->firstOrFail();

    $this->actingAs($this->wali)
        ->getJson("/api/wali/pendaftaran/{$pendaftaran->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $pendaftaran->id)
        ->assertJsonPath('data.nama_santri', 'Ahmad Fauzi')
        ->assertJsonPath('data.calon_santri.nisn', '2223334445')
        ->assertJsonPath('data.wali.id', $this->wali->id);
});

test('a registration id that does not exist is a 404 in the same envelope', function (): void {
    $this->actingAs($this->wali)
        ->getJson('/api/wali/pendaftaran/9999')
        ->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['success', 'message', 'data']);
});

test('the wali list is a plain array with no second data wrapper', function (): void {
    $this->actingAs($this->wali)->postJson('/api/wali/pendaftaran', formPendaftaran())->assertCreated();

    $this->actingAs($this->wali)
        ->getJson('/api/wali/pendaftaran')
        ->assertOk()
        ->assertJsonPath('data.0.id', Pendaftaran::query()->firstOrFail()->id)
        ->assertJsonMissingPath('data.data');
});

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

test('required fields are validated', function (): void {
    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'nama_lengkap',
            'jenis_kelamin',
            'tempat_lahir',
            'tanggal_lahir',
            'alamat',
        ])
        // The envelope carries the first real message, not Laravel's
        // "(and N more errors)" summary.
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Nama lengkap calon santri wajib diisi.');

    expect(Santri::query()->count())->toBe(0);
});

test('an unknown gender is rejected', function (): void {
    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran(['jenis_kelamin' => 'L']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['jenis_kelamin']);

    expect(Santri::query()->count())->toBe(0);
});

test('a birth date in the future is rejected', function (): void {
    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran([
            'tanggal_lahir' => now()->addYear()->toDateString(),
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['tanggal_lahir']);
});

test('a malformed nisn is rejected', function (): void {
    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran(['nisn' => '12345']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['nisn']);
});

test('a nisn already on file is rejected', function (): void {
    Santri::factory()->create(['nisn' => '9876543210']);

    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran(['nisn' => '9876543210']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['nisn']);

    expect(Santri::query()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Duplicate submissions
|--------------------------------------------------------------------------
*/

test('submitting the same student twice is refused', function (): void {
    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran())
        ->assertCreated();

    // The double click. The second request carries an identical body, so the
    // only thing standing between it and a duplicate is the server.
    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['nama_lengkap'])
        ->assertJsonPath('success', false);

    expect(Santri::query()->count())->toBe(1)
        ->and(Pendaftaran::query()->count())->toBe(1);
});

test('a duplicate is refused even when no registration exists for the student', function (): void {
    // A student row with nothing pointing at it: the identity is already taken,
    // which is the thing being checked, not the registration.
    Santri::factory()->untukWali($this->wali)->create([
        'nama_lengkap' => 'Ahmad Fauzi',
        'tanggal_lahir' => '2013-05-17',
    ]);

    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['nama_lengkap']);
});

test('a different child can still be registered', function (): void {
    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran())
        ->assertCreated();

    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran([
            'nama_lengkap' => 'Ahmad Rizky',
            'tanggal_lahir' => '2015-01-02',
        ]))
        ->assertCreated();

    expect(Pendaftaran::query()->count())->toBe(2);
});

test('a student of another wali is not a duplicate', function (): void {
    $this->actingAs($this->waliLain)
        ->postJson('/api/wali/pendaftaran', formPendaftaran())
        ->assertCreated();

    // Two guardians can each have a child called the same thing. The check is
    // scoped to one account, so neither is blocked by the other.
    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran())
        ->assertCreated();

    expect(Pendaftaran::query()->count())->toBe(2);
});

test('a previously rejected student can be registered again', function (): void {
    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran())
        ->assertCreated();

    $santri = Santri::query()->firstOrFail();
    $santri->update(['status' => SantriStatus::Ditolak]);

    // A rejected student has left the pipeline, so a fresh attempt is a new
    // application rather than a repeated one.
    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran())
        ->assertCreated();

    expect(Pendaftaran::query()->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Generated numbers
|--------------------------------------------------------------------------
*/

test('each registration receives a distinct registration number and nis', function (): void {
    $this->actingAs($this->wali)->postJson('/api/wali/pendaftaran', formPendaftaran())->assertCreated();
    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran(['nama_lengkap' => 'Ahmad Rizky', 'tanggal_lahir' => '2015-01-02']))
        ->assertCreated();
    $this->actingAs($this->waliLain)
        ->postJson('/api/wali/pendaftaran', formPendaftaran(['nama_lengkap' => 'Siti Aminah']))
        ->assertCreated();

    $nomor = Pendaftaran::query()->pluck('nomor_pendaftaran');
    $nis = Santri::query()->pluck('nis');

    expect($nomor)->toHaveCount(3)
        ->and($nomor->unique())->toHaveCount(3)
        ->and($nis->unique())->toHaveCount(3);

    foreach ($nomor as $value) {
        expect($value)->toMatch('/^PDF-\d{2}-\d{5}$/');
    }

    foreach ($nis as $value) {
        expect($value)->toMatch('/^P\d{7}$/');
    }
});

test('a deleted registration number is not handed out again', function (): void {
    foreach (['Ahmad Fauzi', 'Ahmad Rizky', 'Ahmad Wawan'] as $index => $nama) {
        $this->actingAs($this->wali)
            ->postJson('/api/wali/pendaftaran', formPendaftaran([
                'nama_lengkap' => $nama,
                'tanggal_lahir' => sprintf('201%d-01-0%d', $index + 1, $index + 1),
            ]))
            ->assertCreated();
    }

    $terhapus = Pendaftaran::query()->orderBy('id')->skip(1)->firstOrFail();
    $nomorTerhapus = $terhapus->nomor_pendaftaran;
    $terhapus->delete();

    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran([
            'nama_lengkap' => 'Ahmad Baru',
            'tanggal_lahir' => '2016-01-01',
        ]))
        ->assertCreated();

    // A gap in the sequence is the correct outcome: the number is a receipt, so
    // skipping one is safe and reissuing one is not.
    expect(Pendaftaran::query()->pluck('nomor_pendaftaran')->all())
        ->not->toContain($nomorTerhapus)
        ->and(Pendaftaran::query()->count())->toBe(3);
});

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/

test('an admin can list every registration', function (): void {
    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran())
        ->assertCreated();
    $this->actingAs($this->waliLain)
        ->postJson('/api/wali/pendaftaran', formPendaftaran(['nama_lengkap' => 'Siti Aminah']))
        ->assertCreated();

    $this->actingAs($this->admin)
        ->getJson('/api/admin/pendaftaran')
        ->assertOk()
        ->assertJsonPath('success', true)
        // The guardian is named on each row, which is the whole reason the
        // admin list exists.
        ->assertJsonPath('data.0.wali.name', 'Ibu Siti')
        ->assertJsonPath('data.1.wali.name', 'Bapak Ahmad')
        ->assertJsonPath('data.0.nama_santri', 'Siti Aminah')
        ->assertJsonCount(2, 'data');
});

test('an admin can open a registration detail with the student and guardian', function (): void {
    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran(['nisn' => '3344556677']))
        ->assertCreated();

    $pendaftaran = Pendaftaran::query()->firstOrFail();

    $this->actingAs($this->admin)
        ->getJson("/api/admin/pendaftaran/{$pendaftaran->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $pendaftaran->id)
        ->assertJsonPath('data.calon_santri.nisn', '3344556677')
        ->assertJsonPath('data.calon_santri.nama_lengkap', 'Ahmad Fauzi')
        ->assertJsonPath('data.wali.id', $this->wali->id)
        ->assertJsonPath('data.wali.email', $this->wali->email)
        ->assertJsonPath('data.status', 'diajukan');
});

test('a guest cannot reach the admin registration list', function (): void {
    $this->getJson('/api/admin/pendaftaran')->assertUnauthorized();
});

test('a wali cannot reach the admin registration endpoints', function (): void {
    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran())
        ->assertCreated();

    $pendaftaran = Pendaftaran::query()->firstOrFail();

    $this->actingAs($this->wali)->getJson('/api/admin/pendaftaran')->assertForbidden();
    $this->actingAs($this->wali)
        ->getJson("/api/admin/pendaftaran/{$pendaftaran->id}")
        ->assertForbidden();
});

test('there is no bare admin verb that can change a registration', function (): void {
    // The only writer is `PUT api/admin/pendaftaran/{id}/status`. A bare PUT or
    // DELETE on the resource would change a status while skipping the invoice,
    // history row, and student transition that belong with an acceptance, so
    // its absence is asserted rather than assumed.
    $route = collect(app('router')->getRoutes())->first(
        fn ($route): bool => $route->uri() === 'api/admin/pendaftaran/{pendaftaran}'
            && in_array('PUT', $route->methods(), true),
    );

    expect($route)->toBeNull();

    // 405 rather than 404: the uri exists for reading, so Laravel reports the
    // verb as unsupported instead of pretending the resource is unknown. Either
    // way the answer is that no write happened.
    $this->actingAs($this->admin)
        ->putJson('/api/admin/pendaftaran/1', ['status' => 'diterima'])
        ->assertStatus(405);

    expect(Pendaftaran::query()->count())->toBe(0);
});

test('an unknown admin registration is a 404 in the same envelope', function (): void {
    $this->actingAs($this->admin)
        ->getJson('/api/admin/pendaftaran/9999')
        ->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['success', 'message', 'data']);
});

/*
|--------------------------------------------------------------------------
| Reported payment position
|--------------------------------------------------------------------------
*/

test('the reported payment position counts only that academic year', function (): void {
    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran())
        ->assertCreated();

    $pendaftaran = Pendaftaran::query()->firstOrFail();

    // A payment from a different year must not make this registration look
    // paid, which is the failure mode of ignoring the year filter.
    Pembayaran::factory()->create([
        'santri_id' => $pendaftaran->santri_id,
        'tahun_ajaran_id' => $this->tahunAjaranLama->id,
        'status' => 'lunas',
    ]);

    $this->actingAs($this->wali)
        ->getJson('/api/wali/pendaftaran')
        ->assertOk()
        ->assertJsonPath('data.0.ringkasan_pembayaran.total', 0)
        ->assertJsonPath('data.0.ringkasan_pembayaran.status', 'belum');
});

test('a fully paid student is reported as paid without claiming which fee', function (): void {
    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran())
        ->assertCreated();

    $pendaftaran = Pendaftaran::query()->firstOrFail();

    Pembayaran::factory()->create([
        'santri_id' => $pendaftaran->santri_id,
        'tahun_ajaran_id' => $this->tahunAjaran->id,
        'status' => 'lunas',
    ]);

    $this->actingAs($this->wali)
        ->getJson("/api/wali/pendaftaran/{$pendaftaran->id}")
        ->assertOk()
        ->assertJsonPath('data.ringkasan_pembayaran.total', 1)
        ->assertJsonPath('data.ringkasan_pembayaran.lunas', 1)
        ->assertJsonPath('data.ringkasan_pembayaran.status', 'lunas');
});

test('a partly paid student is not reported as paid', function (): void {
    $this->actingAs($this->wali)
        ->postJson('/api/wali/pendaftaran', formPendaftaran())
        ->assertCreated();

    $pendaftaran = Pendaftaran::query()->firstOrFail();

    Pembayaran::factory()->count(2)->create([
        'santri_id' => $pendaftaran->santri_id,
        'tahun_ajaran_id' => $this->tahunAjaran->id,
        'status' => 'lunas',
    ]);

    Pembayaran::factory()->create([
        'santri_id' => $pendaftaran->santri_id,
        'tahun_ajaran_id' => $this->tahunAjaran->id,
        'status' => 'belum',
    ]);

    $this->actingAs($this->wali)
        ->getJson("/api/wali/pendaftaran/{$pendaftaran->id}")
        ->assertOk()
        ->assertJsonPath('data.ringkasan_pembayaran.total', 3)
        ->assertJsonPath('data.ringkasan_pembayaran.lunas', 2)
        ->assertJsonPath('data.ringkasan_pembayaran.status', 'belum');
});
