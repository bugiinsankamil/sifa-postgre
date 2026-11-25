<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /** Run the migrations. */
    public function up(): void
    {
        // Create penugasan_mengajar table
        Schema::create('penugasan_mengajar', function ($table) {
            $table->id();
            $table->unsignedInteger('sekolah_id')->nullable();
            $table->unsignedInteger('tahun_ajaran_id')->nullable();
            $table->unsignedInteger('semester_id')->nullable();
            $table->unsignedInteger('rombel_id')->nullable();
            $table->unsignedInteger('mata_pelajaran_id')->nullable();
            $table->unsignedInteger('pegawai_id')->nullable();
            $table->enum('peran', ['utama', 'pendamping'])->default('utama');
            $table->unsignedInteger('jam_per_minggu')->default(24);
            $table->string('no_sk')->nullable();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->timestamps();

            // Constraints and indexes
            $table->index('sekolah_id');
            $table->index('tahun_ajaran_id');
            $table->index('semester_id');
            $table->index('rombel_id');
            $table->index('mata_pelajaran_id');
            $table->index('pegawai_id');
        });

        // Create jam_pelajaran table
        Schema::create('jam_pelajaran', function ($table) {
            $table->id();
            $table->unsignedInteger('sekolah_id')->nullable();
            $table->unsignedInteger('tahun_ajaran_id')->nullable();
            $table->enum('hari', ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'])->nullable();
            $table->unsignedInteger('urutan')->nullable();
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->enum('tipe', ['belajar', 'istirahat', 'ibadah'])->default('belajar');
            $table->string('nama')->nullable(); // e.g., "PKN", "Ibada Malam"
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->timestamps();

            // Constraints and indexes
            $table->unique(['sekolah_id', 'tahun_ajaran_id', 'hari', 'urutan'], 'jam_pelajaran_unique');

            $table->index('sekolah_id');
            $table->index('tahun_ajaran_id');
        });

        // Create jadwal_pelajaran table
        Schema::create('jadwal_pelajaran', function ($table) {
            $table->id();
            $table->unsignedInteger('semester_id')->nullable();
            $table->unsignedInteger('penugasan_mengajar_id')->nullable();
            $table->unsignedInteger('jam_pelajaran_id')->nullable();
            $table->unsignedInteger('ruang_id')->nullable();
            $table->enum('status', ['plan', 'aktif', 'selesai', 'diubah'])->default('plan');
            $table->timestamps();

            // Constraints and indexes
            $table->unique(['penugasan_mengajar_id', 'jam_pelajaran_id'], 'jadwal_pelajaran_unique');

            $table->index('semester_id');
            $table->index('penugasan_mengajar_id');
            $table->index('jam_pelajaran_id');
            $table->index('ruang_id');
        });
    }

    /** Reverse the migrations. */
    public function down(): void
    {
        Schema::dropIfExists('jadwal_pelajaran');
        Schema::dropIfExists('jam_pelajaran');
        Schema::dropIfExists('penugasan_mengajar');
    }
};