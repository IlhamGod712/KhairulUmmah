<?php

use App\Enums\JenisDokumen;
use App\Models\Dokumen;
use App\Models\Pendaftaran;
use App\Models\Santri;
use Illuminate\Database\QueryException;

test('a student cannot register twice in the same academic year', function () {
    $santri = Santri::factory()->create();

    // Passing the model (not just its id) lets the factory reuse the student's
    // own academic year, which is what makes the unique index bite.
    Pendaftaran::factory()->create(['santri_id' => $santri]);

    expect(fn () => Pendaftaran::factory()->create(['santri_id' => $santri]))
        ->toThrow(QueryException::class);
});

test('a document type can only be uploaded once per student', function () {
    $santri = Santri::factory()->create();

    Dokumen::factory()->create([
        'santri_id' => $santri->id,
        'jenis' => JenisDokumen::KartuKeluarga,
    ]);

    expect(fn () => Dokumen::factory()->create([
        'santri_id' => $santri->id,
        'jenis' => JenisDokumen::KartuKeluarga,
    ]))->toThrow(QueryException::class);
});

// Phase 7 made `wali_id` nullable: the pre-existing "santri lama" rows predate
// guardian accounts, so an otherwise valid student legitimately has no wali
// until a guardian claims it through the identification flow. The ownership
// contract moved from the schema to the linking endpoint — claiming is the only
// way a `wali_id` gets written, and it never invents or mutates the student.
test('a student may exist without a wali until a guardian claims it', function () {
    Santri::factory()->tanpaWali()->create();

    $this->assertDatabaseHas('santris', ['wali_id' => null]);
});

test('NIS must be unique', function () {
    Santri::factory()->create(['nis' => '999001']);

    expect(fn () => Santri::factory()->create(['nis' => '999001']))
        ->toThrow(QueryException::class);
});
