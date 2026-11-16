<?php

use App\Enums\JenisPembayaran;
use App\Enums\StatusPembayaran;
use App\Models\HistoriStatus;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\Pengaturan;
use App\Models\Santri;
use App\Models\TahunAjaran;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Admin decision on a registration (Phase 6)
|--------------------------------------------------------------------------
|
| The claims defended here are the accept/reject half of Phase 6:
|
|   - the admin is the only actor with a verdict verb (guardian: 403, guest:
|     401, and nothing but DITERIMA or DITOLAK is a legal answer);
|   - accepting opens the payment lane: a `pendaftaran` fee is created from
|     the school's `biaya_pendaftaran` setting, and the student waits for that
|     fee — accepting is **never** activating;
|   - rejecting requires and stores a reason the guardian can read back, and
|     ends the row; a decision is final and cannot be unmade.
|
*/
beforeEach(function (): void {
    $this->tahunAjaran = TahunAjaran::factory()->aktif()->create();

    Pengaturan::write('biaya_pendaftaran', '500000');

    $this->wali = User::factory()->wali()->create(['name' => 'Bapak Ahmad']);
    $this->admin = User::factory()->admin()->create();
});

/*
|--------------------------------------------------------------------------
| Accepting
|--------------------------------------------------------------------------
*/

test('admin accepting an undecided registration opens the payment lane without activating the student', function (): void {
    $santri = Santri::factory()->untukWali($this->wali)->create();
    $pendaftaran = Pendaftaran::factory()->create(['santri_id' => $santri->id]);

    $this->actingAs($this->admin)
        ->putJson("/api/admin/pendaftaran/{$pendaftaran->id}/status", ['status' => 'diterima'])
        ->assertOk()
        ->assertJsonPath('data.status', 'diterima')
        ->assertJsonPath('data.santri_status', 'menunggu_pembayaran')
        ->assertJsonPath('data.biaya_pendaftaran.status', 'belum')
        ->assertJsonPath('data.biaya_pendaftaran.jumlah', '500000.00');

    $this->assertDatabaseHas('pendaftaran', [
        'id' => $pendaftaran->id,
        'status' => 'diterima',
        'diproses_oleh' => $this->admin->id,
        'alasan_ditolak' => null,
    ]);

    $this->assertDatabaseHas('santris', [
        'id' => $santri->id,
        'status' => 'menunggu_pembayaran',
    ]);

    $this->assertDatabaseHas('pembayarans', [
        'santri_id' => $santri->id,
        'tahun_ajaran_id' => $santri->tahun_ajaran_id,
        'pendaftaran_id' => $pendaftaran->id,
        'jenis_pembayaran' => 'pendaftaran',
        'status' => 'belum',
        'jumlah' => '500000.00',
    ]);

    // Histori records the move into the payment lane.
    $this->assertDatabaseHas('histori_status', [
        'santri_id' => $santri->id,
        'dari_status' => 'pendaftaran',
        'ke_status' => 'menunggu_pembayaran',
        'oleh' => $this->admin->id,
    ]);
    expect(HistoriStatus::query()->where('santri_id', $santri->id)->count())->toBe(1);
    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $this->admin->id,
        'action' => 'pendaftaran.accepted',
        'subject_type' => Pendaftaran::class,
        'subject_id' => $pendaftaran->id,
    ]);
});

test('admin accepting a divergen registration opens the same lane', function (): void {
    $santri = Santri::factory()->untukWali($this->wali)->create();
    $pendaftaran = Pendaftaran::factory()->diverifikasi()->create(['santri_id' => $santri->id]);

    $this->actingAs($this->admin)
        ->putJson("/api/admin/pendaftaran/{$pendaftaran->id}/status", ['status' => 'diterima'])
        ->assertOk();

    $this->assertDatabaseHas('pendaftaran', ['id' => $pendaftaran->id, 'status' => 'diterima']);
    $this->assertDatabaseHas('santris', ['id' => $santri->id, 'status' => 'menunggu_pembayaran']);
    $this->assertDatabaseHas('pembayarans', ['santri_id' => $santri->id, 'jenis_pembayaran' => 'pendaftaran']);
});

