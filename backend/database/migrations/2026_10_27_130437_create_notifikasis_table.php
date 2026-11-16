<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifikasis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('tipe')->index();
            $table->string('judul');
            $table->text('pesan');
            $table->string('url')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('dibaca_pada')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasis');
    }
};
