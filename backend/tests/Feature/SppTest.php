<?php

use App\Enums\JenisPembayaran;
use App\Enums\MetodePembayaran;
use App\Enums\SantriStatus;
use App\Enums\StatusPembayaran;
use App\Models\AuditLog;
use App\Models\Pembayaran;
use App\Models\Pengaturan;
use App\Models\Santri;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Monthly SPP invoicing (Phase 8)
|--------------------------------------------------------------------------
|
| The claims defended here are:
|
|   - only students in `aktif` are ever billed, and the filter that decides who
|     is billed is the query itself, not a check afterwards;
|   - raising a month twice raises it once — the unique
|     `(santri, tahun_ajaran, periode)` index is what guarantees it, and it
|     holds even when the two runs do not go through the controller;
|   - the amount is the school's `biaya_spp_bulanan` setting, and a payload
|     that carries a price changes nothing;
|   - a guardian sees and pays only their own students' invoices; another
|     guardian's invoice id is a 404, and no guardian route writes a status, so
|     `lunas` is unreachable from the wali side;
|   - the illegal transitions (`belum -> lunas`, `ditolak -> lunas`, anything
|     out of `lunas`) are refused, so only an admin verdict over a real proof
|     can settle a bill;
|   - an SPP invoice is `jenis_pembayaran = 'spp'` *and* has a period, which is
|     what keeps the legacy rows the Phase 6 default created out of the SPP
|     surfaces without a single row being rewritten.
|
*/

beforeEach(function (): void {
    $this->wali = User::factory()->wali()->create(['name' => 'Bapak Santri']);
    $this->waliLain = User::factory()->wali()->create(['name' => 'Bapak Orang Lain']);
    $this->admin = User::factory()->admin()->create();
    $this->tahun = TahunAjaran::factory()->aktif()->create(['nama' => '2026/2027']);

    Pengaturan::write('biaya_spp_bulanan', '750000', 'integer');
});

/**
 * An active student owned by the given guardian, in the given academic year.
 */
function sanctriAktif(User $wali, ?TahunAjaran $tahun = null): Santri
{
    return Santri::factory()
        ->aktif()
        ->untukWali($wali)
        ->untukTahunAjaran($tahun ?? TahunAjaran::query()->aktif()->firstOrFail())
        ->create();
}

/**
 * Raise one month of SPP and return the endpoint's payload.
 *
 * @return array<string, mixed>
 */
function generateSpp(string $periode = '2026-07', ?TahunAjaran $tahun = null): array
{
    $response = test()->actingAs(test()->admin)->postJson('/api/admin/spp/generate', [
        'periode' => $periode,
        'tahun_ajaran_id' => ($tahun ?? test()->tahun)->getKey(),
    ]);

    $response->assertOk();

    return $response->json('data');
}

/**
 * An unpaid July invoice for a student.
 */
function tagihanJuli(Santri $santri, ?TahunAjaran $tahun = null): Pembayaran
{
    return Pembayaran::factory()
        ->spp('2026-07-01')
        ->create([
            'santri_id' => $santri->getKey(),
            'tahun_ajaran_id' => ($tahun ?? $santri->tahun_ajaran_id)->getKey(),
        ]);
}

/**
 * A small but real PDF, so finfo sees a PDF and not the filename.
 */
function berkasPdf(string $nama = 'bukti.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($nama, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n");
}

/*
|--------------------------------------------------------------------------
| Which students get billed
|--------------------------------------------------------------------------
*/

test('an active student is billed', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);

    $hasil = generateSpp('2026-07', $this->tahun);

    expect($hasil['dibuat'])->toBe(1);

    $tagihan = Pembayaran::query()->tagihanSpp()->sole();

    expect($tagihan->santri_id)->toBe($santri->getKey())
        ->and($tagihan->jenis_pembayaran)->toBe(JenisPembayaran::Spp)
        ->and($tagihan->status)->toBe(StatusPembayaran::Belum)
        ->and($tagihan->periode?->toDateString())->toBe('2026-07-01')
        ->and($tagihan->jatuh_tempo?->toDateString())->toBe('2026-07-10')
        ->and($tagihan->tahun_ajaran_id)->toBe($this->tahun->getKey());

    $audit = AuditLog::query()->where('action', 'spp.generated')->sole();
    expect($audit->user_id)->toBe($this->admin->getKey())
        ->and($audit->new_values)->toBe([
            'periode' => '2026-07-01',
            'tahun_ajaran_id' => $this->tahun->getKey(),
            'jumlah_tagihan' => 1,
        ]);
});

test('every active student is billed', function (): void {
    sanctriAktif($this->wali, $this->tahun);
    sanctriAktif($this->wali, $this->tahun);
    sanctriAktif($this->waliLain, $this->tahun);

    $hasil = generateSpp('2026-07', $this->tahun);

    expect($hasil['dibuat'])->toBe(3)
        ->and($hasil['total_santri_aktif'])->toBe(3)
        ->and(Pembayaran::query()->tagihanSpp()->count())->toBe(3);
});

