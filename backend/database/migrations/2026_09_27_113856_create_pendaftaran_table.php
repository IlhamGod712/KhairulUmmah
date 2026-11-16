<?php

use App\Enums\StatusPendaftaran;
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
        Schema::create('pendaftaran', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('santri_id')
                ->constrained('santris')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('tahun_ajaran_id')
                ->constrained('tahun_ajaran')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // A student may only register once per academic year.
            $table->string('nomor_pendaftaran', 30)->unique();
            $table->date('tanggal_daftar');
            $table->enum('status', array_column(StatusPendaftaran::cases(), 'value'))
                ->default(StatusPendaftaran::Diajukan->value);
            $table->foreignId('diproses_oleh')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['santri_id', 'tahun_ajaran_id']);
            $table->index(['tahun_ajaran_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pendaftaran');
    }
};
