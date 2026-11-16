<?php

use App\Enums\StatusPembayaran;
use App\Models\AuditLog;
use App\Models\Dokumen;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    $this->wali = User::factory()->wali()->create();
    $this->admin = User::factory()->admin()->create();
});

function filePdf(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('berkas.pdf', "%PDF-1.4\n%%EOF\n");
}

function buatSantriSiapUntukPembayaran(User $wali): array
{
    $santri = Santri::factory()->menungguPembayaran()->untukWali($wali)->create();
    $pendaftaran = Pendaftaran::factory()->diterima()->create(['santri_id' => $santri->id]);
    Pembayaran::factory()->create([
        'santri_id' => $santri->id,
        'tahun_ajaran_id' => $santri->tahun_ajaran_id,
        'pendaftaran_id' => $pendaftaran->id,
        'jenis_pembayaran' => 'pendaftaran',
        'jumlah' => '500000.00',
        'status' => StatusPembayaran::Belum,
    ]);

    return [$santri, $pendaftaran];
}

test('wali can list and upload required documents', function (): void {
    [$santri, $pendaftaran] = buatSantriSiapUntukPembayaran($this->wali);

    $this->actingAs($this->wali)
        ->getJson("/api/wali/pendaftaran/{$pendaftaran->id}/dokumen")
        ->assertOk()
        ->assertJsonPath('data.kelengkapan.total', 5)
        ->assertJsonPath('data.kelengkapan.belum', 5);

    $this->actingAs($this->wali)
        ->post("/api/wali/pendaftaran/{$pendaftaran->id}/dokumen/kk", ['berkas' => filePdf()])
        ->assertStatus(201)
        ->assertJsonPath('data.status', 'menunggu');

    $dokumen = Dokumen::query()->where('santri_id', $santri->id)->firstOrFail();
    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $this->wali->id,
        'action' => 'dokumen.uploaded',
        'subject_type' => Dokumen::class,
        'subject_id' => $dokumen->id,
    ]);
    $audit = AuditLog::query()->where('subject_id', $dokumen->id)->sole();
    expect($audit->new_values)->toBe(['jenis' => 'kk', 'status' => 'menunggu']);
});

test('admin can review documents and activate when complete', function (): void {
    [$santri, $pendaftaran] = buatSantriSiapUntukPembayaran($this->wali);
    $fee = Pembayaran::query()->where('santri_id', $santri->id)->first();

    // Upload all required
    foreach (['kk', 'akta_lahir', 'ijazah', 'identitas_wali', 'pas_foto'] as $j) {
        $this->actingAs($this->wali)->post("/api/wali/pendaftaran/{$pendaftaran->id}/dokumen/{$j}", ['berkas' => filePdf()])->assertStatus(201);
    }

    // Approve all
    $docs = Dokumen::query()->where('santri_id', $santri->id)->get();
    foreach ($docs as $d) {
        $this->actingAs($this->admin)->putJson("/api/admin/dokumen/{$d->id}/status", ['status' => 'diterima'])->assertOk();
    }

    // Upload and approve fee
    $this->actingAs($this->wali)->postJson("/api/wali/pembayaran/{$fee->id}/bukti", ['bukti' => filePdf()])->assertOk();
    $this->actingAs($this->admin)->putJson("/api/admin/pembayaran/{$fee->id}/status", ['status' => 'lunas'])->assertOk();

    $santri->refresh();
    expect($santri->status->value ?? $santri->status)->toBe('aktif');
    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $this->admin->id,
        'action' => 'dokumen.approved',
        'subject_type' => Dokumen::class,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $this->admin->id,
        'action' => 'santri.activated',
        'subject_type' => Santri::class,
        'subject_id' => $santri->id,
    ]);
    expect(AuditLog::query()->where('action', 'santri.activated')->count())->toBe(1);
});

test('cannot upload bukti_pembayaran via dokumen endpoint', function (): void {
    [$santri, $pendaftaran] = buatSantriSiapUntukPembayaran($this->wali);
    $this->actingAs($this->wali)->post("/api/wali/pendaftaran/{$pendaftaran->id}/dokumen/bukti_pembayaran", ['berkas' => filePdf()])->assertStatus(404);
});

test('cannot replace accepted document', function (): void {
    [$santri, $pendaftaran] = buatSantriSiapUntukPembayaran($this->wali);
    $this->actingAs($this->wali)->post("/api/wali/pendaftaran/{$pendaftaran->id}/dokumen/kk", ['berkas' => filePdf()])->assertStatus(201);
    $d = Dokumen::query()->where('santri_id', $santri->id)->where('jenis', 'kk')->first();
    $this->actingAs($this->admin)->putJson("/api/admin/dokumen/{$d->id}/status", ['status' => 'diterima'])->assertOk();
    $this->actingAs($this->wali)->post("/api/wali/pendaftaran/{$pendaftaran->id}/dokumen/kk", ['berkas' => filePdf()])->assertStatus(422);
});

test('activation waits for documents', function (): void {
    [$santri, $pendaftaran] = buatSantriSiapUntukPembayaran($this->wali);
    $fee = Pembayaran::query()->where('santri_id', $santri->id)->first();

    $this->actingAs($this->wali)->postJson("/api/wali/pembayaran/{$fee->id}/bukti", ['bukti' => filePdf()])->assertOk();
    $this->actingAs($this->admin)->putJson("/api/admin/pembayaran/{$fee->id}/status", ['status' => 'lunas'])->assertOk();

    $santri->refresh();
    expect($santri->status->value ?? $santri->status)->toBe('menunggu_pembayaran');
});

test('admin approval of a pending document writes one audit entry and cannot be repeated', function (): void {
    [$santri, $pendaftaran] = buatSantriSiapUntukPembayaran($this->wali);

    $this->actingAs($this->wali)
        ->post("/api/wali/pendaftaran/{$pendaftaran->id}/dokumen/kk", ['berkas' => filePdf()])
        ->assertStatus(201);

    $dokumen = Dokumen::query()->where('santri_id', $santri->id)->where('jenis', 'kk')->firstOrFail();

    $this->actingAs($this->admin)
        ->putJson("/api/admin/dokumen/{$dokumen->id}/status", ['status' => 'diterima'])
        ->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $this->admin->id,
        'action' => 'dokumen.approved',
        'subject_type' => Dokumen::class,
        'subject_id' => $dokumen->id,
    ]);

    // The decision is final; repeating it is refused before any audit is written.
    $this->actingAs($this->admin)
        ->putJson("/api/admin/dokumen/{$dokumen->id}/status", ['status' => 'diterima'])
        ->assertStatus(422);

    expect(AuditLog::query()->where('action', 'dokumen.approved')->count())->toBe(1);
});

test('admin rejection writes the rejection audit', function (): void {
    [$santri, $pendaftaran] = buatSantriSiapUntukPembayaran($this->wali);

    $this->actingAs($this->wali)
        ->post("/api/wali/pendaftaran/{$pendaftaran->id}/dokumen/kk", ['berkas' => filePdf()])
        ->assertStatus(201);

    $dokumen = Dokumen::query()->where('santri_id', $santri->id)->where('jenis', 'kk')->firstOrFail();

    $this->actingAs($this->admin)
        ->putJson("/api/admin/dokumen/{$dokumen->id}/status", ['status' => 'ditolak', 'catatan' => 'Berkas tidak sesuai.'])
        ->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $this->admin->id,
        'action' => 'dokumen.rejected',
        'subject_type' => Dokumen::class,
        'subject_id' => $dokumen->id,
    ]);
});
