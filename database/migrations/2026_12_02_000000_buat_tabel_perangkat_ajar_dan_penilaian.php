<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ========== PERANGKAT AJAR ==========

        Schema::create('modul_ajar', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cabang_id')->nullable();
            $table->unsignedInteger('sekolah_id')->nullable();
            $table->unsignedInteger('tahun_ajaran_id')->nullable();
            $table->unsignedInteger('semester_id')->nullable();
            $table->unsignedInteger('mata_pelajaran_id')->nullable();
            $table->unsignedInteger('tingkat_id')->nullable();
            $table->unsignedInteger('pegawai_id')->nullable();
            $table->string('judul');
            $table->unsignedInteger('alokasi_waktu')->nullable();
            $table->json('isi')->nullable();
            $table->unsignedInteger('versi')->default(1);
            $table->enum('status', ['draft', 'diajukan', 'disetujui', 'revisi'])->default('draft');
            $table->timestamps();

            $table->index('cabang_id');
            $table->index('sekolah_id');
            $table->index('tahun_ajaran_id');
            $table->index('semester_id');
            $table->index('mata_pelajaran_id');
            $table->index('tingkat_id');
            $table->index('pegawai_id');
            $table->index('status');
        });

        Schema::create('modul_ajar_tujuan', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('modul_ajar_id');
            $table->unsignedInteger('tujuan_pembelajaran_id');
            $table->timestamps();

            $table->unique(['modul_ajar_id', 'tujuan_pembelajaran_id'], 'modul_ajar_tujuan_unique');
            $table->index('modul_ajar_id');
            $table->index('tujuan_pembelajaran_id');
        });

        Schema::create('modul_ajar_versi', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('modul_ajar_id');
            $table->unsignedInteger('versi');
            $table->json('isi')->nullable();
            $table->unsignedInteger('dibuat_oleh')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['modul_ajar_id', 'versi'], 'modul_ajar_versi_unique');
            $table->index('modul_ajar_id');
            $table->index('dibuat_oleh');
        });

        // ========== PENILAIAN ==========

        Schema::create('komponen_penilaian', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('sekolah_id')->nullable();
            $table->unsignedInteger('tahun_ajaran_id')->nullable();
            $table->unsignedInteger('kurikulum_id')->nullable();
            $table->string('kode');
            $table->string('nama');
            $table->enum('jenis', ['formatif', 'sumatif', 'uts', 'uas', 'sikap', 'proyek', 'praktik'])->default('formatif');
            $table->decimal('bobot', 5, 2)->default(0.00);
            $table->unsignedInteger('urutan')->default(1);
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();

            $table->unique(['sekolah_id', 'tahun_ajaran_id', 'kurikulum_id', 'kode'], 'komponen_penilaian_unique');
            $table->index('sekolah_id');
            $table->index('tahun_ajaran_id');
            $table->index('kurikulum_id');
            $table->index('is_aktif');
        });

        Schema::create('penilaian', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('penugasan_mengajar_id');
            $table->unsignedInteger('semester_id');
            $table->unsignedInteger('komponen_id');
            $table->unsignedInteger('tujuan_pembelajaran_id')->nullable();
            $table->string('judul');
            $table->date('tanggal');
            $table->decimal('nilai_maks', 5, 2)->default(100.00);
            $table->enum('status', ['draft', 'final'])->default('draft');
            $table->enum('sumber', ['manual', 'tugas', 'cbt'])->default('manual');
            $table->unsignedInteger('sumber_id')->nullable();
            $table->timestamps();

            $table->index('penugasan_mengajar_id');
            $table->index('semester_id');
            $table->index('komponen_id');
            $table->index('status');
        });

        Schema::create('nilai_siswa', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('penilaian_id');
            $table->unsignedInteger('siswa_id');
            $table->decimal('nilai', 5, 2)->nullable();
            $table->decimal('nilai_remedial', 5, 2)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['penilaian_id', 'siswa_id'], 'nilai_siswa_unique');
            $table->index('penilaian_id');
            $table->index('siswa_id');
        });

        Schema::create('skala_penilaian', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('sekolah_id')->nullable();
            $table->unsignedInteger('kurikulum_id')->nullable();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->enum('jenis', ['angka', 'predikat', 'capaian', 'deskriptif'])->default('angka');
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();

            $table->index('sekolah_id');
            $table->index('kurikulum_id');
            $table->index('is_aktif');
        });

        Schema::create('skala_penilaian_butir', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('skala_id');
            $table->string('kode');
            $table->string('label');
            $table->decimal('nilai_min', 5, 2)->nullable();
            $table->decimal('nilai_maks', 5, 2)->nullable();
            $table->unsignedInteger('urutan')->default(1);
            $table->text('deskripsi_templat')->nullable();
            $table->timestamps();

            $table->unique(['skala_id', 'kode'], 'skala_penilaian_butir_unique');
            $table->index('skala_id');
        });

        Schema::create('aturan_olah_nilai', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('sekolah_id');
            $table->unsignedInteger('tahun_ajaran_id');
            $table->unsignedInteger('kurikulum_id')->nullable();
            $table->unsignedInteger('tingkat_id')->nullable();
            $table->unsignedInteger('mata_pelajaran_id')->nullable();
            $table->enum('metode', ['rata_berbobot', 'rata_sumatif', 'nilai_tertinggi', 'formula'])->default('rata_berbobot');
            $table->json('formula')->nullable();
            $table->string('pembulatan')->default('standar');
            $table->decimal('kkm', 5, 2)->nullable();
            $table->unsignedInteger('skala_id')->nullable();
            $table->timestamps();

            $table->index('sekolah_id');
            $table->index('tahun_ajaran_id');
            $table->index('kurikulum_id');
            $table->index('tingkat_id');
            $table->index('mata_pelajaran_id');
            $table->index('skala_id');
        });

        Schema::create('nilai_akhir', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('penugasan_mengajar_id');
            $table->unsignedInteger('siswa_id');
            $table->unsignedInteger('semester_id');
            $table->decimal('nilai_akhir', 5, 2)->nullable();
            $table->unsignedInteger('skala_butir_id')->nullable();
            $table->text('deskripsi')->nullable();
            $table->decimal('kkm', 5, 2)->nullable();
            $table->boolean('is_tuntas')->default(false);
            $table->enum('status', ['draft', 'final'])->default('draft');
            $table->timestamps();

            $table->unique(['penugasan_mengajar_id', 'siswa_id'], 'nilai_akhir_unique');
            $table->index('penugasan_mengajar_id');
            $table->index('siswa_id');
            $table->index('semester_id');
            $table->index('status');
        });

        // FK modul_ajar
        Schema::table('modul_ajar', function ($table) {
            $table->foreign('cabang_id')->references('id')->on('cabang')->restrictOnDelete();
            $table->foreign('sekolah_id')->references('id')->on('sekolah')->restrictOnDelete();
            $table->foreign('tahun_ajaran_id')->references('id')->on('tahun_ajaran')->restrictOnDelete();
            $table->foreign('semester_id')->references('id')->on('semester')->restrictOnDelete();
            $table->foreign('mata_pelajaran_id')->references('id')->on('mata_pelajaran')->restrictOnDelete();
            $table->foreign('tingkat_id')->references('id')->on('tingkat')->restrictOnDelete();
            $table->foreign('pegawai_id')->references('id')->on('pegawai')->restrictOnDelete();
        });

        // FK modul_ajar_tujuan
        Schema::table('modul_ajar_tujuan', function ($table) {
            $table->foreign('modul_ajar_id')->references('id')->on('modul_ajar')->cascadeOnDelete();
            $table->foreign('tujuan_pembelajaran_id')->references('id')->on('tujuan_pembelajaran')->restrictOnDelete();
        });

        // FK modul_ajar_versi
        Schema::table('modul_ajar_versi', function ($table) {
            $table->foreign('modul_ajar_id')->references('id')->on('modul_ajar')->cascadeOnDelete();
            $table->foreign('dibuat_oleh')->references('id')->on('users')->restrictOnDelete();
        });

        // FK komponen_penilaian
        Schema::table('komponen_penilaian', function ($table) {
            $table->foreign('sekolah_id')->references('id')->on('sekolah')->restrictOnDelete();
            $table->foreign('tahun_ajaran_id')->references('id')->on('tahun_ajaran')->restrictOnDelete();
            $table->foreign('kurikulum_id')->references('id')->on('kurikulum')->restrictOnDelete();
        });

        // FK penilaian
        Schema::table('penilaian', function ($table) {
            $table->foreign('penugasan_mengajar_id')->references('id')->on('penugasan_mengajar')->restrictOnDelete();
            $table->foreign('semester_id')->references('id')->on('semester')->restrictOnDelete();
            $table->foreign('komponen_id')->references('id')->on('komponen_penilaian')->restrictOnDelete();
            $table->foreign('tujuan_pembelajaran_id')->references('id')->on('tujuan_pembelajaran')->restrictOnDelete();
        });

        // FK nilai_siswa
        Schema::table('nilai_siswa', function ($table) {
            $table->foreign('penilaian_id')->references('id')->on('penilaian')->cascadeOnDelete();
            $table->foreign('siswa_id')->references('id')->on('siswa')->restrictOnDelete();
        });

        // FK skala_penilaian_butir
        Schema::table('skala_penilaian_butir', function ($table) {
            $table->foreign('skala_id')->references('id')->on('skala_penilaian')->cascadeOnDelete();
        });

        // FK aturan_olah_nilai
        Schema::table('aturan_olah_nilai', function ($table) {
            $table->foreign('sekolah_id')->references('id')->on('sekolah')->restrictOnDelete();
            $table->foreign('tahun_ajaran_id')->references('id')->on('tahun_ajaran')->restrictOnDelete();
            $table->foreign('kurikulum_id')->references('id')->on('kurikulum')->restrictOnDelete();
            $table->foreign('tingkat_id')->references('id')->on('tingkat')->restrictOnDelete();
            $table->foreign('mata_pelajaran_id')->references('id')->on('mata_pelajaran')->restrictOnDelete();
            $table->foreign('skala_id')->references('id')->on('skala_penilaian')->restrictOnDelete();
        });

        // FK nilai_akhir
        Schema::table('nilai_akhir', function ($table) {
            $table->foreign('penugasan_mengajar_id')->references('id')->on('penugasan_mengajar')->restrictOnDelete();
            $table->foreign('siswa_id')->references('id')->on('siswa')->restrictOnDelete();
            $table->foreign('semester_id')->references('id')->on('semester')->restrictOnDelete();
            $table->foreign('skala_butir_id')->references('id')->on('skala_penilaian_butir')->restrictOnDelete();
        });

        // Check: bobot non-negatif
        DB::statement("ALTER TABLE komponen_penilaian ADD CONSTRAINT komponen_penilaian_bobot_non_negatif CHECK (bobot >= 0)");
        // Check: nilai_maks non-negatif
        DB::statement("ALTER TABLE penilaian ADD CONSTRAINT penilaian_nilai_maks_non_negatif CHECK (nilai_maks >= 0)");
        // Check: kkm non-negatif
        DB::statement("ALTER TABLE aturan_olah_nilai ADD CONSTRAINT aturan_olah_nilai_kkm_non_negatif CHECK (kkm IS NULL OR kkm >= 0)");
        DB::statement("ALTER TABLE nilai_akhir ADD CONSTRAINT nilai_akhir_kkm_non_negatif CHECK (kkm IS NULL OR kkm >= 0)");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE nilai_akhir DROP CONSTRAINT IF EXISTS nilai_akhir_kkm_non_negatif");
        DB::statement("ALTER TABLE aturan_olah_nilai DROP CONSTRAINT IF EXISTS aturan_olah_nilai_kkm_non_negatif");
        DB::statement("ALTER TABLE penilaian DROP CONSTRAINT IF EXISTS penilaian_nilai_maks_non_negatif");
        DB::statement("ALTER TABLE komponen_penilaian DROP CONSTRAINT IF EXISTS komponen_penilaian_bobot_non_negatif");

        Schema::dropIfExists('nilai_akhir');
        Schema::dropIfExists('aturan_olah_nilai');
        Schema::dropIfExists('skala_penilaian_butir');
        Schema::dropIfExists('skala_penilaian');
        Schema::dropIfExists('nilai_siswa');
        Schema::dropIfExists('penilaian');
        Schema::dropIfExists('komponen_penilaian');
        Schema::dropIfExists('modul_ajar_versi');
        Schema::dropIfExists('modul_ajar_tujuan');
        Schema::dropIfExists('modul_ajar');
    }
};