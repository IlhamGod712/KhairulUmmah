<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A guardian's phone number, needed for the Phase 4 wali profile and dashboard.
 *
 * The `users` table had no contact number: `santris.no_hp` holds the student's
 * number, which is a different person. This migration is purely additive and
 * nullable, so existing rows keep a NULL number and no data is rewritten. Only
 * wali accounts ever write to it; the wali profile endpoint is the single writer
 * and the admin surface does not touch this column.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('no_hp', 25)->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('no_hp');
        });
    }
};