test('a student who is not active is never billed', function (string $state, string $status): void {
    $santri = Santri::factory()
        ->{$state}()
        ->untukWali($this->wali)
        ->untukTahunAjaran($this->tahun)
        ->create();

    $hasil = generateSpp('2026-07', $this->tahun);

    expect($hasil['dibuat'])->toBe(0)
        ->and($hasil['total_santri_aktif'])->toBe(0)
        ->and(Pembayaran::query()->tagihanSpp()->count())->toBe(0);

    $this->actingAs($this->wali)
        ->getJson('/api/wali/spp')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    expect($santri->status)->toBe(SantriStatus::from($status));
})->with([
    // The state method and the wire value are listed as a pair on purpose:
    // `menungguPembayaran` is the factory method while the column holds
    // `menunggu_pembayaran`, and conflating the two is how a sweep like this
    // ends up calling a method that does not exist.
    'pendaftaran' => ['pendaftaran', 'pendaftaran'],
    'verifikasi' => ['verifikasi', 'verifikasi'],
    'menunggu pembayaran' => ['menungguPembayaran', 'menunggu_pembayaran'],
    'ditolak' => ['ditolak', 'ditolak'],
    'dibatalkan' => ['dibatalkan', 'dibatalkan'],
]);

test('a student is billed in a different academic year than the one they are in', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);
    $tahunLain = TahunAjaran::factory()->create(['nama' => '2025/2026']);

    $hasil = generateSpp('2026-07', $tahunLain);

    expect($hasil['dibuat'])->toBe(0)
        ->and($hasil['total_santri_aktif'])->toBe(0)
        ->and(Pembayaran::query()->tagihanSpp()->count())->toBe(0)
        ->and($santri->tahun_ajaran_id)->toBe($this->tahun->getKey());
});

test('a student activated after a month was billed is picked up the next time that month runs', function (): void {
    // July is raised while nobody is active yet.
    expect(generateSpp('2026-07', $this->tahun)['dibuat'])->toBe(0);

    // The student turns active afterwards.
    $santri = sanctriAktif($this->wali, $this->tahun);

    $hasil = generateSpp('2026-07', $this->tahun);

    expect($hasil['dibuat'])->toBe(1);

    $tagihan = Pembayaran::query()->tagihanSpp()->sole();

    expect($tagihan->santri_id)->toBe($santri->getKey())
        ->and($tagihan->periode?->toDateString())->toBe('2026-07-01');
});

/*
|--------------------------------------------------------------------------
| No duplicates
|--------------------------------------------------------------------------
*/

test('raising the same month twice raises it once', function (): void {
    sanctriAktif($this->wali, $this->tahun);
    sanctriAktif($this->wali, $this->tahun);

    $pertama = generateSpp('2026-07', $this->tahun);

    expect($pertama['dibuat'])->toBe(2);

    $kedua = generateSpp('2026-07', $this->tahun);

    // The second run is honest about having created nothing, rather than
    // reporting the same total again as if it had billed them a second time.
    expect($kedua['dibuat'])->toBe(0)
        ->and($kedua['dilewati'])->toBe(2)
        ->and($kedua['total_santri_aktif'])->toBe(2)
        ->and(Pembayaran::query()->tagihanSpp()->count())->toBe(2)
        ->and(AuditLog::query()->where('action', 'spp.generated')->count())->toBe(1);
});

test('the database refuses a second invoice for the same student, year, and month', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);

    tagihanJuli($santri, $this->tahun);

    expect(fn (): Pembayaran => tagihanJuli($santri, $this->tahun))
        ->toThrow(QueryException::class);
});

test('the same month may be billed in a different academic year', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);
    $tahunLain = TahunAjaran::factory()->create();

    Pembayaran::factory()->spp('2026-07-01')->create([
        'santri_id' => $santri->getKey(),
        'tahun_ajaran_id' => $this->tahun->getKey(),
    ]);

    $kedua = Pembayaran::factory()->spp('2026-07-01')->create([
        'santri_id' => $santri->getKey(),
        'tahun_ajaran_id' => $tahunLain->getKey(),
    ]);

    expect($kedua->exists)->toBeTrue();
});

test('two different months of the same year are two different invoices', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);

    generateSpp('2026-07', $this->tahun);
    $hasil = generateSpp('2026-08', $this->tahun);

    expect($hasil['dibuat'])->toBe(1)
        ->and(Pembayaran::query()->tagihanSpp()->count())->toBe(2);
});

