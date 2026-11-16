<?php

use App\Enums\JenisKelamin;
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
        Schema::create('santris', function (Blueprint $table): void {
            $table->id();

            // A wali is a User with UserRole::Wali. A student always has one.
            $table->foreignId('wali_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('tahun_ajaran_id')
                ->constrained('tahun_ajaran')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // School-issued student number, unique across the school.
            $table->string('nis', 30)->unique();
            // National student number (NISN), 10 digits, nullable for manual entry.
            $table->string('nisn', 20)->nullable()->unique();

            $table->string('nama_lengkap');
            $table->enum('jenis_kelamin', array_column(JenisKelamin::cases(), 'value'));
            $table->string('tempat_lahir');
            $table->date('tanggal_lahir');
            $table->text('alamat');
            $table->string('kelurahan')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('kabupaten')->nullable();
            $table->string('provinsi')->nullable();
            $table->string('no_hp', 25)->nullable();

            $table->string('nama_ayah')->nullable();
            $table->string('nama_ibu')->nullable();
            $table->string('pekerjaan_ayah')->nullable();
            $table->string('pekerjaan_ibu')->nullable();
            $table->unsignedBigInteger('penghasilan_bulanan')->nullable();
            $table->string('sekolah_asal')->nullable();

            $table->string('foto_path')->nullable();
            $table->enum('status', array_column(SantriStatus::cases(), 'value'))
                ->default(SantriStatus::Pendaftaran->value);

            $table->timestamps();

            $table->index(['status', 'tahun_ajaran_id']);
            $table->index(['wali_id', 'status']);
            $table->index('nama_lengkap');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('santris');
    }
};
