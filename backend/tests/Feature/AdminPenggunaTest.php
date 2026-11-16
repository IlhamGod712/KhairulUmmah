<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('a guest receives 401 from every admin pengguna endpoint', function (): void {
    $pengguna = User::factory()->wali()->create();

    $this->getJson('/api/admin/pengguna')->assertUnauthorized();
    $this->getJson("/api/admin/pengguna/{$pengguna->id}")->assertUnauthorized();
    $this->putJson("/api/admin/pengguna/{$pengguna->id}", ['name' => 'Updated'])->assertUnauthorized();

    expect($pengguna->fresh()->name)->toBe($pengguna->name);
});

test('a wali receives 403 from every admin pengguna endpoint', function (): void {
    $wali = User::factory()->wali()->create();
    $pengguna = User::factory()->wali()->create();

    $this->actingAs($wali)->getJson('/api/admin/pengguna')->assertForbidden();
    $this->actingAs($wali)->getJson("/api/admin/pengguna/{$pengguna->id}")->assertForbidden();
    $this->actingAs($wali)->putJson("/api/admin/pengguna/{$pengguna->id}", ['name' => 'Updated'])->assertForbidden();

    expect($pengguna->fresh()->name)->not->toBe('Updated');
});

test('an admin can view a paginated list of users', function (): void {
    $admin = User::factory()->admin()->create(['name' => 'Admin A']);
    User::factory()->wali()->create(['name' => 'Wali B']);
    User::factory()->wali()->create(['name' => 'Wali C']);

    $response = $this->actingAs($admin)
        ->getJson('/api/admin/pengguna?per_page=2&page=2')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 3)
        ->assertJsonCount(1, 'data');

    expect($response->json('data.0.name'))->toBe('Wali C');
});

test('an admin can view a user detail with only permitted fields', function (): void {
    $admin = User::factory()->admin()->create();
    $pengguna = User::factory()->wali()->create([
        'name' => 'Wali Detail',
        'no_hp' => '08123456789',
    ]);

    $this->actingAs($admin)
        ->getJson("/api/admin/pengguna/{$pengguna->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $pengguna->id)
        ->assertJsonPath('data.name', 'Wali Detail')
        ->assertJsonPath('data.no_hp', '08123456789')
        ->assertJsonPath('data.role', 'wali')
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.remember_token')
        ->assertJsonMissingPath('data.tokens');
});

test('an admin can find users by name', function (): void {
    $admin = User::factory()->admin()->create();
    User::factory()->wali()->create(['name' => 'Ahmad Santri']);
    User::factory()->wali()->create(['name' => 'Budi Santri']);

    $this->actingAs($admin)
        ->getJson('/api/admin/pengguna?search=Ahmad')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Ahmad Santri');
});

test('an admin can find users by email', function (): void {
    $admin = User::factory()->admin()->create();
    User::factory()->wali()->create(['email' => 'wali.cari@example.test']);
    User::factory()->wali()->create(['email' => 'wali.lain@example.test']);

    $this->actingAs($admin)
        ->getJson('/api/admin/pengguna?search=cari@example.test')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', 'wali.cari@example.test');
});

test('an admin can update a user and the password is hashed', function (): void {
    $admin = User::factory()->admin()->create();
    $pengguna = User::factory()->wali()->create([
        'name' => 'Nama Lama',
        'email' => 'lama@example.test',
    ]);

    $response = $this->actingAs($admin)
        ->putJson("/api/admin/pengguna/{$pengguna->id}", [
            'name' => 'Nama Baru',
            'email' => ' BARU@EXAMPLE.TEST ',
            'no_hp' => '08123456789',
            'role' => 'admin',
            'password' => 'password-baru',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Nama Baru')
        ->assertJsonPath('data.email', 'baru@example.test')
        ->assertJsonPath('data.role', 'admin')
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.remember_token')
        ->assertJsonMissingPath('data.tokens');

    $pengguna->refresh();

    expect($pengguna->no_hp)->toBe('08123456789')
        ->and($pengguna->role)->toBe(UserRole::Admin)
        ->and(Hash::check('password-baru', $pengguna->password))->toBeTrue()
        ->and($response->json('data.email'))->toBe('baru@example.test');

    $audit = AuditLog::query()->where('action', 'pengguna.update')->sole();
    expect($audit->old_values)->not->toHaveKey('password')
        ->and($audit->new_values)->not->toHaveKey('password')
        ->and($audit->old_values)->not->toHaveKey('email')
        ->and($audit->new_values)->not->toHaveKey('email')
        ->and($audit->old_values)->not->toHaveKey('no_hp')
        ->and($audit->new_values)->not->toHaveKey('no_hp')
        ->and($audit->toJson())->not->toContain('lama@example.test')
        ->and($audit->toJson())->not->toContain('baru@example.test')
        ->and($audit->toJson())->not->toContain('08123456789');
});

test('updating a user without changes does not write an audit entry', function (): void {
    $admin = User::factory()->admin()->create();
    $pengguna = User::factory()->wali()->create(['name' => 'Nama Tetap']);

    $this->actingAs($admin)
        ->putJson("/api/admin/pengguna/{$pengguna->id}", ['name' => 'Nama Tetap'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Nama Tetap');

    expect(AuditLog::query()->count())->toBe(0);
});

test('an admin cannot update a user to an existing email', function (): void {
    $admin = User::factory()->admin()->create();
    $pengguna = User::factory()->wali()->create(['email' => 'wali@example.test']);
    User::factory()->wali()->create(['email' => 'sudah-ada@example.test']);

    $this->actingAs($admin)
        ->putJson("/api/admin/pengguna/{$pengguna->id}", ['email' => 'SUDAH-ADA@EXAMPLE.TEST'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    expect($pengguna->fresh()->email)->toBe('wali@example.test');
    expect(AuditLog::query()->count())->toBe(0);
});

test('an admin cannot demote their own account when they are the last admin', function (): void {
    $admin = User::factory()->admin()->create(['name' => 'Admin Utama']);

    $this->actingAs($admin)
        ->putJson("/api/admin/pengguna/{$admin->id}", [
            'name' => 'Nama Seharusnya Tidak Berubah',
            'role' => 'wali',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);

    expect($admin->fresh()->name)->toBe('Admin Utama')
        ->and($admin->fresh()->role)->toBe(UserRole::Admin)
        ->and(AuditLog::query()->count())->toBe(0);
});

test('user list and update responses never expose password or authentication tokens', function (): void {
    $admin = User::factory()->admin()->create();
    $pengguna = User::factory()->wali()->create();
    $plainTextToken = $pengguna->createToken('test-token')->plainTextToken;

    $list = $this->actingAs($admin)->getJson('/api/admin/pengguna')->assertOk();
    $detail = $this->getJson("/api/admin/pengguna/{$pengguna->id}")->assertOk();
    $update = $this->putJson("/api/admin/pengguna/{$pengguna->id}", ['name' => 'Nama Diperbarui'])->assertOk();

    foreach ([$list, $detail, $update] as $response) {
        $response->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token')
            ->assertJsonMissingPath('data.tokens')
            ->assertDontSee($pengguna->password)
            ->assertDontSee($pengguna->remember_token)
            ->assertDontSee($plainTextToken);
    }
});