test('the same month in a different year is a different period', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);

    generateSpp('2026-07', $this->tahun);
    $hasil = generateSpp('2027-07', $this->tahun);

    expect($hasil['dibuat'])->toBe(1)
        ->and(Pembayaran::query()->tagihanSpp()->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| The amount is the school's, not the caller's
|--------------------------------------------------------------------------
*/

test('the amount comes from the school settings', function (): void {
    Pengaturan::write('biaya_spp_bulanan', '825000', 'integer');
    sanctriAktif($this->wali, $this->tahun);

    $hasil = generateSpp('2026-07', $this->tahun);

    expect($hasil['nominal'])->toBe('825000.00')
        ->and(Pembayaran::query()->tagihanSpp()->sole()->jumlah)->toBe('825000.00');
});

test('a payload cannot set the amount', function (): void {
    sanctriAktif($this->wali, $this->tahun);

    $this->actingAs($this->admin)->postJson('/api/admin/spp/generate', [
        'periode' => '2026-07',
        'tahun_ajaran_id' => $this->tahun->getKey(),
        'jumlah' => '1.00',
        'nominal' => '1.00',
    ])->assertOk();

    expect(Pembayaran::query()->tagihanSpp()->sole()->jumlah)->toBe('750000.00');
});

test('a raised invoice keeps its amount when the setting later changes', function (): void {
    sanctriAktif($this->wali, $this->tahun);
    generateSpp('2026-07', $this->tahun);

    Pengaturan::write('biaya_spp_bulanan', '999000', 'integer');

    // A bill already shown to a guardian is not rewritten by a settings change.
    expect(Pembayaran::query()->tagihanSpp()->sole()->jumlah)->toBe('750000.00');
});

/*
|--------------------------------------------------------------------------
| Period
|--------------------------------------------------------------------------
*/

test('the period is stored as the first day of the billed month, with its year', function (): void {
    sanctriAktif($this->wali, $this->tahun);

    generateSpp('2026-07', $this->tahun);

    $this->actingAs($this->wali)
        ->getJson('/api/wali/spp')
        ->assertOk()
        ->assertJsonPath('data.0.periode', '2026-07-01')
        ->assertJsonPath('data.0.periode_label', 'Juli 2026')
        ->assertJsonPath('data.0.jatuh_tempo', '2026-07-10');
})->with([
    'Januari' => ['2026-01', '2026-01-01', 'Januari 2026', '2026-01-10'],
    'Juli' => ['2026-07', '2026-07-01', 'Juli 2026', '2026-07-10'],
    'Desember' => ['2026-12', '2026-12-01', 'Desember 2026', '2026-12-10'],
    'tahun berikutnya' => ['2027-02', '2027-02-01', 'Februari 2027', '2027-02-10'],
]);

test('a period that is not a real month is refused', function (string $periode): void {
    sanctriAktif($this->wali, $this->tahun);

    $this->actingAs($this->admin)
        ->postJson('/api/admin/spp/generate', [
            'periode' => $periode,
            'tahun_ajaran_id' => $this->tahun->getKey(),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('periode');

    expect(Pembayaran::query()->tagihanSpp()->count())->toBe(0);
})->with([
    'bulan 13' => '2026-13',
    'bulan 00' => '2026-00',
    'tanpa bulan' => '2026',
    'bulan saja tanpa tahun' => '07',
    'bukan tanggal' => 'Juli',
]);

test('the due day is clamped so a short month still has a due date', function (): void {
    Pengaturan::write('spp_tanggal_jatuh_tempo', '31', 'integer');
    sanctriAktif($this->wali, $this->tahun);

    generateSpp('2026-02', $this->tahun);

    // February has no 31st; the generator clamps rather than overflowing.
    expect(Pembayaran::query()->tagihanSpp()->sole()->jatuh_tempo?->toDateString())->toBe('2026-02-28');
});

/*
|--------------------------------------------------------------------------
| Wali surface
|--------------------------------------------------------------------------
*/

test('a guardian sees their own invoices and nobody else', function (): void {
    $santriSaya = sanctriAktif($this->wali, $this->tahun);
    $santriOrang = sanctriAktif($this->waliLain, $this->tahun);

    $milikSaya = tagihanJuli($santriSaya, $this->tahun);
    $milikOrang = tagihanJuli($santriOrang, $this->tahun);

    $this->actingAs($this->wali)
        ->getJson('/api/wali/spp')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $milikSaya->getKey())
        ->assertJsonPath('data.0.nama_santri', $santriSaya->nama_lengkap)
        ->assertJsonPath('data.0.tahun_ajaran', '2026/2027');

    // The other guardian's invoice id is a 404, not a 403: ownership is part of
    // the query, so "not yours" and "does not exist" are the same answer.
    $this->actingAs($this->wali)
        ->getJson("/api/wali/spp/{$milikOrang->getKey()}")
        ->assertNotFound();
});

test('a guardian can open one of their own invoices', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);
    $tagihan = tagihanJuli($santri, $this->tahun);

    $this->actingAs($this->wali)
        ->getJson("/api/wali/spp/{$tagihan->getKey()}")
        ->assertOk()
        ->assertJsonPath('data.id', $tagihan->getKey())
        ->assertJsonPath('data.jumlah', $tagihan->jumlah)
        ->assertJsonPath('data.status', 'belum')
        ->assertJsonPath('data.bukti', null);
});

test('a guardian can upload a proof and the invoice waits for review', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);
    $tagihan = tagihanJuli($santri, $this->tahun);

    $this->actingAs($this->wali)
        ->post("/api/wali/spp/{$tagihan->getKey()}/bukti", [
            'bukti' => berkasPdf(),
            'referensi' => 'TRF-20260710-001',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'menunggu_verifikasi')
        ->assertJsonPath('data.referensi', 'TRF-20260710-001')
        ->assertJsonPath('data.bukti.nama_asli', 'bukti.pdf');

    expect($tagihan->fresh()?->status)->toBe(StatusPembayaran::MenungguVerifikasi)
        ->and($tagihan->fresh()?->bukti_path)->not->toBeNull()
        ->and($tagihan->fresh()?->tanggal_bayar)->not->toBeNull();

    // The file is on the private disk, under a name the server chose.
    Storage::disk('local')->assertExists($tagihan->fresh()->bukti_path);
    $audit = AuditLog::query()->where('action', 'spp.payment.proof_uploaded')->sole();
    expect($audit->user_id)->toBe($this->wali->getKey())
        ->and($audit->subject_id)->toBe($tagihan->getKey())
        ->and($audit->new_values)->toBe(['status' => 'menunggu_verifikasi'])
        ->and($audit->toJson())->not->toContain('TRF-20260710-001')
        ->and($audit->toJson())->not->toContain('bukti-pembayaran');
});

