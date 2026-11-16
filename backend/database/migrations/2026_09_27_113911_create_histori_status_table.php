<?php

use App\Enums\SantriStatus;
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
        Schema::create('histori_status', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('santri_id')
                ->constrained('santris')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Null on the very first entry, when there is no previous status.
            $table->enum('dari_status', array_column(SantriStatus::cases(), 'value'))
                ->nullable();
            $table->enum('ke_status', array_column(SantriStatus::cases(), 'value'));
            $table->text('keterangan')->nullable();

            $table->foreignId('oleh')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->timestamp('pada')->useCurrent();
            $table->timestamps();

            $table->index(['santri_id', 'pada']);
            $table->index('ke_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('histori_status');
    }
};
