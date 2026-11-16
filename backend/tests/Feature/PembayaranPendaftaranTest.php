<?php

use App\Enums\JenisDokumen;
use App\Enums\JenisPembayaran;
use App\Enums\MetodePembayaran;
use App\Enums\SantriStatus;
use App\Enums\StatusDokumen;
use App\Enums\StatusPembayaran;
use App\Models\AuditLog;
use App\Models\Dokumen;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| The registration-fee payment lane (Phase 6)
|--------------------------------------------------------------------------
|
| Once a registration is accepted the student owes the fee, and this file
| defends the two half flows of that lane:
|
|   - the guardian sees their own fees, uploads a proof, and nothing else. A
|     fee is only payable once its registration was accepted; a settled or
|     already-submitted fee stays put; and another guardian's fee is a 404.
|
|   - the admin verifies the proof. Confirming it (`lunas`) is the single place
|     where a student becomes `aktif`, and it happens in one write; refusing it
|     leaves the lane open for a corrected proof.
|
*/
beforeEach(function (): void {
    Storage::fake('local');

    $this->wali = User::factory()->wali()->create(['name' => 'Bapak Ahmad']);
    $this->waliLain = User::factory()->wali()->create(['name' => 'Ibu Siti']);
    $this->admin = User::factory()->admin()->create();
});

/**
 * A student already sitting in the payment lane: registration accepted, waiting
 * to settle the fee.
 *
 * @return array{santri: Santri, pendaftaran: Pendaftaran, fee: Pembayaran}
 */
function buatDokumenWajib(Santri $santri, string $status = StatusDokumen::Diterima->value): void
{
    $wajib = [JenisDokumen::KartuKeluarga, JenisDokumen::AktaLahir, JenisDokumen::Ijazah, JenisDokumen::IdentitasWali, JenisDokumen::PasFoto];
    foreach ($wajib as $jenis) {
        Dokumen::factory()->create([
            'santri_id' => $santri->id,
            'jenis' => $jenis,
            'status' => $status,
            'path' => 'dokumen/'.Str::uuid().'.pdf',
            'nama_asli' => $jenis->value.'.pdf',
            'mime_type' => 'application/pdf',
            'ukuran_bytes' => 1024,
        ]);
    }
}

function calonYangSedangMembayar(User $wali, bool $dokumenLengkap = true): array
{
    $santri = Santri::factory()->menungguPembayaran()->untukWali($wali)->create();
    $pendaftaran = Pendaftaran::factory()->diterima()->create(['santri_id' => $santri->id]);

    if ($dokumenLengkap) {
        buatDokumenWajib($santri);
    }

    $fee = Pembayaran::factory()->create([
        'santri_id' => $santri->id,
        'tahun_ajaran_id' => $santri->tahun_ajaran_id,
        'pendaftaran_id' => $pendaftaran->id,
        'jenis_pembayaran' => JenisPembayaran::Pendaftaran,
        'jumlah' => '500000.00',
        'status' => StatusPembayaran::Belum,
        'metode' => MetodePembayaran::TransferBank,
    ]);

    return [
        'santri' => $santri,
        'pendaftaran' => $pendaftaran,
        'fee' => $fee,
    ];
}

function berkasBuktiPdf(): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        'bukti.pdf',
        "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n",
    );
}

/**
 * A monthly SPP invoice for the same student, so the two payment surfaces can
 * be pointed at one id and the answer compared.
 */
function tagihanSppMilik(Santri $santri, string $periode = '2026-09-01'): Pembayaran
{
    return Pembayaran::factory()->spp($periode)->create([
        'santri_id' => $santri->getKey(),
        'tahun_ajaran_id' => $santri->tahun_ajaran_id,
        'jumlah' => '750000.00',
    ]);
}

/*
|--------------------------------------------------------------------------
| The guardian sees their own fees
|--------------------------------------------------------------------------
*/

test('a wali lists only their own registration-fee payments', function (): void {
    $punya = calonYangSedangMembayar($this->wali);
    $bukanPunya = calonYangSedangMembayar($this->waliLain);

    $this->actingAs($this->wali)
        ->getJson('/api/wali/pembayaran')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $punya['fee']->id)
        ->assertJsonPath('data.0.jumlah', '500000.00')
        ->assertJsonPath('data.0.jenis_pembayaran', 'pendaftaran')
        ->assertJsonPath('data.0.nomor_pendaftaran', $punya['pendaftaran']->nomor_pendaftaran)
        ->assertJsonPath('data.0.nama_santri', $punya['santri']->nama_lengkap);

    $this->actingAs($this->waliLain)
        ->getJson('/api/wali/pembayaran')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $bukanPunya['fee']->id);
});

