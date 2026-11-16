<?php

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
        Schema::create('tahun_ajaran', function (Blueprint $table): void {
            $table->id();
            // Human readable label, e.g. "2026/2027".
            $table->string('nama', 20)->unique();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->boolean('is_active')->default(false)->index();
            $table->timestamps();

            $table->index(['is_active', 'tanggal_mulai']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tahun_ajaran');
    }
};