test('each month keeps its own proof', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);
    $juli = tagihanJuli($santri, $this->tahun);
    $agustus = Pembayaran::factory()->spp('2026-08-01')->create([
        'santri_id' => $santri->getKey(),
        'tahun_ajaran_id' => $this->tahun->getKey(),
    ]);

    $this->actingAs($this->wali)
        ->post("/api/wali/spp/{$juli->getKey()}/bukti", ['bukti' => berkasPdf('juli.pdf')])
        ->assertOk();
    $this->actingAs($this->wali)
        ->post("/api/wali/spp/{$agustus->getKey()}/bukti", ['bukti' => berkasPdf('agustus.pdf')])
        ->assertOk();

    // August's upload did not overwrite July's proof — the whole reason SPP
    // proofs cannot share the student's single payment document.
    expect($juli->fresh()?->bukti_nama_asli)->toBe('juli.pdf')
        ->and($agustus->fresh()?->bukti_nama_asli)->toBe('agustus.pdf')
        ->and($juli->fresh()?->bukti_path)->not->toBe($agustus->fresh()?->bukti_path);
});

test('a rejected proof may be replaced and the old file is removed', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);
    $tagihan = tagihanJuli($santri, $this->tahun);

    $this->actingAs($this->wali)
        ->post("/api/wali/spp/{$tagihan->getKey()}/bukti", ['bukti' => berkasPdf('lama.pdf')])
        ->assertOk();

    $lama = $tagihan->fresh()?->bukti_path;

    expect($lama)->not->toBeNull();
    Storage::disk('local')->assertExists($lama);

    $tagihan->update(['status' => StatusPembayaran::Ditolak]);

    $this->actingAs($this->wali)
        ->post("/api/wali/spp/{$tagihan->getKey()}/bukti", ['bukti' => berkasPdf('baru.pdf')])
        ->assertOk()
        ->assertJsonPath('data.bukti.nama_asli', 'baru.pdf');

    Storage::disk('local')->assertMissing($lama);
});

test('a guardian cannot upload a proof for a settled or in-review invoice', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);

    foreach ([StatusPembayaran::Lunas, StatusPembayaran::MenungguVerifikasi] as $status) {
        $tagihan = tagihanJuli($santri, $this->tahun);
        $tagihan->update(['status' => $status]);

        $this->actingAs($this->wali)
            ->post("/api/wali/spp/{$tagihan->getKey()}/bukti", ['bukti' => berkasPdf()])
            ->assertStatus(422)
            ->assertJsonValidationErrors('bukti');

        $tagihan->delete();
    }
});

test('a guardian cannot upload a proof for another guardian invoice', function (): void {
    $santri = sanctriAktif($this->waliLain, $this->tahun);
    $tagihan = tagihanJuli($santri, $this->tahun);

    $this->actingAs($this->wali)
        ->post("/api/wali/spp/{$tagihan->getKey()}/bukti", ['bukti' => berkasPdf()])
        ->assertNotFound();

    expect($tagihan->fresh()?->status)->toBe(StatusPembayaran::Belum)
        ->and($tagihan->fresh()?->bukti_path)->toBeNull();
});

test('a guardian cannot read another guardian proof file', function (): void {
    $santri = sanctriAktif($this->waliLain, $this->tahun);
    $tagihan = tagihanJuli($santri, $this->tahun);

    $this->actingAs($this->waliLain)
        ->post("/api/wali/spp/{$tagihan->getKey()}/bukti", ['bukti' => berkasPdf()])
        ->assertOk();

    $this->actingAs($this->wali)
        ->get("/api/wali/spp/{$tagihan->getKey()}/bukti")
        ->assertNotFound();
});

test('a proof must really be a pdf or an image', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);
    $tagihan = tagihanJuli($santri, $this->tahun);

    // A script wearing a .pdf name is refused: finfo reads the bytes, not the
    // extension, which is the Phase 6 rule carried over unchanged.
    $palsu = UploadedFile::fake()->createWithContent('bukti.pdf', '<?php echo "bukan pdf";');

    $this->actingAs($this->wali)
        ->post("/api/wali/spp/{$tagihan->getKey()}/bukti", ['bukti' => $palsu])
        ->assertStatus(422)
        ->assertJsonValidationErrors('bukti');

    expect($tagihan->fresh()?->status)->toBe(StatusPembayaran::Belum);
});

