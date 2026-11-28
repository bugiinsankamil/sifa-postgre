<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Run the migrations. */
    public function up(): void
    {
        Schema::create('perkembangan_siswa', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('siswa_id');
            $table->unsignedInteger('cabang_id')->nullable();
            $table->unsignedInteger('tahun_ajaran_id');
            $table->unsignedInteger('semester_id')->nullable();
            $table->string('aspek_ref');
            $table->text('uraian');
            $table->decimal('skor', 5, 2)->nullable();
            $table->unsignedInteger('pencatat_id')->nullable();
            $table->date('tanggal');
            $table->timestamps();
            $table->index('siswa_id');
            $table->index('cabang_id');
            $table->index('tahun_ajaran_id');
            $table->index('semester_id');
            $table->index('pencatat_id');
            $table->index('tanggal');
        });

        Schema::create('keputusan_kenaikan', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('siswa_id');
            $table->unsignedInteger('rombel_id');
            $table->unsignedInteger('cabang_id')->nullable();
            $table->unsignedInteger('tahun_ajaran_id');
            $table->unsignedInteger('rombel_tujuan_id')->nullable();
            $table->enum('keputusan', ['naik', 'tinggal', 'lulus', 'pindah']);
            $table->text('catatan')->nullable();
            $table->unsignedInteger('ditetapkan_oleh')->nullable();
            $table->date('tanggal');
            $table->timestamps();
            $table->unique(['siswa_id', 'tahun_ajaran_id'], 'keputusan_kenaikan_unique');
            $table->index('siswa_id');
            $table->index('rombel_id');
            $table->index('cabang_id');
            $table->index('tahun_ajaran_id');
            $table->index('rombel_tujuan_id');
            $table->index('ditetapkan_oleh');
            $table->index('tanggal');
        });

        Schema::create('rapor_templat', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('sekolah_id')->nullable();
            $table->unsignedInteger('jenjang_id')->nullable();
            $table->unsignedInteger('kurikulum_id')->nullable();
            $table->unsignedInteger('tahun_ajaran_id')->nullable();
            $table->unsignedInteger('cabang_id')->nullable();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->enum('jenis_penilaian', ['angka', 'deskriptif', 'campuran'])->default('angka');
            $table->json('pengaturan')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
            $table->index('sekolah_id');
            $table->index('jenjang_id');
            $table->index('kurikulum_id');
            $table->index('tahun_ajaran_id');
            $table->index('cabang_id');
            $table->index('is_aktif');
        });

        Schema::create('rapor_templat_bagian', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('templat_id');
            $table->string('kode');
            $table->string('judul');
            $table->enum('tipe', [
                'nilai_mapel',
                'deskriptif_mapel',
                'deskriptif_aspek',
                'sikap',
                'ekskul',
                'kehadiran',
                'proyek',
                'tahfidz',
                'pertumbuhan',
                'catatan_wali',
                'kustom',
            ]);
            $table->unsignedInteger('urutan')->default(0);
            $table->json('konfigurasi')->nullable();
            $table->boolean('is_tampil')->default(true);
            $table->timestamps();
            $table->unique(['templat_id', 'kode'], 'rapor_templat_bagian_unique');
            $table->index('templat_id');
            $table->index('kode');
        });

        Schema::create('rapor', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('siswa_id');
            $table->unsignedInteger('rombel_id');
            $table->unsignedInteger('semester_id');
            $table->unsignedInteger('cabang_id')->nullable();
            $table->unsignedInteger('templat_id');
            $table->string('nomor')->nullable()->unique();
            $table->text('catatan_wali_kelas')->nullable();
            $table->enum('status', ['draft', 'diverifikasi', 'terbit'])->default('draft');
            $table->unsignedInteger('ditandatangani_oleh')->nullable();
            $table->date('tanggal_terbit')->nullable();
            $table->string('berkas_path')->nullable();
            $table->json('data_tambahan')->nullable();
            $table->timestamps();
            $table->unique(['siswa_id', 'semester_id'], 'rapor_unique');
            $table->index('siswa_id');
            $table->index('rombel_id');
            $table->index('semester_id');
            $table->index('cabang_id');
            $table->index('templat_id');
            $table->index('status');
            $table->index('tanggal_terbit');
            $table->index('ditandatangani_oleh');
        });

        Schema::create('rapor_butir', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('rapor_id');
            $table->unsignedInteger('bagian_id')->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->string('label');
            $table->string('ref_type')->nullable();
            $table->unsignedInteger('ref_id')->nullable();
            $table->decimal('nilai_angka', 10, 2)->nullable();
            $table->string('predikat')->nullable();
            $table->text('deskripsi')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
            $table->index('rapor_id');
            $table->index('bagian_id');
            $table->index('ref_type');
            $table->index('ref_id');
        });

        Schema::create('tk_aspek', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('kurikulum_id')->nullable();
            $table->unsignedInteger('induk_id')->nullable();
            $table->string('kode');
            $table->string('nama');
            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
            $table->index('kurikulum_id');
            $table->index('induk_id');
            $table->index('kode');
            $table->index('is_aktif');
        });

        Schema::create('tk_indikator', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('aspek_id');
            $table->unsignedInteger('tingkat_id');
            $table->string('kode')->nullable();
            $table->text('uraian');
            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
            $table->index('aspek_id');
            $table->index('tingkat_id');
            $table->index('kode');
            $table->index('is_aktif');
        });

        Schema::create('tk_tema', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('sekolah_id');
            $table->unsignedInteger('cabang_id')->nullable();
            $table->unsignedInteger('tahun_ajaran_id');
            $table->unsignedInteger('semester_id');
            $table->string('nama');
            $table->string('subtema')->nullable();
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
            $table->index('sekolah_id');
            $table->index('cabang_id');
            $table->index('tahun_ajaran_id');
            $table->index('semester_id');
            $table->index('tanggal_mulai');
            $table->index('tanggal_selesai');
        });

        Schema::create('tk_observasi', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('siswa_id');
            $table->unsignedInteger('cabang_id')->nullable();
            $table->unsignedInteger('rombel_id')->nullable();
            $table->unsignedInteger('semester_id');
            $table->unsignedInteger('tema_id')->nullable();
            $table->unsignedInteger('aspek_id')->nullable();
            $table->date('tanggal');
            $table->text('kegiatan')->nullable();
            $table->text('uraian');
            $table->text('interpretasi')->nullable();
            $table->text('tindak_lanjut')->nullable();
            $table->unsignedInteger('pengamat_id')->nullable();
            $table->timestamps();
            $table->index('siswa_id');
            $table->index('cabang_id');
            $table->index('rombel_id');
            $table->index('semester_id');
            $table->index('tema_id');
            $table->index('aspek_id');
            $table->index('tanggal');
            $table->index('pengamat_id');
        });

        Schema::create('tk_ceklis_capaian', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('siswa_id');
            $table->unsignedInteger('cabang_id')->nullable();
            $table->unsignedInteger('semester_id');
            $table->unsignedInteger('indikator_id');
            $table->date('tanggal');
            $table->unsignedInteger('skala_butir_id')->nullable();
            $table->unsignedInteger('pengamat_id')->nullable();
            $table->text('catatan')->nullable();
            $table->unsignedInteger('observasi_id')->nullable();
            $table->timestamps();
            $table->index('siswa_id');
            $table->index('cabang_id');
            $table->index('semester_id');
            $table->index('indikator_id');
            $table->index('skala_butir_id');
            $table->index('pengamat_id');
            $table->index('tanggal');
            $table->index('observasi_id');
        });

        Schema::create('tk_hasil_karya', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('siswa_id');
            $table->unsignedInteger('cabang_id')->nullable();
            $table->unsignedInteger('semester_id');
            $table->unsignedInteger('tema_id')->nullable();
            $table->unsignedInteger('aspek_id')->nullable();
            $table->date('tanggal');
            $table->string('judul');
            $table->text('uraian')->nullable();
            $table->unsignedInteger('pengamat_id')->nullable();
            $table->timestamps();
            $table->index('siswa_id');
            $table->index('cabang_id');
            $table->index('semester_id');
            $table->index('tema_id');
            $table->index('aspek_id');
            $table->index('tanggal');
            $table->index('pengamat_id');
        });

        Schema::create('tk_asesmen_semester', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('siswa_id');
            $table->unsignedInteger('semester_id');
            $table->unsignedInteger('aspek_id');
            $table->unsignedInteger('skala_butir_id')->nullable();
            $table->text('deskripsi');
            $table->unsignedInteger('disusun_oleh')->nullable();
            $table->enum('status', ['draft', 'final'])->default('draft');
            $table->timestamps();
            $table->unique(['siswa_id', 'semester_id', 'aspek_id'], 'tk_asesmen_semester_unique');
            $table->index('siswa_id');
            $table->index('semester_id');
            $table->index('aspek_id');
            $table->index('skala_butir_id');
            $table->index('disusun_oleh');
            $table->index('status');
        });

        Schema::create('tk_refleksi_orang_tua', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('siswa_id');
            $table->unsignedInteger('semester_id');
            $table->unsignedInteger('orang_id');
            $table->date('tanggal');
            $table->text('uraian');
            $table->timestamps();
            $table->index('siswa_id');
            $table->index('semester_id');
            $table->index('orang_id');
            $table->index('tanggal');
        });

        Schema::table('perkembangan_siswa', function ($table) {
            $table->foreign('siswa_id')->references('id')->on('siswa')->restrictOnDelete();
            $table->foreign('cabang_id')->references('id')->on('cabang')->restrictOnDelete();
            $table->foreign('tahun_ajaran_id')->references('id')->on('tahun_ajaran')->restrictOnDelete();
            $table->foreign('semester_id')->references('id')->on('semester')->restrictOnDelete();
            $table->foreign('pencatat_id')->references('id')->on('pegawai')->restrictOnDelete();
        });

        Schema::table('keputusan_kenaikan', function ($table) {
            $table->foreign('siswa_id')->references('id')->on('siswa')->restrictOnDelete();
            $table->foreign('rombel_id')->references('id')->on('rombel')->restrictOnDelete();
            $table->foreign('cabang_id')->references('id')->on('cabang')->restrictOnDelete();
            $table->foreign('tahun_ajaran_id')->references('id')->on('tahun_ajaran')->restrictOnDelete();
            $table->foreign('rombel_tujuan_id')->references('id')->on('rombel')->restrictOnDelete();
            $table->foreign('ditetapkan_oleh')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('rapor_templat', function ($table) {
            $table->foreign('sekolah_id')->references('id')->on('sekolah')->restrictOnDelete();
            $table->foreign('jenjang_id')->references('id')->on('jenjang')->restrictOnDelete();
            $table->foreign('kurikulum_id')->references('id')->on('kurikulum')->restrictOnDelete();
            $table->foreign('tahun_ajaran_id')->references('id')->on('tahun_ajaran')->restrictOnDelete();
            $table->foreign('cabang_id')->references('id')->on('cabang')->restrictOnDelete();
        });

        Schema::table('rapor_templat_bagian', function ($table) {
            $table->foreign('templat_id')->references('id')->on('rapor_templat')->restrictOnDelete();
        });

        Schema::table('rapor', function ($table) {
            $table->foreign('siswa_id')->references('id')->on('siswa')->restrictOnDelete();
            $table->foreign('rombel_id')->references('id')->on('rombel')->restrictOnDelete();
            $table->foreign('semester_id')->references('id')->on('semester')->restrictOnDelete();
            $table->foreign('cabang_id')->references('id')->on('cabang')->restrictOnDelete();
            $table->foreign('templat_id')->references('id')->on('rapor_templat')->restrictOnDelete();
            $table->foreign('ditandatangani_oleh')->references('id')->on('pegawai')->restrictOnDelete();
        });

        Schema::table('rapor_butir', function ($table) {
            $table->foreign('rapor_id')->references('id')->on('rapor')->restrictOnDelete();
            $table->foreign('bagian_id')->references('id')->on('rapor_templat_bagian')->restrictOnDelete();
        });

        Schema::table('tk_aspek', function ($table) {
            $table->foreign('kurikulum_id')->references('id')->on('kurikulum')->restrictOnDelete();
            $table->foreign('induk_id')->references('id')->on('tk_aspek')->restrictOnDelete();
        });

        Schema::table('tk_indikator', function ($table) {
            $table->foreign('aspek_id')->references('id')->on('tk_aspek')->restrictOnDelete();
            $table->foreign('tingkat_id')->references('id')->on('tingkat')->restrictOnDelete();
        });

        Schema::table('tk_tema', function ($table) {
            $table->foreign('sekolah_id')->references('id')->on('sekolah')->restrictOnDelete();
            $table->foreign('cabang_id')->references('id')->on('cabang')->restrictOnDelete();
            $table->foreign('tahun_ajaran_id')->references('id')->on('tahun_ajaran')->restrictOnDelete();
            $table->foreign('semester_id')->references('id')->on('semester')->restrictOnDelete();
        });

        Schema::table('tk_observasi', function ($table) {
            $table->foreign('siswa_id')->references('id')->on('siswa')->restrictOnDelete();
            $table->foreign('cabang_id')->references('id')->on('cabang')->restrictOnDelete();
            $table->foreign('rombel_id')->references('id')->on('rombel')->restrictOnDelete();
            $table->foreign('semester_id')->references('id')->on('semester')->restrictOnDelete();
            $table->foreign('tema_id')->references('id')->on('tk_tema')->restrictOnDelete();
            $table->foreign('aspek_id')->references('id')->on('tk_aspek')->restrictOnDelete();
            $table->foreign('pengamat_id')->references('id')->on('pegawai')->restrictOnDelete();
        });

        Schema::table('tk_ceklis_capaian', function ($table) {
            $table->foreign('siswa_id')->references('id')->on('siswa')->restrictOnDelete();
            $table->foreign('cabang_id')->references('id')->on('cabang')->restrictOnDelete();
            $table->foreign('semester_id')->references('id')->on('semester')->restrictOnDelete();
            $table->foreign('indikator_id')->references('id')->on('tk_indikator')->restrictOnDelete();
            $table->foreign('skala_butir_id')->references('id')->on('skala_penilaian_butir')->restrictOnDelete();
            $table->foreign('pengamat_id')->references('id')->on('pegawai')->restrictOnDelete();
            $table->foreign('observasi_id')->references('id')->on('tk_observasi')->restrictOnDelete();
        });

        Schema::table('tk_hasil_karya', function ($table) {
            $table->foreign('siswa_id')->references('id')->on('siswa')->restrictOnDelete();
            $table->foreign('cabang_id')->references('id')->on('cabang')->restrictOnDelete();
            $table->foreign('semester_id')->references('id')->on('semester')->restrictOnDelete();
            $table->foreign('tema_id')->references('id')->on('tk_tema')->restrictOnDelete();
            $table->foreign('aspek_id')->references('id')->on('tk_aspek')->restrictOnDelete();
            $table->foreign('pengamat_id')->references('id')->on('pegawai')->restrictOnDelete();
        });

        Schema::table('tk_asesmen_semester', function ($table) {
            $table->foreign('siswa_id')->references('id')->on('siswa')->restrictOnDelete();
            $table->foreign('semester_id')->references('id')->on('semester')->restrictOnDelete();
            $table->foreign('aspek_id')->references('id')->on('tk_aspek')->restrictOnDelete();
            $table->foreign('skala_butir_id')->references('id')->on('skala_penilaian_butir')->restrictOnDelete();
            $table->foreign('disusun_oleh')->references('id')->on('pegawai')->restrictOnDelete();
        });

        Schema::table('tk_refleksi_orang_tua', function ($table) {
            $table->foreign('siswa_id')->references('id')->on('siswa')->restrictOnDelete();
            $table->foreign('semester_id')->references('id')->on('semester')->restrictOnDelete();
            $table->foreign('orang_id')->references('id')->on('orang')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE tk_tema ADD CONSTRAINT tk_tema_tanggal_chk CHECK (tanggal_selesai IS NULL OR tanggal_mulai IS NULL OR tanggal_selesai >= tanggal_mulai)');
    }

    /** Reverse the migrations. */
    public function down(): void
    {
        Schema::dropIfExists('tk_refleksi_orang_tua');
        Schema::dropIfExists('tk_asesmen_semester');
        Schema::dropIfExists('tk_hasil_karya');
        Schema::dropIfExists('tk_ceklis_capaian');
        Schema::dropIfExists('tk_observasi');
        Schema::dropIfExists('tk_tema');
        Schema::dropIfExists('tk_indikator');
        Schema::dropIfExists('tk_aspek');
        Schema::dropIfExists('rapor_butir');
        Schema::dropIfExists('rapor');
        Schema::dropIfExists('rapor_templat_bagian');
        Schema::dropIfExists('rapor_templat');
        Schema::dropIfExists('keputusan_kenaikan');
        Schema::dropIfExists('perkembangan_siswa');
    }
};