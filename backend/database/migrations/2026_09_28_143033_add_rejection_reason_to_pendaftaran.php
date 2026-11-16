<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A rejection needs a reason the guardian can read. `catatan` is an
     * admin-internal note that Phase 5 also exposes as "Catatan", so reusing it
     * would let an internal remark read as the rejection verdict. A dedicated
     * nullable column keeps the two intents distinct.
     */
    public function up(): void
    {
        Schema::table('pendaftaran', function (Blueprint $table): void {
            $table->text('alasan_ditolak')->nullable()->after('catatan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pendaftaran', function (Blueprint $table): void {
            $table->dropColumn('alasan_ditolak');
        });
    }
};