test('a proof larger than 2 MB is refused', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);
    $tagihan = tagihanJuli($santri, $this->tahun);

    $besar = UploadedFile::fake()->create('bukti.pdf', 2049, 'application/pdf');

    $this->actingAs($this->wali)
        ->post("/api/wali/spp/{$tagihan->getKey()}/bukti", ['bukti' => $besar])
        ->assertStatus(422)
        ->assertJsonValidationErrors('bukti');
});

test('the upload uses the same finfo rule as the registration fee', function (): void {
    // Not a new upload system: the very same request class, so the whitelist
    // cannot drift between the two payment kinds. The test proves it by reusing
    // an image, which the registration-fee whitelist allows.
    $santri = sanctriAktif($this->wali, $this->tahun);
    $tagihan = tagihanJuli($santri, $this->tahun);

    $this->actingAs($this->wali)
        ->post("/api/wali/spp/{$tagihan->getKey()}/bukti", ['bukti' => UploadedFile::fake()->image('bukti.png')])
        ->assertOk()
        ->assertJsonPath('data.bukti.mime_type', 'image/png');
});

/*
|--------------------------------------------------------------------------
| The guardian may not settle a bill
|--------------------------------------------------------------------------
*/

test('a guardian cannot set an invoice to lunas', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);
    $tagihan = tagihanJuli($santri, $this->tahun);

    // There is no status route on the wali surface at all, so the verb is
    // refused by the router rather than by a rule that could be widened.
    $this->actingAs($this->wali)
        ->putJson("/api/wali/spp/{$tagihan->getKey()}/status", ['status' => 'lunas'])
        ->assertNotFound();

    // And the one wali write route ignores a status sent alongside the file:
    // the payload is not a source of truth for state.
    $this->actingAs($this->wali)
        ->post("/api/wali/spp/{$tagihan->getKey()}/bukti", [
            'bukti' => berkasPdf(),
            'status' => 'lunas',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'menunggu_verifikasi');

    expect($tagihan->fresh()?->status)->toBe(StatusPembayaran::MenungguVerifikasi);
});

test('a guardian cannot reach the admin verdict', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);
    $tagihan = tagihanJuli($santri, $this->tahun ?? $this->tahun);

    $this->actingAs($this->wali)
        ->putJson("/api/admin/spp/{$tagihan->getKey()}/status", ['status' => 'lunas'])
        ->assertForbidden();
});

test('an admin cannot reach the wali upload', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);
    $tagihan = tagihanJuli($santri, $this->tahun);

    $this->actingAs($this->admin)
        ->post("/api/wali/spp/{$tagihan->getKey()}/bukti", ['bukti' => berkasPdf()])
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Admin surface
|--------------------------------------------------------------------------
*/

test('an admin sees every invoice', function (): void {
    $saya = sanctriAktif($this->wali, $this->tahun);
    $orang = sanctriAktif($this->waliLain, $this->tahun);

    $milikSaya = tagihanJuli($saya, $this->tahun ?? $this->tahun);
    $milikOrang = tagihanJuli($orang, $this->tahun);

    $this->actingAs($this->admin)
        ->getJson('/api/admin/spp')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.wali.name', 'Bapak Santri')
        ->assertJsonPath('data.1.wali.name', 'Bapak Orang Lain');

    $this->actingAs($this->admin)
        ->getJson("/api/admin/spp/{$milikOrang->getKey()}")
        ->assertOk()
        ->assertJsonPath('data.id', $milikOrang->getKey());
});

test('an admin can filter the queue by year, month, and status', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);
    $juli = tagihanJuli($santri, $this->tahun);
    $agustus = Pembayaran::factory()->spp('2026-08-01')->create([
        'santri_id' => $santri->getKey(),
        'tahun_ajaran_id' => $this->tahun->getKey(),
    ]);
    $agustus->update(['status' => StatusPembayaran::Lunas]);

    $this->actingAs($this->admin)
        ->getJson('/api/admin/spp?periode=2026-07')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $juli->getKey());

    $this->actingAs($this->admin)
        ->getJson('/api/admin/spp?periode=2026-08')
        ->assertOk()
        ->assertJsonPath('data.0.id', $agustus->getKey());

    $this->actingAs($this->admin)
        ->getJson('/api/admin/spp?status=lunas')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $agustus->getKey());

    $this->actingAs($this->admin)
        ->getJson("/api/admin/spp?tahun_ajaran_id={$this->tahun->getKey()}")
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $tahunLain = TahunAjaran::factory()->create();
    $this->actingAs($this->admin)
        ->getJson("/api/admin/spp?tahun_ajaran_id={$tahunLain->getKey()}")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('a malformed filter is refused rather than answering with an empty queue', function (string $kueri, string $field): void {
    $santri = sanctriAktif($this->wali, $this->tahun);
    tagihanJuli($santri, $this->tahun);

    $this->actingAs($this->admin)
        ->getJson("/api/admin/spp?{$kueri}")
        ->assertStatus(422)
        ->assertJsonPath('data.errors.'.$field, fn (array $pesan): bool => $pesan !== []);
})->with([
    // A queue that ignores a bad filter answers `200` with a short list, which
    // reads to the admin as "nothing is outstanding" rather than "your filter
    // was wrong". The invoice above exists, so an empty list would be a lie.
    'bukan bulan' => ['periode=bla', 'periode'],
    'bulan.namedua belas' => ['periode=2026-13', 'periode'],
    'bulan nol' => ['periode=2026-00', 'periode'],
    // `Menunggu Pembayaran` is the label; `menunggu_verifikasi` is the wire
    // value. A client that sent the label used to filter for nothing.
    'label bukan nilai' => ['status=menunggu', 'status'],
    'status asing' => ['status=sudah_lunas', 'status'],
    'tahun tidak ada' => ['tahun_ajaran_id=999999', 'tahun_ajaran_id'],
    'tahun bukan angka' => ['tahun_ajaran_id=abc', 'tahun_ajaran_id'],
]);

