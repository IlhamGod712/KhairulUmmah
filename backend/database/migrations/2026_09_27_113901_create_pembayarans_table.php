<?php

use App\Enums\MetodePembayaran;
use App\Enums\StatusPembayaran;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pembayarans', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('santri_id')
                ->constrained('santris')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('tahun_ajaran_id')
                ->constrained('tahun_ajaran')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Stored in rupiah; kept as a string cast to avoid float drift.
            $table->decimal('jumlah', 15, 2);
            $table->enum('metode', array_column(MetodePembayaran::cases(), 'value'));
            $table->enum('status', array_column(StatusPembayaran::cases(), 'value'))
                ->default(StatusPembayaran::Belum->value);
            $table->date('tanggal_bayar')->nullable();
            // Receipt number, transfer reference, or DANA transaction id.
            $table->string('referensi', 100)->nullable();
            $table->string('keterangan')->nullable();

            // The admin / TU who recorded the payment.
            $table->foreignId('dicatat_oleh')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['tahun_ajaran_id', 'status']);
            $table->index(['santri_id', 'tahun_ajaran_id']);
            $table->index('tanggal_bayar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pembayarans');
    }
};
