<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 6 taught `pembayarans` to tell a registration fee from an SPP
     * instalment via `jenis_pembayaran`, but left the row describing a *payment*
     * rather than a *recurring invoice*: nothing recorded which month was being
     * billed, and nothing stopped the same month being billed twice. Phase 8
     * adds exactly that, and nothing else. The table is reused rather than
     * duplicated — an SPP invoice is a `pembayarans` row with
     * `jenis_pembayaran = 'spp'` and a `periode`.
     *
     *  - `periode`: the first day of the billed month, so "Juli 2026" and
     *    "Juli 2027" are different values. The year lives in the date itself
     *    rather than being implied by `tahun_ajaran_id`, because a calendar
     *    month and an academic year are not the same clock. NULL for
     *    registration fees, and NULL for the legacy rows the Phase 6 default
     *    left behind — which is precisely what quarantines them: an SPP
     *    invoice is `spp` *and* has a period, and those old rows have neither a
     *    period nor the configured amount, so they never surface as an invoice
     *    and never had to be rewritten to achieve that.
     *  - `jatuh_tempo`: derived from the period at generation time rather than
     *    recomputed on read, so a change to the school calendar later cannot
     *    retroactively move a due date a guardian was already shown.
     *
     * The unique index is the anti-duplication guarantee the brief asks for.
     * `(santri_id, tahun_ajaran_id, periode)` is the identity of a monthly
     * invoice, and the generator relies on the database rather than on a
     * read-then-write check to refuse a second one. MySQL and SQLite both treat
     * NULLs as distinct inside a unique index, so registration-fee rows — whose
     * `periode` is NULL — are not constrained by it and several may exist for
     * one student and year, exactly as before. That is the intended reading, not
     * a loophole: only a row that claims to bill a period is subject to the
     * period's uniqueness.
     *
     * The proof columns follow the same reasoning. Phase 6 keeps the
     * registration-fee proof in `dokumen`, which is unique per `(santri_id,
     * jenis)` — one payment proof per student, true only because the fee is
     * one-off. A monthly invoice needs one proof per invoice, and retrofitting
     * `dokumen` would mean replacing that unique index with a three-column
     * version, which — because unique indexes ignore NULLs — would silently
     * stop enforcing one-file-per-kind for the student documents. Putting the
     * file on the invoice row makes "one proof per invoice" structural rather
     * than indexed: there is exactly one place for it to be, and no index to
     * drift. `dokumen` is left completely untouched.
     */
    public function up(): void
    {
        Schema::table('pembayarans', function (Blueprint $table): void {
            // First day of the billed month; NULL means "not a period invoice".
            $table->date('periode')->nullable()->after('jenis_pembayaran');
            $table->date('jatuh_tempo')->nullable()->after('periode');

            // The proof for this invoice, on the private `local` disk. The path is
            // server generated, so the client's filename is only ever a label.
            $table->string('bukti_path', 255)->nullable()->after('keterangan');
            $table->string('bukti_nama_asli', 255)->nullable()->after('bukti_path');
            $table->string('bukti_mime_type', 100)->nullable()->after('bukti_nama_asli');
            $table->unsignedBigInteger('bukti_ukuran_bytes')->nullable()->after('bukti_mime_type');

            $table->foreignId('diverifikasi_oleh')
                ->nullable()
                ->after('dicatat_oleh')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamp('diverifikasi_pada')->nullable()->after('diverifikasi_oleh');

            // One invoice per student per academic year per month.
            $table->unique(['santri_id', 'tahun_ajaran_id', 'periode'], 'uniq_pembayaran_periode_spp');

            // The admin list filters by academic year, month, and status.
            $table->index(['jenis_pembayaran', 'tahun_ajaran_id', 'periode'], 'idx_pembayaran_spp_filter');
            $table->index(['jenis_pembayaran', 'status'], 'idx_pembayaran_jenis_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pembayarans', function (Blueprint $table): void {
            $table->dropIndex('idx_pembayaran_jenis_status');
            $table->dropIndex('idx_pembayaran_spp_filter');
            $table->dropUnique('uniq_pembayaran_periode_spp');
            $table->dropForeign(['diverifikasi_oleh']);
            $table->dropColumn([
                'diverifikasi_oleh',
                'diverifikasi_pada',
                'bukti_ukuran_bytes',
                'bukti_mime_type',
                'bukti_nama_asli',
                'bukti_path',
                'jatuh_tempo',
                'periode',
            ]);
        });
    }
};
