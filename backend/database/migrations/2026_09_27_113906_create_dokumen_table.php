<?php

use App\Enums\JenisDokumen;
use App\Enums\StatusDokumen;
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
        Schema::create('dokumen', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('santri_id')
                ->constrained('santris')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->enum('jenis', array_column(JenisDokumen::cases(), 'value'));
            // Path on the configured Laravel Storage disk, never a public URL.
            $table->string('path', 255);
            $table->string('nama_asli', 255);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('ukuran_bytes')->nullable();

            $table->enum('status', array_column(StatusDokumen::cases(), 'value'))
                ->default(StatusDokumen::Menunggu->value);
            $table->foreignId('diverifikasi_oleh')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamp('diverifikasi_pada')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            // One file per document type per student; re-uploading replaces it.
            $table->unique(['santri_id', 'jenis']);
            $table->index(['status', 'jenis']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dokumen');
    }
};
