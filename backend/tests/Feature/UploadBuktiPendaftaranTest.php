<?php

use App\Enums\JenisPembayaran;
use App\Enums\MetodePembayaran;
use App\Enums\StatusPembayaran;
use App\Models\Dokumen;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| The proof file itself: content, size, storage, and who may fetch it
|--------------------------------------------------------------------------
|
| The upload is the only file a guardian ever hands over, so its validation is
| loaded against real traffic:
|
|   - `mimetypes` sniffs content, not the client's declared extension; an
|     executable renamed to `.pdf` still fails, and any non-whitelisted type is
|     refused whatever its name claims.
|   - the cap is 2 MB, and it is enforced on the bytes, not the header.
|   - the file lands under a server-generated name on the private `local` disk,
|     and is streamed back only through the role-scoped route of whoever owns
|     the fee (or the admin reviewing it).
|
| Real signatures are used rather than `UploadedFile::fake()->create()` â€” that
| helper fills the temp file with placeholder bytes that finfo reads as text,
| which would make a correct PDF fail the sniffing rule. These files are real
| enough for finfo to classify them correctly.
|
*/
beforeEach(function (): void {
    Storage::fake('local');

    $this->wali = User::factory()->wali()->create(['name' => 'Bapak Ahmad']);
    $this->waliLain = User::factory()->wali()->create(['name' => 'Ibu Siti']);
    $this->admin = User::factory()->admin()->create();
});

/**
 * A student with an accepted registration and a waiting-for-payment fee.
 *
 * @return array{santri: Santri, pendaftaran: Pendaftaran, fee: Pembayaran}
 */
function calonDalamLanePembayaran(User $wali): array
{
    $santri = Santri::factory()->menungguPembayaran()->untukWali($wali)->create();
    $pendaftaran = Pendaftaran::factory()->diterima()->create(['santri_id' => $santri->id]);
    $fee = Pembayaran::factory()->create([
        'santri_id' => $santri->id,
        'tahun_ajaran_id' => $santri->tahun_ajaran_id,
        'pendaftaran_id' => $pendaftaran->id,
        'jenis_pembayaran' => JenisPembayaran::Pendaftaran,
        'jumlah' => '500000.00',
        'status' => StatusPembayaran::Belum,
        'metode' => MetodePembayaran::TransferBank,
    ]);

    return ['santri' => $santri, 'pendaftaran' => $pendaftaran, 'fee' => $fee];
}

/**
 * @param  TestCase  $test
 * @return TestResponse
 */
function unggahBuktiValid($test, User $wali, int $feeId)
{
    return $test->actingAs($wali)->postJson("/api/wali/pembayaran/{$feeId}/bukti", [
        'bukti' => UploadedFile::fake()->createWithContent(
            'bukti.pdf',
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< >>\n%%EOF\n",
        ),
    ]);
}

/*
|--------------------------------------------------------------------------
| What is accepted / refused
|--------------------------------------------------------------------------
*/

test('pdf proof is accepted', function (): void {
    $setup = calonDalamLanePembayaran($this->wali);

    $this->actingAs($this->wali)
        ->postJson("/api/wali/pembayaran/{$setup['fee']->id}/bukti", [
            'bukti' => UploadedFile::fake()->createWithContent(
                'kwitansi.pdf',
                "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< >>\n%%EOF\n",
            ),
        ])
        ->assertOk();

    $this->assertDatabaseHas('dokumen', [
        'santri_id' => $setup['santri']->id,
        'jenis' => 'bukti_pembayaran',
        'mime_type' => 'application/pdf',
    ]);
});

test('jpeg and png proofs are accepted', function (string $nama, string $isyarat, string $mime): void {
    $setup = calonDalamLanePembayaran($this->wali);

    $this->actingAs($this->wali)
        ->postJson("/api/wali/pembayaran/{$setup['fee']->id}/bukti", [
            'bukti' => UploadedFile::fake()->createWithContent($nama, $isyarat),
        ])
        ->assertOk();

    $this->assertDatabaseHas('dokumen', [
        'santri_id' => $setup['santri']->id,
        'jenis' => 'bukti_pembayaran',
        'mime_type' => $mime,
    ]);
})->with([
    'jpeg' => ['kwitansi.jpg', "\xFF\xD8\xFF\xE0".str_repeat("\x00", 32), 'image/jpeg'],
    // The magic bytes alone are not enough for the bundled magic database to
    // commit on Windows; a genuinely minimal 1x1 PNG is the reliable fixture.
    'png' => [
        'kwitansi.png',
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', true),
        'image/png',
    ],
]);

test('a renamed executable is refused even when it is called .pdf', function (): void {
    $setup = calonDalamLanePembayaran($this->wali);

    $this->actingAs($this->wali)
        ->postJson("/api/wali/pembayaran/{$setup['fee']->id}/bukti", [
            // `create()` places plain filler bytes; the sniff sees text, so a
            // `.pdf` name alone must not carry it through.
            'bukti' => UploadedFile::fake()->create('script.pdf', 64, 'application/pdf'),
        ])
        ->assertStatus(422);

    $this->assertDatabaseMissing('dokumen', ['santri_id' => $setup['santri']->id, 'jenis' => 'bukti_pembayaran']);
});

