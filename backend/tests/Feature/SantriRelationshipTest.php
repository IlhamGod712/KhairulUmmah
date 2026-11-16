<?php

use App\Enums\JenisDokumen;
use App\Enums\MetodePembayaran;
use App\Enums\SantriStatus;
use App\Enums\StatusDokumen;
use App\Models\Dokumen;
use App\Models\HistoriStatus;
use App\Models\Pembayaran;
use App\Models\Santri;
use App\Models\TahunAjaran;
use App\Models\User;

test('a wali owns their students through the relationship', function () {
    $wali = User::factory()->wali()->create();
    $lain = User::factory()->wali()->create();

    $anak = Santri::factory()->untukWali($wali)->create();
    Santri::factory()->untukWali($lain)->create();

    expect($wali->santris)->toHaveCount(1)
        ->and($wali->santris->first()->id)->toBe($anak->id)
        ->and($anak->wali->id)->toBe($wali->id);
});

test('a student belongs to an academic year', function () {
    $tahunAjaran = TahunAjaran::factory()->aktif()->create();
    $santri = Santri::factory()->untukTahunAjaran($tahunAjaran)->create();

    expect($santri->tahunAjaran->nama)->toBe($tahunAjaran->nama)
        ->and($tahunAjaran->santris)->toHaveCount(1);
});

test('only the active academic year is returned by the scope', function () {
    TahunAjaran::factory()->create(['is_active' => false]);
    $aktif = TahunAjaran::factory()->aktif()->create();

    expect(TahunAjaran::active()->pluck('id')->all())->toBe([$aktif->id]);
});

test('student status defaults to registration and is cast to the enum', function () {
    $santri = Santri::factory()->create();

    expect($santri->status)->toBe(SantriStatus::Pendaftaran)
        ->and($santri->status->isActive())->toBeFalse();

    $santri->update(['status' => SantriStatus::Aktif]);
    expect($santri->fresh()->status->isActive())->toBeTrue();
});

test('the active scope filters students by status', function () {
    $aktif = Santri::factory()->aktif()->create();
    Santri::factory()->create();

    expect(Santri::aktif()->pluck('id')->all())->toBe([$aktif->id]);
});

test('a student has many documents, registrations, payments and history entries', function () {
    $santri = Santri::factory()->create();

    expect($santri->dokumen)->toHaveCount(0)
        ->and($santri->pendaftarans)->toHaveCount(0)
        ->and($santri->pembayarans)->toHaveCount(0)
        ->and($santri->historiStatus)->toHaveCount(0);
});

test('berkasLengkap is true only when every required document is accepted', function () {
    $santri = Santri::factory()->create();

    foreach (JenisDokumen::cases() as $index => $jenis) {
        Dokumen::factory()->create([
            'santri_id' => $santri->id,
            'jenis' => $jenis,
            'status' => $index === 0
                ? StatusDokumen::Diterima
                : StatusDokumen::Menunggu,
        ]);
    }

    expect($santri->berkasLengkap())->toBeFalse();

    $santri->dokumen()->update(['status' => StatusDokumen::Diterima->value]);

    expect($santri->berkasLengkap())->toBeTrue();
});

test('the status history is returned oldest first', function () {
    $santri = Santri::factory()->create();

    HistoriStatus::factory()->count(2)->sequence(
        ['dari_status' => null, 'ke_status' => SantriStatus::Pendaftaran, 'pada' => now()],
        ['dari_status' => SantriStatus::Pendaftaran, 'ke_status' => SantriStatus::Verifikasi, 'pada' => now()->addHour()],
    )->create(['santri_id' => $santri->id]);

    $riwayat = $santri->historiStatus;

    expect($riwayat)->toHaveCount(2)
        ->and($riwayat->first()->ke_status)->toBe(SantriStatus::Pendaftaran)
        ->and($riwayat->last()->ke_status)->toBe(SantriStatus::Verifikasi);
});

test('deleting a student cascades to their dependent records', function () {
    $santri = Santri::factory()->create();
    $admin = User::factory()->admin()->create();

    $santri->dokumen()->create([
        'jenis' => JenisDokumen::AktaLahir,
        'path' => 'dokumen/x.pdf',
        'nama_asli' => 'Akta Lahir.pdf',
    ]);
    $santri->pembayarans()->create([
        'tahun_ajaran_id' => $santri->tahun_ajaran_id,
        'jumlah' => 500000,
        'metode' => MetodePembayaran::Tunai,
        'dicatat_oleh' => $admin->id,
    ]);

    $santri->delete();

    expect(Dokumen::query()->where('santri_id', $santri->id)->count())->toBe(0)
        ->and(Pembayaran::query()->where('santri_id', $santri->id)->count())->toBe(0);
});
