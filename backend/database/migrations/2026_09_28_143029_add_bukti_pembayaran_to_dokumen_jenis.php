<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The bukti pembayaran (payment proof) is still an uploaded file with a
     * review status, so it belongs in the existing `dokumen` table, not a
     * second upload system. This widens the `jenis` enum with
     * `bukti_pembayaran` — `JenisDokumen::isRequired()` returns false for it,
     * so `berkasLengkap()` keeps ignoring it and no file can make a
     * registration "already complete" before its fee is even in reach.
     */
    public function up(): void
    {
        Schema::table('dokumen', function (Blueprint $table): void {
            $table->enum('jenis', ['kk', 'akta_lahir', 'ijazah', 'identitas_wali', 'pas_foto', 'bukti_pembayaran'])
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dokumen', function (Blueprint $table): void {
            $table->enum('jenis', ['kk', 'akta_lahir', 'ijazah', 'identitas_wali', 'pas_foto'])
                ->change();
        });
    }
};