test('plain text and php files are refused', function (string $nama, string $mime): void {
    $setup = calonDalamLanePembayaran($this->wali);

    Storage::disk('local');

    $this->actingAs($this->wali)
        ->postJson("/api/wali/pembayaran/{$setup['fee']->id}/bukti", [
            'bukti' => UploadedFile::fake()->createWithContent($nama, '<?php echo "pintu belakang";'),
        ])
        ->assertStatus(422);

    $this->assertDatabaseMissing('dokumen', ['santri_id' => $setup['santri']->id, 'jenis' => 'bukti_pembayaran']);
})->with([
    'php disguised as pdf' => ['exploit.pdf', 'application/pdf'],
    'plain text' => ['catatan.txt', 'text/plain'],
]);

test('proofs larger than the cap are refused', function (): void {
    $setup = calonDalamLanePembayaran($this->wali);

    // A *valid* PDF that simply exceeds 2 MB: the failure must come from its
    // size, not from being the wrong type.
    $besar = UploadedFile::fake()->createWithContent(
        'besar.pdf',
        "%PDF-1.4\n".str_repeat('0', 3_100_000)."\n%%EOF\n",
    );

    $this->actingAs($this->wali)
        ->postJson("/api/wali/pembayaran/{$setup['fee']->id}/bukti", ['bukti' => $besar])
        ->assertStatus(422);

    $this->assertDatabaseMissing('dokumen', ['santri_id' => $setup['santri']->id, 'jenis' => 'bukti_pembayaran']);
    $this->assertDatabaseHas('pembayarans', ['id' => $setup['fee']->id, 'status' => 'belum']);
});

/*
|--------------------------------------------------------------------------
| How the file is stored
|--------------------------------------------------------------------------
*/

test('the stored path is server generated, not the client filename', function (): void {
    $setup = calonDalamLanePembayaran($this->wali);

    $this->actingAs($this->wali)
        ->postJson("/api/wali/pembayaran/{$setup['fee']->id}/bukti", [
            'bukti' => UploadedFile::fake()->createWithContent(
                'sepuluh-milyar-../evil.pdf',
                "%PDF-1.4\n%%EOF\n",
            ),
        ])
        ->assertOk();

    $dokumen = Dokumen::query()->where('santri_id', $setup['santri']->id)->where('jenis', 'bukti_pembayaran')->firstOrFail();

    // The client name is kept only as metadata; nothing about it reaches the
    // filesystem or the stored path. (PHP itself delivers the basename only.)
    expect($dokumen->path)->toMatch('/^bukti-pembayaran\/[A-Za-z0-9_-]{20,}\.pdf$/')
        ->and($dokumen->path)->not->toContain('..')
        ->and($dokumen->nama_asli)->toBe('evil.pdf');

    Storage::disk('local')->assertExists($dokumen->path);
});

/*
|--------------------------------------------------------------------------
| Who may fetch the file
|--------------------------------------------------------------------------
*/

test('the owner downloads their own proof', function (): void {
    $setup = calonDalamLanePembayaran($this->wali);

    $isi = 'PDF-OFFICIAL-'.str_repeat('B', 128);
    $this->actingAs($this->wali)
        ->postJson("/api/wali/pembayaran/{$setup['fee']->id}/bukti", [
            'bukti' => UploadedFile::fake()->createWithContent('bukti.pdf', "%PDF-1.4\n$isi\n%%EOF\n"),
        ])
        ->assertOk();

    $this->actingAs($this->wali)
        ->get("/api/wali/pembayaran/{$setup['fee']->id}/bukti")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('a guardian cannot fetch another guardian proof', function (): void {
    $bukanPunya = calonDalamLanePembayaran($this->waliLain);
    unggahBuktiValid($this, $this->waliLain, $bukanPunya['fee']->id);

    $this->actingAs($this->wali)
        ->get("/api/wali/pembayaran/{$bukanPunya['fee']->id}/bukti")
        ->assertNotFound();

    // And the admin route is not the guardian's back door for reading it.
    $this->actingAs($this->wali)
        ->get("/api/admin/pembayaran/{$bukanPunya['fee']->id}/bukti")
        ->assertForbidden();
});

test('the admin reviewing the review stream fetches the proof from the admin route', function (): void {
    $setup = calonDalamLanePembayaran($this->wali);
    unggahBuktiValid($this, $this->wali, $setup['fee']->id);

    $this->actingAs($this->admin)
        ->get("/api/admin/pembayaran/{$setup['fee']->id}/bukti")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('a guest cannot fetch any proof', function (): void {
    $setup = calonDalamLanePembayaran($this->wali);

    // No actingAs on purpose: these two must answer 401 before any file is even
    // reachable, so no upload is set up for them.
    $this->getJson("/api/wali/pembayaran/{$setup['fee']->id}/bukti")->assertUnauthorized();
    $this->getJson("/api/admin/pembayaran/{$setup['fee']->id}/bukti")->assertUnauthorized();
});

test('a proof that exists as a row but not on disk is a 404', function (): void {
    $setup = calonDalamLanePembayaran($this->wali);

    UploadedFile::fake();
    $this->actingAs($this->wali)
        ->postJson("/api/wali/pembayaran/{$setup['fee']->id}/bukti", ['bukti' => UploadedFile::fake()->createWithContent(
            'bukti.pdf',
            "%PDF-1.4\n%%EOF\n",
        )])
        ->assertOk();

    $dokumen = Dokumen::query()->where('santri_id', $setup['santri']->id)->firstOrFail();
    Storage::disk('local')->delete($dokumen->path);

    $this->actingAs($this->wali)
        ->get("/api/wali/pembayaran/{$setup['fee']->id}/bukti")
        ->assertNotFound();
});
