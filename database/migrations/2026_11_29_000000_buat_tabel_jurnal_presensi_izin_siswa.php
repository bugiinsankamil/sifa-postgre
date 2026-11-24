<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Run the migrations. */
    public function up(): void
    {
        Schema::create('jurnal_mengajar', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('penugasan_mengajar_id')->nullable();
            $table->unsignedInteger('cabang_id')->nullable();
            $table->date('tanggal');
            // jam_pelajaran_id NULL = presensi harian (TK/SD); diisi = per jam (SMP)
            $table->unsignedInteger('jam_pelajaran_id')->nullable();
            $table->unsignedInteger('pegawai_id')->nullable(); // pengajar sebenarnya pada hari itu
            $table->text('materi')->nullable();
            $table->text('kegiatan')->nullable();
            $table->text('catatan')->nullable();
            $table->enum('status', ['hadir', 'diganti', 'kosong'])->default('hadir');
            $table->timestamps();

            $table->index('penugasan_mengajar_id');
            $table->index('cabang_id');
            $table->index('tanggal');
            $table->index('jam_pelajaran_id');
            $table->index('pegawai_id');
        });

        Schema::create('presensi_siswa', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cabang_id')->nullable();
            $table->unsignedInteger('rombel_id')->nullable();
            $table->unsignedInteger('siswa_id')->nullable();
            $table->date('tanggal');
            // jurnal_mengajar_id NULL = harian TK/SD; diisi = per jam SMP
            $table->unsignedInteger('jurnal_mengajar_id')->nullable();
            $table->enum('status', ['hadir', 'sakit', 'izin', 'terlambat', 'alpa'])->default('hadir');
            $table->text('keterangan')->nullable();
            $table->unsignedInteger('dicatat_oleh')->nullable(); // users
            $table->timestamps();

            $table->index('cabang_id');
            $table->index('rombel_id');
            $table->index('siswa_id');
            $table->index('tanggal');
            $table->index('jurnal_mengajar_id');
            $table->index('dicatat_oleh');
        });

        Schema::create('izin_siswa', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('siswa_id')->nullable();
            $table->unsignedInteger('cabang_id')->nullable();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->enum('jenis', ['sakit', 'izin'])->nullable();
            $table->text('alasan')->nullable();
            $table->unsignedInteger('diajukan_oleh')->nullable(); // orang
            $table->enum('status', ['diajukan', 'disetujui', 'ditolak', 'dibatalkan'])->default('diajukan');
            $table->timestamps();

            $table->index('siswa_id');
            $table->index('cabang_id');
            $table->index('tanggal_mulai');
            $table->index('tanggal_selesai');
            $table->index('diajukan_oleh');
            $table->index('status');
        });

        // FK jurnal_mengajar
        Schema::table('jurnal_mengajar', function ($table) {
            $table->foreign('penugasan_mengajar_id')->references('id')->on('penugasan_mengajar')->restrictOnDelete();
            $table->foreign('cabang_id')->references('id')->on('cabang')->restrictOnDelete();
            $table->foreign('jam_pelajaran_id')->references('id')->on('jam_pelajaran')->restrictOnDelete();
            $table->foreign('pegawai_id')->references('id')->on('pegawai')->restrictOnDelete();
        });

        // FK presensi_siswa (jurnal_mengajar sudah ada di atas)
        Schema::table('presensi_siswa', function ($table) {
            $table->foreign('cabang_id')->references('id')->on('cabang')->restrictOnDelete();
            $table->foreign('rombel_id')->references('id')->on('rombel')->restrictOnDelete();
            $table->foreign('siswa_id')->references('id')->on('siswa')->restrictOnDelete();
            $table->foreign('jurnal_mengajar_id')->references('id')->on('jurnal_mengajar')->restrictOnDelete();
            $table->foreign('dicatat_oleh')->references('id')->on('users')->restrictOnDelete();
        });

        // FK izin_siswa
        Schema::table('izin_siswa', function ($table) {
            $table->foreign('siswa_id')->references('id')->on('siswa')->restrictOnDelete();
            $table->foreign('cabang_id')->references('id')->on('cabang')->restrictOnDelete();
            $table->foreign('diajukan_oleh')->references('id')->on('orang')->restrictOnDelete();
        });

        // Check: izin rentang tanggal
        DB::statement('ALTER TABLE izin_siswa ADD CONSTRAINT izin_siswa_tanggal_chk CHECK (tanggal_selesai >= tanggal_mulai)');

        // Partial unique index PostgreSQL:
        //  Harian TK/SD (jurnal_mengajar_id NULL): unik per siswa + tanggal
        //  Per jam SMP (jurnal_mengajar_id NOT NULL): unik per siswa + tanggal + jurnal
        // NULL dianggap beda di PostgreSQL, jadi tidak bisa pakai unique tunggal pada 3 kolom.
        DB::statement('CREATE UNIQUE INDEX presensi_siswa_harian_uniq ON presensi_siswa (siswa_id, tanggal) WHERE jurnal_mengajar_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX presensi_siswa_perjam_uniq ON presensi_siswa (siswa_id, tanggal, jurnal_mengajar_id) WHERE jurnal_mengajar_id IS NOT NULL');
    }

    /** Reverse the migrations. */
    public function down(): void
    {
        Schema::dropIfExists('izin_siswa');
        Schema::dropIfExists('presensi_siswa');
        Schema::dropIfExists('jurnal_mengajar');
    }
};