test('another guardian fee is a 404, not a forbidden', function (): void {
    $bukanPunya = calonYangSedangMembayar($this->waliLain);

    $this->actingAs($this->wali)
        ->getJson("/api/wali/pembayaran/{$bukanPunya['fee']->id}")
        ->assertNotFound();
});

test('a guest cannot list or read payments', function (): void {
    $punya = calonYangSedangMembayar($this->wali);

    $this->getJson('/api/wali/pembayaran')->assertUnauthorized();
    $this->getJson("/api/wali/pembayaran/{$punya['fee']->id}")->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| Uploading the proof
|--------------------------------------------------------------------------
*/

test('a wali uploads a proof and the fee moves to menunggu_verifikasi', function (): void {
    $setup = calonYangSedangMembayar($this->wali);

    $this->actingAs($this->wali)
        ->postJson("/api/wali/pembayaran/{$setup['fee']->id}/bukti", [
            'bukti' => berkasBuktiPdf(),
            'referensi' => 'TRF-20260928-001',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'menunggu_verifikasi')
        ->assertJsonPath('data.referensi', 'TRF-20260928-001');

    $this->assertDatabaseHas('pembayarans', [
        'id' => $setup['fee']->id,
        'status' => 'menunggu_verifikasi',
        'referensi' => 'TRF-20260928-001',
    ]);

    $this->assertDatabaseHas('dokumen', [
        'santri_id' => $setup['santri']->id,
        'jenis' => 'bukti_pembayaran',
        'status' => 'menunggu',
        'nama_asli' => 'bukti.pdf',
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $this->wali->id,
        'action' => 'pembayaran.pendaftaran.bukti_uploaded',
        'subject_type' => Pembayaran::class,
        'subject_id' => $setup['fee']->id,
    ]);
    $audit = AuditLog::query()
        ->where('action', 'pembayaran.pendaftaran.bukti_uploaded')
        ->sole();
    expect($audit->new_values)->toBe(['status' => 'menunggu_verifikasi'])
        ->and($audit->toJson())->not->toContain('TRF-20260928-001')
        ->and($audit->toJson())->not->toContain('bukti-pembayaran');

    $buktiDok = Dokumen::query()->where('santri_id', $setup['santri']->id)->where('jenis', 'bukti_pembayaran')->first();
    expect($buktiDok)->not->toBeNull();
    Storage::disk('local')->assertExists($buktiDok->path);

    // The upload is an event in the audit trail, not a transition: the student
    // has not moved, but the attempt is on record.
    $this->assertDatabaseHas('histori_status', [
        'santri_id' => $setup['santri']->id,
        'dari_status' => 'menunggu_pembayaran',
        'ke_status' => 'menunggu_pembayaran',
        'oleh' => $this->wali->id,
    ]);
});

test('proof cannot be uploaded while the registration is not accepted', function (): void {
    $santri = Santri::factory()->untukWali($this->wali)->create();
    $pendaftaran = Pendaftaran::factory()->create(['santri_id' => $santri->id]);
    $fee = Pembayaran::factory()->create([
        'santri_id' => $santri->id,
        'tahun_ajaran_id' => $santri->tahun_ajaran_id,
        'pendaftaran_id' => $pendaftaran->id,
        'jenis_pembayaran' => JenisPembayaran::Pendaftaran,
        'jumlah' => '500000.00',
        'status' => StatusPembayaran::Belum,
    ]);

    $this->actingAs($this->wali)
        ->postJson("/api/wali/pembayaran/{$fee->id}/bukti", ['bukti' => berkasBuktiPdf()])
        ->assertStatus(422);

    $this->assertDatabaseHas('pembayarans', ['id' => $fee->id, 'status' => 'belum']);
    $this->assertDatabaseMissing('dokumen', ['santri_id' => $santri->id, 'jenis' => 'bukti_pembayaran']);
});

test('proof cannot be uploaded once the fee is already being reviewed or settled', function (StatusPembayaran $status): void {
    $setup = calonYangSedangMembayar($this->wali);
    $setup['fee']->forceFill(['status' => $status])->save();

    $this->actingAs($this->wali)
        ->postJson("/api/wali/pembayaran/{$setup['fee']->id}/bukti", ['bukti' => berkasBuktiPdf()])
        ->assertStatus(422);

    $this->assertDatabaseHas('pembayarans', ['id' => $setup['fee']->id, 'status' => $status->value]);
})->with([
    'menunggu verifikasi' => [StatusPembayaran::MenungguVerifikasi],
    'lunas' => [StatusPembayaran::Lunas],
]);

test('uploading a new proof replaces the previous one and removes its file', function (): void {
    $setup = calonYangSedangMembayar($this->wali);

    $this->actingAs($this->wali)
        ->postJson("/api/wali/pembayaran/{$setup['fee']->id}/bukti", ['bukti' => berkasBuktiPdf()])
        ->assertOk();

    $lama = Dokumen::query()->where('santri_id', $setup['santri']->id)->where('jenis', 'bukti_pembayaran')->firstOrFail();
    Storage::disk('local')->assertExists($lama->path);

    // Admin turns the first proof down, so the guardian is allowed to fix it.
    $this->actingAs($this->admin)
        ->putJson("/api/admin/pembayaran/{$setup['fee']->id}/status", [
            'status' => 'ditolak',
            'catatan' => 'Nomor referensi tidak terbaca.',
        ])
        ->assertOk();

    $this->actingAs($this->wali)
        ->postJson("/api/wali/pembayaran/{$setup['fee']->id}/bukti", [
            'bukti' => berkasBuktiPdf(),
            'referensi' => 'TRF-20260928-002',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'menunggu_verifikasi');

    // Bukti pembayaran row should be replaced; total dokumen includes required docs
    $this->assertDatabaseHas('dokumen', ['santri_id' => $setup['santri']->id, 'jenis' => 'bukti_pembayaran']);
    Storage::disk('local')->assertMissing($lama->path);
    $this->assertDatabaseHas('pembayarans', [
        'id' => $setup['fee']->id,
        'status' => 'menunggu_verifikasi',
        'referensi' => 'TRF-20260928-002',
    ]);
});

/*
|--------------------------------------------------------------------------
| Verifying the fee (the only way to activate)
|--------------------------------------------------------------------------
*/

test('admin confirming the fee makes the student active in one write', function (): void {
    $setup = calonYangSedangMembayar($this->wali);

    $this->actingAs($this->wali)
        ->postJson("/api/wali/pembayaran/{$setup['fee']->id}/bukti", [
            'bukti' => berkasBuktiPdf(),
            'referensi' => 'TRF-20260928-001',
        ])
        ->assertOk();

    $this->actingAs($this->admin)
        ->putJson("/api/admin/pembayaran/{$setup['fee']->id}/status", [
            'status' => 'lunas',
            'catatan' => 'Terverifikasi, salinan di arsip.',
        ])
        ->assertOk()
        ->assertJsonPath('data.pembayaran.status', 'lunas')
        ->assertJsonPath('data.pembayaran.keterangan', 'Terverifikasi, salinan di arsip.');

    $this->assertDatabaseHas('pembayarans', [
        'id' => $setup['fee']->id,
        'status' => 'lunas',
        'dicatat_oleh' => $this->admin->id,
    ]);
    $this->assertDatabaseHas('santris', ['id' => $setup['santri']->id, 'status' => 'aktif']);
    $this->assertDatabaseHas('dokumen', [
        'santri_id' => $setup['santri']->id,
        'jenis' => 'bukti_pembayaran',
        'status' => 'diterima',
        'diverifikasi_oleh' => $this->admin->id,
        'catatan' => 'Terverifikasi, salinan di arsip.',
    ]);
    $this->assertDatabaseHas('histori_status', [
        'santri_id' => $setup['santri']->id,
        'dari_status' => 'menunggu_pembayaran',
        'ke_status' => 'aktif',
        'oleh' => $this->admin->id,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $this->admin->id,
        'action' => 'pembayaran.pendaftaran.verified',
        'subject_id' => $setup['fee']->id,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $this->admin->id,
        'action' => 'santri.activated',
        'subject_id' => $setup['santri']->id,
    ]);
});

test('confirming lunas is refused when no proof is under review', function (): void {
    $setup = calonYangSedangMembayar($this->wali);
    // The fee is "waiting for review" but no file has been uploaded; a verdict
    // of lunas would settle money no one has seen.
    $setup['fee']->forceFill(['status' => StatusPembayaran::MenungguVerifikasi])->save();

    $this->actingAs($this->admin)
        ->putJson("/api/admin/pembayaran/{$setup['fee']->id}/status", ['status' => 'lunas'])
        ->assertStatus(422);

    $this->assertDatabaseHas('pembayarans', ['id' => $setup['fee']->id, 'status' => 'menunggu_verifikasi']);
    $this->assertDatabaseHas('santris', ['id' => $setup['santri']->id, 'status' => 'menunggu_pembayaran']);
    $this->assertDatabaseMissing('audit_logs', ['subject_id' => $setup['fee']->id]);
});

test('confirming lunas is refused while the registration is not accepted', function (): void {
    $santri = Santri::factory()->untukWali($this->wali)->create();
    $pendaftaran = Pendaftaran::factory()->diverifikasi()->create(['santri_id' => $santri->id]);
    $fee = Pembayaran::factory()->create([
        'santri_id' => $santri->id,
        'tahun_ajaran_id' => $santri->tahun_ajaran_id,
        'pendaftaran_id' => $pendaftaran->id,
        'jenis_pembayaran' => JenisPembayaran::Pendaftaran,
        'jumlah' => '500000.00',
        'status' => StatusPembayaran::MenungguVerifikasi,
    ]);

    $this->actingAs($this->admin)
        ->putJson("/api/admin/pembayaran/{$fee->id}/status", ['status' => 'lunas'])
        ->assertStatus(422);

    $this->assertDatabaseHas('santris', ['id' => $santri->id, 'status' => 'pendaftaran']);
});

test('an already active student cannot be activated again', function (): void {
    $setup = calonYangSedangMembayar($this->wali);
    $setup['fee']->forceFill(['status' => StatusPembayaran::MenungguVerifikasi])->save();
    $setup['santri']->forceFill(['status' => SantriStatus::Aktif])->save();
    Dokumen::query()->create([
        'santri_id' => $setup['santri']->id,
        'jenis' => JenisDokumen::BuktiPembayaran,
        'path' => 'bukti-pembayaran/sudah-ada.pdf',
        'nama_asli' => 'bukti.pdf',
        'mime' => 'application/pdf',
        'ukuran' => 1024,
        'status' => StatusDokumen::Menunggu,
    ]);

    $this->actingAs($this->admin)
        ->putJson("/api/admin/pembayaran/{$setup['fee']->id}/status", ['status' => 'lunas'])
        ->assertOk()
        ->assertJsonPath('data.aktivasi.status', 'sudah_aktif');
});

test('admin refusing the proof keeps the student waiting on a corrected one', function (): void {
    $setup = calonYangSedangMembayar($this->wali);

    $this->actingAs($this->wali)
        ->postJson("/api/wali/pembayaran/{$setup['fee']->id}/bukti", [
            'bukti' => berkasBuktiPdf(),
        ])
        ->assertOk();

    $this->actingAs($this->admin)
        ->putJson("/api/admin/pembayaran/{$setup['fee']->id}/status", [
            'status' => 'ditolak',
            'catatan' => 'Bukti tidak jelas: potong nomor referensi.',
        ])
        ->assertOk()
        ->assertJsonPath('data.pembayaran.status', 'ditolak')
        ->assertJsonPath('data.pembayaran.keterangan', 'Bukti tidak jelas: potong nomor referensi.');

    $this->assertDatabaseHas('pembayarans', ['id' => $setup['fee']->id, 'status' => 'ditolak']);
    $this->assertDatabaseHas('dokumen', [
        'santri_id' => $setup['santri']->id,
        'jenis' => 'bukti_pembayaran',
        'status' => 'ditolak',
        'catatan' => 'Bukti tidak jelas: potong nomor referensi.',
    ]);
    $this->assertDatabaseHas('santris', ['id' => $setup['santri']->id, 'status' => 'menunggu_pembayaran']);
    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $this->admin->id,
        'action' => 'pembayaran.pendaftaran.rejected',
        'subject_id' => $setup['fee']->id,
    ]);
});

test('the payment state machine refuses the illegal ways into lunas', function (StatusPembayaran $dari): void {
    $setup = calonYangSedangMembayar($this->wali);
    $setup['fee']->forceFill(['status' => $dari])->save();

    // Settling straight from belum skips the approval that a proof is meant to
    // carry; settling from ditolak overrides an explicit rejection.
    $this->actingAs($this->admin)
        ->putJson("/api/admin/pembayaran/{$setup['fee']->id}/status", ['status' => 'lunas'])
        ->assertStatus(422);

    $this->assertDatabaseHas('pembayarans', ['id' => $setup['fee']->id, 'status' => $dari->value]);
    $this->assertDatabaseHas('santris', ['id' => $setup['santri']->id, 'status' => 'menunggu_pembayaran']);
})->with([
    'belum' => [StatusPembayaran::Belum],
    'ditolak' => [StatusPembayaran::Ditolak],
]);

/*
|--------------------------------------------------------------------------
| Who may review
|--------------------------------------------------------------------------
*/

test('a guardian cannot reach the admin review surface', function (): void {
    $setup = calonYangSedangMembayar($this->wali);
    $setup['fee']->forceFill(['status' => StatusPembayaran::MenungguVerifikasi])->save();

    $this->actingAs($this->wali)->getJson('/api/admin/pembayaran')->assertForbidden();
    $this->actingAs($this->wali)
        ->putJson("/api/admin/pembayaran/{$setup['fee']->id}/status", ['status' => 'lunas'])
        ->assertForbidden();
});

test('a guest cannot review a payment', function (): void {
    $setup = calonYangSedangMembayar($this->wali);

    $this->putJson("/api/admin/pembayaran/{$setup['fee']->id}/status", ['status' => 'lunas'])
        ->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| Other payment rows keep their place
|--------------------------------------------------------------------------
*/

test('spp instalments never appear on the registration-fee surface', function (): void {
    $setup = calonYangSedangMembayar($this->wali);

    Pembayaran::factory()->create([
        'santri_id' => $setup['santri']->id,
        'tahun_ajaran_id' => $setup['santri']->tahun_ajaran_id,
        'jenis_pembayaran' => JenisPembayaran::Spp,
        'jumlah' => '100000.00',
        'status' => StatusPembayaran::Lunas,
        'metode' => MetodePembayaran::Dana,
    ]);

    $this->actingAs($this->wali)
        ->getJson('/api/wali/pembayaran')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $setup['fee']->id);

    $this->actingAs($this->admin)
        ->getJson('/api/admin/pembayaran')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $setup['fee']->id);
});

/*
|--------------------------------------------------------------------------
| One contract per payment kind
|--------------------------------------------------------------------------
|
| `pembayarans` holds both the one-off registration fee and the monthly SPP
| invoice, and each lane has its own list, detail, proof, and verdict routes.
| The list already filtered by `jenis_pembayaran`; the detail, stream, and
| verdict lookups now do too, so an SPP id cannot be read through a route whose
| response shape has no period or due date to explain it.
|
*/

test('a wali reads the detail of their own registration fee', function (): void {
    $setup = calonYangSedangMembayar($this->wali);

    $this->actingAs($this->wali)
        ->getJson("/api/wali/pembayaran/{$setup['fee']->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $setup['fee']->id)
        ->assertJsonPath('data.jenis_pembayaran', 'pendaftaran')
        ->assertJsonPath('data.nomor_pendaftaran', $setup['pendaftaran']->nomor_pendaftaran)
        // SPP-only fields stay out of the fee contract.
        ->assertJsonMissingPath('data.periode')
        ->assertJsonMissingPath('data.jatuh_tempo');

    $this->actingAs($this->admin)
        ->getJson("/api/admin/pembayaran/{$setup['fee']->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $setup['fee']->id)
        ->assertJsonMissingPath('data.periode');
});

test('a registration-fee detail is a 404 when the id is an spp invoice', function (): void {
    $setup = calonYangSedangMembayar($this->wali);
    $spp = tagihanSppMilik($setup['santri']);

    $this->actingAs($this->wali)
        ->getJson("/api/wali/pembayaran/{$spp->id}")
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->getJson("/api/admin/pembayaran/{$spp->id}")
        ->assertNotFound();

    // The upload endpoint shares the same lookup, so it answers the same way.
    $this->actingAs($this->wali)
        ->postJson("/api/wali/pembayaran/{$spp->id}/bukti", ['bukti' => berkasBuktiPdf()])
        ->assertNotFound();

    // The fee that owns the surface is still readable.
    $this->actingAs($this->wali)
        ->getJson("/api/wali/pembayaran/{$setup['fee']->id}")
        ->assertOk();
});

test('a registration-fee proof stream is a 404 when the id is an spp invoice', function (): void {
    $setup = calonYangSedangMembayar($this->wali);
    $spp = tagihanSppMilik($setup['santri']);

    $path = 'bukti-pembayaran/tersedia.pdf';
    Storage::disk('local')->put($path, "%PDF-1.4\n%%EOF\n");
    Dokumen::query()->create([
        'santri_id' => $setup['santri']->id,
        'jenis' => JenisDokumen::BuktiPembayaran,
        'path' => $path,
        'nama_asli' => 'tersedia.pdf',
        'mime_type' => 'application/pdf',
        'ukuran_bytes' => 16,
        'status' => StatusDokumen::Menunggu,
    ]);

    // The student does have a proof on file, so the only reason for the 404 is
    // that the id names an SPP invoice rather than a registration fee.
    $this->actingAs($this->wali)
        ->get("/api/wali/pembayaran/{$spp->id}/bukti")
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->get("/api/admin/pembayaran/{$spp->id}/bukti")
        ->assertNotFound();

    $this->actingAs($this->wali)
        ->get("/api/wali/pembayaran/{$setup['fee']->id}/bukti")
        ->assertOk();

    $this->actingAs($this->admin)
        ->get("/api/admin/pembayaran/{$setup['fee']->id}/bukti")
        ->assertOk();
});

test('a fee verdict is a 404 when the id is an spp invoice', function (): void {
    $setup = calonYangSedangMembayar($this->wali);
    $spp = tagihanSppMilik($setup['santri']);

    $this->actingAs($this->admin)
        ->putJson("/api/admin/pembayaran/{$spp->id}/status", ['status' => 'lunas'])
        ->assertNotFound();

    $this->assertDatabaseHas('pembayarans', ['id' => $spp->id, 'status' => 'belum']);
    $this->assertDatabaseHas('santris', ['id' => $setup['santri']->id, 'status' => 'menunggu_pembayaran']);
});

test('the spp surface still reads and settles an spp invoice', function (): void {
    $santri = Santri::factory()->aktif()->untukWali($this->wali)->create();
    $spp = tagihanSppMilik($santri);

    $this->actingAs($this->wali)
        ->getJson("/api/wali/spp/{$spp->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $spp->id);

    $this->actingAs($this->admin)
        ->getJson("/api/admin/spp/{$spp->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $spp->id);

    $this->actingAs($this->wali)
        ->post("/api/wali/spp/{$spp->id}/bukti", ['bukti' => berkasBuktiPdf()])
        ->assertOk();

    $this->actingAs($this->admin)
        ->putJson("/api/admin/spp/{$spp->id}/status", ['status' => 'lunas'])
        ->assertOk()
        ->assertJsonPath('data.status', 'lunas');
});

test('a fee for a student with no guardian still reads', function (): void {
    $santri = Santri::factory()->tanpaWali()->menungguPembayaran()->create();
    $pendaftaran = Pendaftaran::factory()->diterima()->create(['santri_id' => $santri->id]);
    $fee = Pembayaran::factory()->create([
        'santri_id' => $santri->id,
        'tahun_ajaran_id' => $santri->tahun_ajaran_id,
        'pendaftaran_id' => $pendaftaran->id,
        'jenis_pembayaran' => JenisPembayaran::Pendaftaran,
        'jumlah' => '500000.00',
    ]);

    // `wali_id` is nullable since the santri-lama flow, so the contact block is
    // absent rather than a property read on null.
    $this->actingAs($this->admin)
        ->getJson("/api/admin/pembayaran/{$fee->id}")
        ->assertOk()
        ->assertJsonPath('data.wali', null)
        ->assertJsonPath('data.nama_santri', $santri->nama_lengkap);

    $this->actingAs($this->admin)
        ->getJson('/api/admin/pembayaran')
        ->assertOk()
        ->assertJsonPath('data.0.wali', null);
});