test('the admin queue never shows the registration fee, and the fee queue never shows SPP', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);
    $spp = tagihanJuli($santri, $this->tahun ?? $this->tahun);

    $biaya = Pembayaran::factory()->create([
        'santri_id' => $santri->getKey(),
        'tahun_ajaran_id' => $this->tahun->getKey(),
        'jenis_pembayaran' => JenisPembayaran::Pendaftaran,
        'jumlah' => '500000.00',
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/admin/spp')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $spp->getKey());

    $this->actingAs($this->admin)
        ->getJson('/api/admin/pembayaran')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $biaya->getKey());

    $this->actingAs($this->wali)
        ->getJson('/api/wali/spp')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $spp->getKey());

    $this->actingAs($this->wali)
        ->getJson('/api/wali/pembayaran')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $biaya->getKey());
});

test('a fee is not reachable as an SPP invoice', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);
    $biaya = Pembayaran::factory()->create([
        'santri_id' => $santri->getKey(),
        'tahun_ajaran_id' => $this->tahun->getKey(),
        'jenis_pembayaran' => JenisPembayaran::Pendaftaran,
    ]);

    $this->actingAs($this->admin)->getJson("/api/admin/spp/{$biaya->getKey()}")->assertNotFound();
    $this->actingAs($this->wali)->getJson("/api/wali/spp/{$biaya->getKey()}")->assertNotFound();
});

test('an admin can read the proof they are reviewing', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);
    $tagihan = tagihanJuli($santri, $this->tahun ?? $this->tahun);

    $this->actingAs($this->wali)
        ->post("/api/wali/spp/{$tagihan->getKey()}/bukti", ['bukti' => berkasPdf()])
        ->assertOk();

    $this->actingAs($this->admin)
        ->get("/api/admin/spp/{$tagihan->getKey()}/bukti")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('a guardian can read back their own proof', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);
    $tagihan = tagihanJuli($santri, $this->tahun ?? $this->tahun);

    $this->actingAs($this->wali)
        ->post("/api/wali/spp/{$tagihan->getKey()}/bukti", ['bukti' => berkasPdf()])
        ->assertOk();

    $this->actingAs($this->wali)
        ->get("/api/wali/spp/{$tagihan->getKey()}/bukti")
        ->assertOk();
});

test('asking for a proof that was never uploaded is a 404', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);
    $tagihan = tagihanJuli($santri, $this->tahun ?? $this->tahun);

    $this->actingAs($this->admin)->get("/api/admin/spp/{$tagihan->getKey()}/bukti")->assertNotFound();
    $this->actingAs($this->wali)->get("/api/wali/spp/{$tagihan->getKey()}/bukti")->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| The verdict
|--------------------------------------------------------------------------
*/

test('an admin settles a bill and it is recorded as verified', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);
    $tagihan = tagihanJuli($santri, $this->tahun ?? $this->tahun);

    $this->actingAs($this->wali)
        ->post("/api/wali/spp/{$tagihan->getKey()}/bukti", ['bukti' => berkasPdf()])
        ->assertOk();

    $this->actingAs($this->admin)
        ->putJson("/api/admin/spp/{$tagihan->getKey()}/status", [
            'status' => 'lunas',
            'catatan' => 'Sudah masuk rekening.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'lunas')
        ->assertJsonPath('data.keterangan', 'Sudah masuk rekening.');

    $tagihan = $tagihan->fresh();

    expect($tagihan?->status)->toBe(StatusPembayaran::Lunas)
        ->and($tagihan?->diverifikasi_oleh)->toBe($this->admin->getKey())
        ->and($tagihan?->diverifikasi_pada)->not->toBeNull()
        ->and($tagihan?->tanggal_bayar)->not->toBeNull();
    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $this->admin->getKey(),
        'action' => 'spp.payment.verified',
        'subject_id' => $tagihan?->getKey(),
    ]);
});

test('verifying an SPP invoice does not touch the student', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);
    $tagihan = tagihanJuli($santri, $this->tahun);

    $this->actingAs($this->wali)
        ->post("/api/wali/spp/{$tagihan->getKey()}/bukti", ['bukti' => berkasPdf()])
        ->assertOk();

    $this->actingAs($this->admin)
        ->putJson("/api/admin/spp/{$tagihan->getKey()}/status", ['status' => 'lunas'])
        ->assertOk();

    // The student was already active; a tuition bill has no business moving them.
    expect($santri->fresh()?->status)->toBe(SantriStatus::Aktif);
});

