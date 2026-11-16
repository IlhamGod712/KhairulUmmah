<?php

use App\Models\Pengaturan;
use App\Models\Santri;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    // RefreshDatabase only migrates, so the seeders have to run explicitly.
    $this->seed(DatabaseSeeder::class);
});

test('berkasLengkap matches the seeded document coverage', function () {
    $lengkap = Santri::query()->where('nis', '260001')->firstOrFail();
    $sebagian = Santri::query()->where('nis', '260002')->firstOrFail();

    expect($lengkap->berkasLengkap())->toBeTrue()
        ->and($sebagian->berkasLengkap())->toBeFalse();
});

test('settings are readable and writable by key', function () {
    expect(Pengaturan::read('nama_pondok'))->toBe('Pondok Pesantren Terpadu Khairul Ummah')
        ->and(Pengaturan::read('tidak_ada', 'fallback'))->toBe('fallback');

    Pengaturan::write('biaya_uji', '1234', 'integer');

    expect(Pengaturan::read('biaya_uji'))->toBe('1234')
        ->and(Pengaturan::find('biaya_uji')?->tipe)->toBe('integer');
});
