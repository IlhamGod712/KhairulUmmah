<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 2 created `santris` on the assumption that a student always has a
     * guardian. Enrollment for an existing student (Phase 7) breaks that: the
     * pre-existing rows were on file years before guardian accounts existed, so
     * their `wali_id` is legitimately null until a guardian claims them through
     * the identification flow. The relation itself (`santris.wali_id ->
     * users.id`, with `restrictOnDelete`) already exists, so this phase needs
     * only the nullability — no new table and no duplicate column.
     */
    public function up(): void
    {
        Schema::table('santris', function (Blueprint $table): void {
            $table->foreignId('wali_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('santris', function (Blueprint $table): void {
            $table->foreignId('wali_id')->change();
        });
    }
};
