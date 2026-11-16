<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditTrailService;
use Illuminate\Support\Facades\DB;

test('a guest receives 401 from audit trail list and detail', function (): void {
    $auditLog = AuditLog::query()->create([
        'action' => 'pengguna.update',
        'description' => 'Memperbarui pengguna',
    ]);

    $this->getJson('/api/admin/audit-trail')->assertUnauthorized();
    $this->getJson("/api/admin/audit-trail/{$auditLog->id}")->assertUnauthorized();
});

test('a wali receives 403 from audit trail list and detail', function (): void {
    $wali = User::factory()->wali()->create();
    $auditLog = AuditLog::query()->create([
        'action' => 'pengguna.update',
        'description' => 'Memperbarui pengguna',
    ]);

    $this->actingAs($wali)->getJson('/api/admin/audit-trail')->assertForbidden();
    $this->actingAs($wali)->getJson("/api/admin/audit-trail/{$auditLog->id}")->assertForbidden();
});

test('an admin can view the audit trail list', function (): void {
    $admin = User::factory()->admin()->create(['name' => 'Admin Audit']);
    $auditLog = AuditLog::query()->create([
        'user_id' => $admin->id,
        'action' => 'pengguna.update',
        'description' => 'Memperbarui pengguna',
        'subject_type' => User::class,
        'subject_id' => $admin->id,
        'created_at' => '2026-11-01 10:00:00',
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin/audit-trail')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.id', $auditLog->id)
        ->assertJsonPath('data.0.action', 'pengguna.update')
        ->assertJsonPath('data.0.pelaku.name', 'Admin Audit')
        ->assertJsonMissingPath('data.0.pelaku.email')
        ->assertJsonPath('meta.total', 1);
});

test('an admin can view an audit entry detail', function (): void {
    $admin = User::factory()->admin()->create();
    $auditLog = AuditLog::query()->create([
        'user_id' => $admin->id,
        'action' => 'pengguna.update',
        'description' => 'Memperbarui pengguna',
        'old_values' => ['name' => 'Nama Lama'],
        'new_values' => ['name' => 'Nama Baru'],
    ]);

    $this->actingAs($admin)
        ->getJson("/api/admin/audit-trail/{$auditLog->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $auditLog->id)
        ->assertJsonPath('data.old_values.name', 'Nama Lama')
        ->assertJsonPath('data.new_values.name', 'Nama Baru')
        ->assertJsonPath('data.pelaku.id', $admin->id)
        ->assertJsonMissingPath('data.pelaku.email');
});

test('an admin can filter audit entries by user', function (): void {
    $admin = User::factory()->admin()->create();
    $otherUser = User::factory()->wali()->create();
    AuditLog::query()->create(['user_id' => $admin->id, 'action' => 'login']);
    $matchingLog = AuditLog::query()->create(['user_id' => $otherUser->id, 'action' => 'logout']);

    $this->actingAs($admin)
        ->getJson("/api/admin/audit-trail?user_id={$otherUser->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchingLog->id)
        ->assertJsonPath('data.0.pelaku.id', $otherUser->id);
});

test('an admin can filter audit entries by activity type', function (): void {
    $admin = User::factory()->admin()->create();
    AuditLog::query()->create(['user_id' => $admin->id, 'action' => 'pengguna.update']);
    $matchingLog = AuditLog::query()->create(['user_id' => $admin->id, 'action' => 'pembayaran.status']);

    $this->actingAs($admin)
        ->getJson('/api/admin/audit-trail?action=pembayaran')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchingLog->id)
        ->assertJsonPath('data.0.action', 'pembayaran.status');
});

test('an admin can filter audit entries by date range', function (): void {
    $admin = User::factory()->admin()->create();
    AuditLog::query()->create([
        'user_id' => $admin->id,
        'action' => 'sebelum',
        'created_at' => '2026-10-31 23:59:59',
    ]);
    $matchingLog = AuditLog::query()->create([
        'user_id' => $admin->id,
        'action' => 'dalam-rentang',
        'created_at' => '2026-11-01 12:00:00',
    ]);
    AuditLog::query()->create([
        'user_id' => $admin->id,
        'action' => 'sesudah',
        'created_at' => '2026-11-02 00:00:01',
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin/audit-trail?from=2026-11-01&to=2026-11-01')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchingLog->id);
});

test('an invalid audit date range is rejected', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->getJson('/api/admin/audit-trail?from=2026-11-02&to=2026-11-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to']);
});

test('invalid audit pagination is rejected', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->getJson('/api/admin/audit-trail?page=0')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['page']);

    $this->getJson('/api/admin/audit-trail?per_page=101')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page']);
});

test('audit trail pagination returns the requested page', function (): void {
    $admin = User::factory()->admin()->create();
    $firstLog = AuditLog::query()->create([
        'user_id' => $admin->id,
        'action' => 'aksi-pertama',
        'created_at' => '2026-10-29 10:00:00',
    ]);
    AuditLog::query()->create([
        'user_id' => $admin->id,
        'action' => 'aksi-kedua',
        'created_at' => '2026-10-30 10:00:00',
    ]);
    AuditLog::query()->create([
        'user_id' => $admin->id,
        'action' => 'aksi-ketiga',
        'created_at' => '2026-10-31 10:00:00',
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin/audit-trail?per_page=2&page=2')
        ->assertOk()
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 3)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $firstLog->id);
});

