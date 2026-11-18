<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F2 Kepegawaian/SDM — tahap 2b: cuti & izin (skema 3.3).
 * Persetujuan bertingkat: diajukan → disetujui_atasan → disetujui.
 * Setengah hari, lintas tahun, dan saldo dihitung berdasarkan rincian hari.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hari_libur', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->nullable()->constrained('cabang')->nullOnDelete();
            $table->date('tanggal');
            $table->string('nama');
            $table->boolean('is_cuti_bersama')->default(false);
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['cabang_id', 'tanggal']);
            $table->index(['cabang_id', 'tanggal', 'is_cuti_bersama']);
        });

        Schema::create('jenis_cuti', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();
            $table->string('nama');
            $table->unsignedSmallInteger('kuota_hari_tahunan')->nullable();
            $table->boolean('is_dibayar')->default(true);
            $table->boolean('perlu_lampiran')->default(false);
            $table->jsonb('aturan')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('saldo_cuti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->foreignId('jenis_cuti_id')->constrained('jenis_cuti')->restrictOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->decimal('kuota', 5, 1)->default(0);
            $table->decimal('terpakai', 5, 1)->default(0);
            $table->decimal('dibawa', 5, 1)->default(0);
            $table->timestamps();

            $table->unique(['pegawai_id', 'jenis_cuti_id', 'tahun']);
            $table->index(['pegawai_id', 'tahun']);
        });
        DB::statement('ALTER TABLE saldo_cuti ADD CONSTRAINT sc_sisa_chk CHECK (terpakai <= kuota + dibawa)');

        Schema::create('mutasi_saldo_cuti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->foreignId('jenis_cuti_id')->constrained('jenis_cuti')->restrictOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->string('tipe', 20);
            $table->decimal('jumlah_hari', 5, 1);
            $table->decimal('saldo_sebelum', 5, 1);
            $table->decimal('saldo_sesudah', 5, 1);
            $table->foreignId('pengajuan_cuti_id')->nullable()->constrained('pengajuan_cuti')->nullOnDelete();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->text('keterangan')->nullable();
            $table->string('kode_unik', 64)->unique();
            $table->timestamps();

            $table->index(['pegawai_id', 'jenis_cuti_id', 'tahun']);
            $table->index('pengajuan_cuti_id');
        });
        DB::statement("ALTER TABLE mutasi_saldo_cuti ADD CONSTRAINT msc_tipe_chk CHECK (tipe IN ('pemberian','pemakaian','pengembalian','penyesuaian','carry_over'))");

        Schema::create('pengajuan_cuti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->restrictOnDelete();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->foreignId('jenis_cuti_id')->constrained('jenis_cuti')->restrictOnDelete();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->decimal('jumlah_hari', 5, 1);
            $table->unsignedSmallInteger('jumlah_setengah_hari')->default(0);
            $table->text('alasan')->nullable();
            $table->foreignId('pengganti_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->string('status', 30)->default('diajukan');
            $table->foreignId('disetujui_atasan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disetujui_atasan_pada')->nullable();
            $table->foreignId('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disetujui_pada')->nullable();
            $table->text('alasan_penolakan')->nullable();
            $table->text('alasan_pembatalan')->nullable();
            $table->foreignId('dibatalkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dibatalkan_pada')->nullable();
            $table->timestamps();

            $table->index(['pegawai_id', 'status']);
            $table->index(['cabang_id', 'status']);
            $table->index(['tanggal_mulai', 'tanggal_selesai']);
        });
        DB::statement("ALTER TABLE pengajuan_cuti ADD CONSTRAINT pc_status_chk CHECK (status IN ('diajukan','disetujui_atasan','disetujui','ditolak','dibatalkan'))");
        DB::statement('ALTER TABLE pengajuan_cuti ADD CONSTRAINT pc_tanggal_chk CHECK (tanggal_selesai >= tanggal_mulai)');

        Schema::create('pengajuan_cuti_hari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_cuti_id')->constrained('pengajuan_cuti')->cascadeOnDelete();
            $table->date('tanggal');
            $table->unsignedSmallInteger('tahun');
            $table->decimal('nilai', 3, 2);
            $table->string('sesi', 10)->default('penuh');
            $table->string('status', 20)->default('terhitung');
            $table->foreignId('absensi_pegawai_id')->nullable()->constrained('absensi_pegawai')->nullOnDelete();
            $table->timestamps();

            $table->unique(['pengajuan_cuti_id', 'tanggal', 'sesi']);
            $table->index(['pengajuan_cuti_id', 'tahun']);
        });
        DB::statement("ALTER TABLE pengajuan_cuti_hari ADD CONSTRAINT pch_nilai_chk CHECK (nilai IN (0.50, 1.00))");
        DB::statement("ALTER TABLE pengajuan_cuti_hari ADD CONSTRAINT pch_sesi_chk CHECK (sesi IN ('penuh','pagi','siang'))");
        DB::statement("ALTER TABLE pengajuan_cuti_hari ADD CONSTRAINT pch_status_chk CHECK (status IN ('terhitung','libur','tidak_aktif'))");

        Schema::create('pengajuan_izin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->restrictOnDelete();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->string('jenis', 20);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->decimal('jumlah_hari', 5, 1);
            $table->unsignedSmallInteger('jumlah_setengah_hari')->default(0);
            $table->text('alasan')->nullable();
            $table->string('status', 30)->default('diajukan');
            $table->foreignId('disetujui_atasan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disetujui_atasan_pada')->nullable();
            $table->foreignId('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disetujui_pada')->nullable();
            $table->text('alasan_penolakan')->nullable();
            $table->text('alasan_pembatalan')->nullable();
            $table->foreignId('dibatalkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dibatalkan_pada')->nullable();
            $table->timestamps();

            $table->index(['pegawai_id', 'status']);
            $table->index(['cabang_id', 'status']);
            $table->index(['tanggal_mulai', 'tanggal_selesai']);
        });
        DB::statement("ALTER TABLE pengajuan_izin ADD CONSTRAINT pi_jenis_chk CHECK (jenis IN ('izin','sakit','dinas_luar'))");
        DB::statement("ALTER TABLE pengajuan_izin ADD CONSTRAINT pi_status_chk CHECK (status IN ('diajukan','disetujui_atasan','disetujui','ditolak','dibatalkan'))");
        DB::statement('ALTER TABLE pengajuan_izin ADD CONSTRAINT pi_tanggal_chk CHECK (tanggal_selesai >= tanggal_mulai)');

        Schema::create('pengajuan_izin_hari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_izin_id')->constrained('pengajuan_izin')->cascadeOnDelete();
            $table->date('tanggal');
            $table->unsignedSmallInteger('tahun');
            $table->decimal('nilai', 3, 2);
            $table->string('sesi', 10)->default('penuh');
            $table->string('status', 20)->default('terhitung');
            $table->foreignId('absensi_pegawai_id')->nullable()->constrained('absensi_pegawai')->nullOnDelete();
            $table->timestamps();

            $table->unique(['pengajuan_izin_id', 'tanggal', 'sesi']);
            $table->index(['pengajuan_izin_id', 'tahun']);
        });
        DB::statement("ALTER TABLE pengajuan_izin_hari ADD CONSTRAINT pih_nilai_chk CHECK (nilai IN (0.50, 1.00))");
        DB::statement("ALTER TABLE pengajuan_izin_hari ADD CONSTRAINT pih_sesi_chk CHECK (sesi IN ('penuh','pagi','siang'))");
        DB::statement("ALTER TABLE pengajuan_izin_hari ADD CONSTRAINT pih_status_chk CHECK (status IN ('terhitung','libur','tidak_aktif'))");

        // Penanda sumber: absensi yang berasal dari persetujuan cuti/izin terkunci dari edit manual
        // (lihat AbsensiService::catatManual). Satu absensi tidak boleh berasal dari cuti dan izin sekaligus.
        Schema::table('absensi_pegawai', function (Blueprint $table) {
            $table->foreignId('pengajuan_cuti_id')->nullable()->after('diajukan_oleh')
                ->constrained('pengajuan_cuti')->nullOnDelete();
            $table->foreignId('pengajuan_izin_id')->nullable()->after('pengajuan_cuti_id')
                ->constrained('pengajuan_izin')->nullOnDelete();
        });
        DB::statement('ALTER TABLE absensi_pegawai ADD CONSTRAINT ap_satu_sumber_chk CHECK (pengajuan_cuti_id IS NULL OR pengajuan_izin_id IS NULL)');
    }

    public function down(): void
    {
        Schema::table('absensi_pegawai', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pengajuan_izin_id');
            $table->dropConstrainedForeignId('pengajuan_cuti_id');
        });
        Schema::dropIfExists('pengajuan_izin_hari');
        Schema::dropIfExists('pengajuan_izin');
        Schema::dropIfExists('pengajuan_cuti_hari');
        Schema::dropIfExists('pengajuan_cuti');
        Schema::dropIfExists('mutasi_saldo_cuti');
        Schema::dropIfExists('saldo_cuti');
        Schema::dropIfExists('hari_libur');
        Schema::dropIfExists('jenis_cuti');
    }
};
