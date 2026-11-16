<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 2 shipped `pembayarans` without a way to tell the registration fee
     * apart from an SPP instalment, so the Phase 5 registration resource could
     * only count payments per year and refused to claim the fee was settled.
     * Phase 6 activation depends on deciding *which* payment it is, so this
     * migration adds:
     *
     *  - `jenis_pembayaran`: `pendaftaran` (one-off, blocks activation) versus
     *    `spp` (recurring, never blocks it). Seeded rows default to `spp`; the
     *    seeder relabels the three existing rows to `pendaftaran` where the
     *    amount is the registration fee.
     *  - `pendaftaran_id`: the registration this payment settles. NULL for SPP,
     *    set for the fee. A unique index makes "one registration fee per
     *    registration" a database invariant instead of a convention.
     *  - Two new `status` values, `menunggu_verifikasi` (guardian uploaded a
     *    proof) and `ditolak` (proof rejected, upload can be repeated).
     *  - `metode` becomes nullable: the fee row opens with `belum` and no method;
     *    the guardian picks the transfer method only when submitting the proof.
     */
    public function up(): void
    {
        Schema::table('pembayarans', function (Blueprint $table): void {
            $table->foreignId('pendaftaran_id')
                ->nullable()
                ->after('id')
                ->constrained('pendaftaran')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->enum('jenis_pembayaran', ['pendaftaran', 'spp'])
                ->default('spp')
                ->after('santri_id');

            $table->enum('status', ['belum', 'menunggu_verifikasi', 'ditolak', 'lunas'])
                ->default('belum')
                ->change();

            $table->enum('metode', ['dana', 'transfer_bank', 'tunai'])
                ->nullable()
                ->change();

            $table->index('jenis_pembayaran');
            $table->unique('pendaftaran_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pembayarans', function (Blueprint $table): void {
            $table->dropUnique(['pendaftaran_id']);
            $table->dropForeign(['pendaftaran_id']);
            $table->dropColumn(['pendaftaran_id']);
            $table->dropIndex(['jenis_pembayaran']);
            $table->dropColumn(['jenis_pembayaran']);
            $table->enum('status', ['belum', 'lunas'])->default('belum')->change();
            $table->enum('metode', ['dana', 'transfer_bank', 'tunai'])->nullable(false)->change();
        });
    }
};