test('accepting does not create a second fee when one already exists, and backfills its link', function (): void {
    $santri = Santri::factory()->untukWali($this->wali)->create();
    $pendaftaran = Pendaftaran::factory()->create(['santri_id' => $santri->id]);

    // A legacy Phase 2 payment row, present before Phase 6 linked things up.
    Pembayaran::factory()->create([
        'santri_id' => $santri->id,
        'tahun_ajaran_id' => $santri->tahun_ajaran_id,
        'jenis_pembayaran' => JenisPembayaran::Pendaftaran,
        'pendaftaran_id' => null,
        'status' => StatusPembayaran::Belum,
    ]);

    $this->actingAs($this->admin)
        ->putJson("/api/admin/pendaftaran/{$pendaftaran->id}/status", ['status' => 'diterima'])
        ->assertOk();

    $this->assertDatabaseHas('pembayarans', ['pendaftaran_id' => $pendaftaran->id]);
    expect(Pembayaran::query()->where('santri_id', $santri->id)->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Rejecting
|--------------------------------------------------------------------------
*/

test('admin rejecting stores an alasan the guardian can read and ends the row', function (): void {
    $santri = Santri::factory()->untukWali($this->wali)->create();
    $pendaftaran = Pendaftaran::factory()->create(['santri_id' => $santri->id]);

    $this->actingAs($this->admin)
        ->putJson("/api/admin/pendaftaran/{$pendaftaran->id}/status", [
            'status' => 'ditolak',
            'alasan_ditolak' => 'Berkas calon santri tidak sesuai ketentuan.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'ditolak')
        ->assertJsonPath('data.alasan_ditolak', 'Berkas calon santri tidak sesuai ketentuan.')
        ->assertJsonPath('data.santri_status', 'ditolak')
        // No payment lane was opened for a rejection.
        ->assertJsonPath('data.biaya_pendaftaran', null);

    $this->assertDatabaseHas('pendaftaran', [
        'id' => $pendaftaran->id,
        'status' => 'ditolak',
        'alasan_ditolak' => 'Berkas calon santri tidak sesuai ketentuan.',
    ]);
    $this->assertDatabaseHas('santris', ['id' => $santri->id, 'status' => 'ditolak']);
    $this->assertDatabaseMissing('pembayarans', ['santri_id' => $santri->id]);
    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $this->admin->id,
        'action' => 'pendaftaran.rejected',
        'subject_id' => $pendaftaran->id,
    ]);
});

test('rejecting without an alasan is a 422', function (): void {
    $santri = Santri::factory()->untukWali($this->wali)->create();
    $pendaftaran = Pendaftaran::factory()->create(['santri_id' => $santri->id]);

    $this->actingAs($this->admin)
        ->putJson("/api/admin/pendaftaran/{$pendaftaran->id}/status", ['status' => 'ditolak'])
        ->assertStatus(422);

    $this->assertDatabaseHas('pendaftaran', ['id' => $pendaftaran->id, 'status' => 'diajukan']);
    $this->assertDatabaseMissing('audit_logs', ['subject_id' => $pendaftaran->id]);
});

test('accepting with an alasan is a 422', function (): void {
    $santri = Santri::factory()->untukWali($this->wali)->create();
    $pendaftaran = Pendaftaran::factory()->create(['santri_id' => $santri->id]);

    $this->actingAs($this->admin)
        ->putJson("/api/admin/pendaftaran/{$pendaftaran->id}/status", [
            'status' => 'diterima',
            'alasan_ditolak' => 'seharusnya tidak ada',
        ])
        ->assertStatus(422);

    $this->assertDatabaseHas('pendaftaran', ['id' => $pendaftaran->id, 'status' => 'diajukan']);
});

/*
|--------------------------------------------------------------------------
| The whitelist and the state machine
|--------------------------------------------------------------------------
*/

test('only diterima and ditolak are legal verdicts', function (string $ilegal): void {
    $santri = Santri::factory()->untukWali($this->wali)->create();
    $pendaftaran = Pendaftaran::factory()->create(['santri_id' => $santri->id]);

    $this->actingAs($this->admin)
        ->putJson("/api/admin/pendaftaran/{$pendaftaran->id}/status", ['status' => $ilegal])
        ->assertStatus(422);

    $this->assertDatabaseHas('pendaftaran', ['id' => $pendaftaran->id, 'status' => 'diajukan']);
})->with(['aktif', 'diajukan', 'diverifikasi', 'belum', 'menghilang']);

test('a decided registration cannot be decided again', function (): void {
    $santri = Santri::factory()->untukWali($this->wali)->create();
    $pendaftaran = Pendaftaran::factory()->diterima()->create(['santri_id' => $santri->id]);

    $this->actingAs($this->admin)
        ->putJson("/api/admin/pendaftaran/{$pendaftaran->id}/status", ['status' => 'ditolak'])
        ->assertStatus(422);

    $this->assertDatabaseHas('pendaftaran', ['id' => $pendaftaran->id, 'status' => 'diterima']);
});

/*
|--------------------------------------------------------------------------
| Who may decide
|--------------------------------------------------------------------------
*/

test('a guardian cannot decide a registration', function (): void {
    $santri = Santri::factory()->untukWali($this->wali)->create();
    $pendaftaran = Pendaftaran::factory()->create(['santri_id' => $santri->id]);

    $this->actingAs($this->wali)
        ->putJson("/api/admin/pendaftaran/{$pendaftaran->id}/status", ['status' => 'diterima'])
        ->assertForbidden();
});

test('a guest cannot decide a registration', function (): void {
    $santri = Santri::factory()->untukWali($this->wali)->create();
    $pendaftaran = Pendaftaran::factory()->create(['santri_id' => $santri->id]);

    $this->putJson("/api/admin/pendaftaran/{$pendaftaran->id}/status", ['status' => 'diterima'])
        ->assertUnauthorized();
});

test('there is no annotation route that activates a student directly', function (): void {
    $santri = Santri::factory()->untukWali($this->wali)->create();

    // The pipeline has no "make this student active" verb. Status can only move
    // as a consequence of the registration fee being verified.
    $this->actingAs($this->admin)
        ->putJson("/api/admin/santri/{$santri->id}/status", ['status' => 'aktif'])
        ->assertNotFound();
});