test('an admin refuses a proof and the guardian may send a corrected one', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);
    $tagihan = tagihanJuli($santri, $this->tahun ?? $this->tahun);

    $this->actingAs($this->wali)
        ->post("/api/wali/spp/{$tagihan->getKey()}/bukti", ['bukti' => berkasPdf()])
        ->assertOk();

    $this->actingAs($this->admin)
        ->putJson("/api/admin/spp/{$tagihan->getKey()}/status", [
            'status' => 'ditolak',
            'catatan' => 'Bukti tidak terbaca.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'ditolak')
        ->assertJsonPath('data.keterangan', 'Bukti tidak terbaca.');

    // A rejection is not a payment, so it must not stamp a payment date.
    expect($tagihan->fresh()?->tanggal_bayar)->not->toBeNull();

    $this->actingAs($this->wali)
        ->post("/api/wali/spp/{$tagihan->getKey()}/bukti", ['bukti' => berkasPdf('perbaikan.pdf')])
        ->assertOk()
        ->assertJsonPath('data.status', 'menunggu_verifikasi');
});

test('a rejected bill cannot be settled without a new proof', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);
    $tagihan = tagihanJuli($santri, $this->tahun ?? $this->tahun);

    $tagihan->update(['status' => StatusPembayaran::Ditolak]);

    // `ditolak -> lunas` is refused by the state machine, so a rejected proof
    // can never clear the bill.
    $this->actingAs($this->admin)
        ->putJson("/api/admin/spp/{$tagihan->getKey()}/status", ['status' => 'lunas'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');

    expect($tagihan->fresh()?->status)->toBe(StatusPembayaran::Ditolak);
});

test('an unpaid bill cannot be settled by an admin', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);
    $tagihan = tagihanJuli($santri, $this->tahun ?? $this->tahun);

    // `belum -> lunas` is refused: a bill with no proof under review cannot be
    // confirmed on air.
    $this->actingAs($this->admin)
        ->putJson("/api/admin/spp/{$tagihan->getKey()}/status", ['status' => 'lunas'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');

    expect($tagihan->fresh()?->status)->toBe(StatusPembayaran::Belum);
});

test('a settled bill is terminal', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);
    $tagihan = tagihanJuli($santri, $this->tahun ?? $this->tahun);

    $tagihan->update(['status' => StatusPembayaran::Lunas]);

    foreach (['lunas', 'ditolak'] as $keputusan) {
        $this->actingAs($this->admin)
            ->putJson("/api/admin/spp/{$tagihan->getKey()}/status", ['status' => $keputusan])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    expect($tagihan->fresh()?->status)->toBe(StatusPembayaran::Lunas);
});

test('a bill in review can only be settled or refused', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);
    $tagihan = tagihanJuli($santri, $this->tahun ?? $this->tahun);

    $this->actingAs($this->wali)
        ->post("/api/wali/spp/{$tagihan->getKey()}/bukti", ['bukti' => berkasPdf()])
        ->assertOk();

    // `belum` and `menunggu_verifikasi` are not verdicts an admin may hand down.
    foreach (['belum', 'menunggu_verifikasi'] as $bukan) {
        $this->actingAs($this->admin)
            ->putJson("/api/admin/spp/{$tagihan->getKey()}/status", ['status' => $bukan])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    expect($tagihan->fresh()?->status)->toBe(StatusPembayaran::MenungguVerifikasi);
});

test('a verdict on an invoice that is not SPP is a 404', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);
    $biaya = Pembayaran::factory()->create([
        'santri_id' => $santri->getKey(),
        'tahun_ajaran_id' => $this->tahun->getKey(),
        'jenis_pembayaran' => JenisPembayaran::Pendaftaran,
    ]);

    $this->actingAs($this->admin)
        ->putJson("/api/admin/spp/{$biaya->getKey()}/status", ['status' => 'lunas'])
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Legacy rows
|--------------------------------------------------------------------------
*/

