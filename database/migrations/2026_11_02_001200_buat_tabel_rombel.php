<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kelas nyata pada satu tahun ajaran. Tingkat (kelas 1, 2, ...) dipisah dari rombel.
        Schema::create('rombel', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sekolah_id');
            $table->unsignedBigInteger('cabang_id');         // denormalisasi untuk global scope cabang
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->restrictOnDelete();
            $table->foreignId('tingkat_id')->constrained('tingkat')->restrictOnDelete();
            $table->string('nama', 100);
            $table->foreignId('wali_kelas_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->unsignedSmallInteger('kapasitas')->nullable();
            $table->string('status', 20)->default('aktif');
            $table->timestamps();

            $table->unique(['sekolah_id', 'tahun_ajaran_id', 'nama']);
            // Target FK komposit untuk rombel_siswa
            $table->unique(['id', 'cabang_id', 'tahun_ajaran_id']);
            $table->index(['cabang_id', 'tahun_ajaran_id']);
            $table->foreign(['sekolah_id', 'cabang_id'])
                ->references(['id', 'cabang_id'])->on('sekolah')->restrictOnDelete();
        });

        Schema::create('rombel_siswa', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rombel_id');
            $table->foreignId('siswa_id')->constrained('siswa')->restrictOnDelete();
            $table->unsignedBigInteger('cabang_id');
            $table->unsignedBigInteger('tahun_ajaran_id');
            $table->unsignedSmallInteger('no_absen')->nullable();
            $table->date('tanggal_masuk');
            $table->date('tanggal_keluar')->nullable();
            $table->string('status', 20)->default('aktif');
            $table->timestamps();

            // Seorang siswa hanya berada di satu rombel per tahun ajaran
            $table->unique(['tahun_ajaran_id', 'siswa_id']);
            $table->index(['rombel_id', 'status']);
            $table->index(['cabang_id', 'tahun_ajaran_id']);
            // cabang_id dan tahun_ajaran_id wajib sama dengan milik rombel-nya
            $table->foreign(['rombel_id', 'cabang_id', 'tahun_ajaran_id'])
                ->references(['id', 'cabang_id', 'tahun_ajaran_id'])->on('rombel')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE rombel_siswa ADD CONSTRAINT rombel_siswa_tanggal_chk CHECK (tanggal_keluar IS NULL OR tanggal_keluar >= tanggal_masuk)');
    }

    public function down(): void
    {
        Schema::dropIfExists('rombel_siswa');
        Schema::dropIfExists('rombel');
    }
};