test('audit entries remain readable when their actor has been deleted', function (): void {
    $admin = User::factory()->admin()->create();
    $formerActor = User::factory()->wali()->create();
    $auditLog = AuditLog::query()->create([
        'user_id' => $formerActor->id,
        'action' => 'pengguna.update',
    ]);
    $formerActor->delete();

    $this->actingAs($admin)
        ->getJson("/api/admin/audit-trail/{$auditLog->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $auditLog->id)
        ->assertJsonPath('data.pelaku', null);
});

test('audit responses exclude secrets and private document data', function (): void {
    $admin = User::factory()->admin()->create();
    $auditLog = AuditLog::query()->create([
        'user_id' => $admin->id,
        'action' => 'pengguna.update',
        'old_values' => [
            'name' => 'Nama Tercatat',
            'password' => 'password-plaintext-secret',
            'password_hash' => 'password-hash-secret',
            'remember_token' => 'remember-token-secret',
            'api_token' => 'api-token-secret',
            'session_id' => 'session-id-secret',
            'cookie' => 'cookie-secret',
            'dokumen' => ['isi' => 'private-document-content'],
            'bukti_path' => 'private-document-path',
            'email' => 'rahasia@example.test',
            'no_hp' => '081200000001',
            'nik' => '3273000000000001',
            'nis' => 'NIS-RAHASIA',
            'nisn' => 'NISN-RAHASIA',
            'alamat' => 'Alamat Rahasia',
            'tanggal_lahir' => '2010-01-01',
            'jenis' => 'kk',
            'nested' => [
                'access_token' => 'nested-token-secret',
                'email' => 'nested@example.test',
                'jenis' => 'kk',
            ],
        ],
    ]);

    $this->actingAs($admin)
        ->getJson("/api/admin/audit-trail/{$auditLog->id}")
        ->assertOk()
        ->assertJsonPath('data.old_values.name', 'Nama Tercatat')
        ->assertJsonMissingPath('data.old_values.password')
        ->assertJsonMissingPath('data.old_values.password_hash')
        ->assertJsonMissingPath('data.old_values.remember_token')
        ->assertJsonMissingPath('data.old_values.api_token')
        ->assertJsonMissingPath('data.old_values.session_id')
        ->assertJsonMissingPath('data.old_values.cookie')
        ->assertJsonMissingPath('data.old_values.dokumen')
        ->assertJsonMissingPath('data.old_values.bukti_path')
        ->assertJsonMissingPath('data.old_values.email')
        ->assertJsonMissingPath('data.old_values.no_hp')
        ->assertJsonMissingPath('data.old_values.nik')
        ->assertJsonMissingPath('data.old_values.nis')
        ->assertJsonMissingPath('data.old_values.nisn')
        ->assertJsonMissingPath('data.old_values.alamat')
        ->assertJsonMissingPath('data.old_values.tanggal_lahir')
        ->assertJsonPath('data.old_values.jenis', 'kk')
        ->assertJsonMissingPath('data.old_values.nested.access_token')
        ->assertJsonMissingPath('data.old_values.nested.email')
        ->assertJsonPath('data.old_values.nested.jenis', 'kk')
        ->assertDontSee('password-plaintext-secret')
        ->assertDontSee('password-hash-secret')
        ->assertDontSee('remember-token-secret')
        ->assertDontSee('api-token-secret')
        ->assertDontSee('session-id-secret')
        ->assertDontSee('cookie-secret')
        ->assertDontSee('private-document-content')
        ->assertDontSee('private-document-path')
        ->assertDontSee('rahasia@example.test')
        ->assertDontSee('081200000001')
        ->assertDontSee('3273000000000001')
        ->assertDontSee('NIS-RAHASIA')
        ->assertDontSee('NISN-RAHASIA')
        ->assertDontSee('Alamat Rahasia')
        ->assertDontSee('2010-01-01')
        ->assertDontSee('nested-token-secret')
        ->assertDontSee('nested@example.test');
});

test('audit service recursively excludes sensitive metadata keys', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    app(AuditTrailService::class)->log(
        'test.sanitization',
        'Audit metadata sanitization.',
        newValues: [
            'status' => 'diterima',
            'password_hash' => 'hash-secret',
            'email' => 'rahasia@example.test',
            'no_hp' => '081200000001',
            'nik' => '3273000000000001',
            'nis' => 'NIS-RAHASIA',
            'nisn' => 'NISN-RAHASIA',
            'alamat' => 'Alamat Rahasia',
            'tanggal_lahir' => '2010-01-01',
            'jenis' => 'kk',
            'nested' => [
                'session_id' => 'session-secret',
                'bukti_path' => 'private/path.pdf',
                'email' => 'nested@example.test',
                'jenis' => 'kk',
                'count' => 2,
            ],
        ],
    );

    $audit = AuditLog::query()->where('action', 'test.sanitization')->sole();

    expect($audit->new_values)->toBe([
        'status' => 'diterima',
        'jenis' => 'kk',
        'nested' => ['jenis' => 'kk', 'count' => 2],
    ]);
});

test('audit entries roll back with their surrounding business transaction', function (): void {
    $audit = app(AuditTrailService::class);

    expect(fn () => DB::transaction(function () use ($audit): void {
        $audit->log(
            'test.rollback',
            'This entry must not survive the rollback.',
            newValues: ['status' => 'diterima'],
        );

        throw new RuntimeException('Force the transaction to roll back.');
    }))->toThrow(RuntimeException::class);

    expect(AuditLog::query()->where('action', 'test.rollback')->count())->toBe(0);
});