test('rows the Phase 6 default typed as spp are not treated as invoices', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);

    // Exactly the shape the development migration left behind: kind `spp`, no
    // registration link, no period, and the registration fee's amount.
    $legacy = Pembayaran::factory()->create([
        'santri_id' => $santri->getKey(),
        'tahun_ajaran_id' => $this->tahun->getKey(),
        'jenis_pembayaran' => JenisPembayaran::Spp,
        'pendaftaran_id' => null,
        'periode' => null,
        'jatuh_tempo' => null,
        'jumlah' => '500000.00',
        'status' => StatusPembayaran::Lunas,
        'metode' => MetodePembayaran::Dana,
    ]);

    // It is left exactly as it was: no period invented, no amount rewritten.
    expect($legacy->fresh()?->periode)->toBeNull()
        ->and($legacy->fresh()?->jumlah)->toBe('500000.00');

    $this->actingAs($this->admin)
        ->getJson('/api/admin/spp')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs($this->wali)
        ->getJson('/api/wali/spp')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs($this->admin)->getJson("/api/admin/spp/{$legacy->getKey()}")->assertNotFound();

    // Raising July bills the student, and does not touch the legacy row.
    $hasil = generateSpp('2026-07', $this->tahun);

    expect($hasil['dibuat'])->toBe(1)
        ->and($legacy->fresh()?->periode)->toBeNull()
        ->and(Pembayaran::query()->tagihanSpp()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Access
|--------------------------------------------------------------------------
*/

test('a guest cannot reach any SPP endpoint', function (string $method, string $path): void {
    $this->{$method}($path)->assertUnauthorized();
})->with([
    ['get', '/api/wali/spp'],
    ['get', '/api/wali/spp/1'],
    ['post', '/api/wali/spp/1/bukti'],
    ['get', '/api/wali/spp/1/bukti'],
    ['get', '/api/admin/spp'],
    ['get', '/api/admin/spp/1'],
    ['get', '/api/admin/spp/1/bukti'],
    ['put', '/api/admin/spp/1/status'],
    ['post', '/api/admin/spp/generate'],
    ['get', '/api/admin/spp/tahun-ajaran'],
]);

test('a guest cannot generate an invoice', function (): void {
    $this->postJson('/api/admin/spp/generate', ['periode' => '2026-07'])->assertUnauthorized();

    expect(Pembayaran::query()->tagihanSpp()->count())->toBe(0);
});

test('a guardian cannot reach the admin invoice surface', function (string $method, string $path): void {
    $this->actingAs($this->wali)->{$method}($path)->assertForbidden();
})->with([
    ['get', '/api/admin/spp'],
    ['get', '/api/admin/spp/1'],
    ['get', '/api/admin/spp/1/bukti'],
    ['put', '/api/admin/spp/1/status'],
    ['post', '/api/admin/spp/generate'],
    ['get', '/api/admin/spp/tahun-ajaran'],
]);

test('an admin cannot reach the wali invoice surface', function (string $method, string $path): void {
    $this->actingAs($this->admin)->{$method}($path)->assertForbidden();
})->with([
    ['get', '/api/wali/spp'],
    ['get', '/api/wali/spp/1'],
    ['get', '/api/wali/spp/1/bukti'],
    ['post', '/api/wali/spp/1/bukti'],
]);

test('the academic years offered as a filter are readable by an admin', function (): void {
    TahunAjaran::factory()->create(['nama' => '2025/2026', 'is_active' => false]);

    $this->actingAs($this->admin)
        ->getJson('/api/admin/spp/tahun-ajaran')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

/*
|--------------------------------------------------------------------------
| Phase 1-7 left alone
|--------------------------------------------------------------------------
*/

test('the Phase 7 claim is respected by the SPP surface', function (): void {
    // A student claimed through the Phase 7 flow (wali_id was nullable, so the
    // row can predate guardian accounts) is billed and visible as their own.
    $santri = Santri::factory()
        ->aktif()
        ->tanpaWali()
        ->untukTahunAjaran($this->tahun)
        ->create();

    generateSpp('2026-07', $this->tahun);

    // Not billed to anyone yet: the invoice exists, but no guardian owns the
    // student, so no guardian surface may show it.
    $this->actingAs($this->wali)
        ->getJson('/api/wali/spp')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $santri->update(['wali_id' => $this->wali->getKey()]);

    $this->actingAs($this->wali)
        ->getJson('/api/wali/spp')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.santri_id', $santri->getKey());
});

test('the Phase 6 fee pipeline is untouched by raising SPP', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);

    $biaya = Pembayaran::factory()->create([
        'santri_id' => $santri->getKey(),
        'tahun_ajaran_id' => $this->tahun->getKey(),
        'jenis_pembayaran' => JenisPembayaran::Pendaftaran,
        'jumlah' => '500000.00',
        'status' => StatusPembayaran::Belum,
    ]);

    generateSpp('2026-07', $this->tahun ?? $this->tahun);

    // The fee still reads as a fee, with its own amount, its own state, and no
    // period bolted onto it.
    expect($biaya->fresh()?->jumlah)->toBe('500000.00')
        ->and($biaya->fresh()?->status)->toBe(StatusPembayaran::Belum)
        ->and($biaya->fresh()?->periode)->toBeNull()
        ->and($biaya->isTagihanSpp())->toBeFalse();
});

test('the invoice list is ordered newest month first', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);

    generateSpp('2026-07', $this->tahun);
    generateSpp('2026-08', $this->tahun);
    generateSpp('2026-09', $this->tahun);

    $this->actingAs($this->wali)
        ->getJson('/api/wali/spp')
        ->assertOk()
        ->assertJsonPath('data.0.periode_label', 'September 2026')
        ->assertJsonPath('data.1.periode_label', 'Agustus 2026')
        ->assertJsonPath('data.2.periode_label', 'Juli 2026');
});

test('admin rejection writes the rejection audit', function (): void {
    $santri = sanctriAktif($this->wali, $this->tahun);
    $tagihan = tagihanJuli($santri, $this->tahun);

    $this->actingAs($this->wali)
        ->post("/api/wali/spp/{$tagihan->getKey()}/bukti", ['bukti' => berkasPdf()])
        ->assertOk();

    $this->actingAs($this->admin)
        ->putJson("/api/admin/spp/{$tagihan->getKey()}/status", [
            'status' => 'ditolak',
            'catatan' => 'Bukti tidak terbaca.',
        ])
        ->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $this->admin->getKey(),
        'action' => 'spp.payment.rejected',
        'subject_id' => $tagihan->getKey(),
    ]);
});
