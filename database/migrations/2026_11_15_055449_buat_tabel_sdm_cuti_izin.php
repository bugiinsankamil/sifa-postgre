<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F2 Kepegawaian/SDM — tahap 2b: cuti & izin (skema 3.3).
 * Persetujuan langsung via service (engine approval generik belum dibuat).
 */
return new class extends Migration
{
    public function up(): void
    {
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
            $table->unsignedSmallInteger('kuota')->default(0);
            $table->unsignedSmallInteger('terpakai')->default(0);
            $table->timestamps();

            $table->unique(['pegawai_id', 'jenis_cuti_id', 'tahun']);
            $table->index(['pegawai_id', 'tahun']);
        });
        DB::statement('ALTER TABLE saldo_cuti ADD CONSTRAINT sc_sisa_chk CHECK (terpakai <= kuota)');

        Schema::create('pengajuan_cuti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->restrictOnDelete();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->foreignId('jenis_cuti_id')->constrained('jenis_cuti')->restrictOnDelete();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->unsignedSmallInteger('jumlah_hari');
            $table->text('alasan')->nullable();
            $table->foreignId('pengganti_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->string('status', 20)->default('diajukan'); // diajukan/disetujui/ditolak/dibatalkan
            $table->foreignId('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disetujui_pada')->nullable();
            $table->timestamps();

            $table->index(['pegawai_id', 'status']);
            $table->index(['cabang_id', 'status']);
            $table->index(['tanggal_mulai', 'tanggal_selesai']);
        });
        DB::statement("ALTER TABLE pengajuan_cuti ADD CONSTRAINT pc_status_chk CHECK (status IN ('diajukan','disetujui','ditolak','dibatalkan'))");
        DB::statement('ALTER TABLE pengajuan_cuti ADD CONSTRAINT pc_tanggal_chk CHECK (tanggal_selesai >= tanggal_mulai)');

        Schema::create('pengajuan_izin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->restrictOnDelete();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->string('jenis', 20); // izin/sakit/dinas_luar
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->text('alasan')->nullable();
            $table->string('status', 20)->default('diajukan'); // diajukan/disetujui/ditolak/dibatalkan
            $table->foreignId('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disetujui_pada')->nullable();
            $table->timestamps();

            $table->index(['pegawai_id', 'status']);
            $table->index(['cabang_id', 'status']);
            $table->index(['tanggal_mulai', 'tanggal_selesai']);
        });
        DB::statement("ALTER TABLE pengajuan_izin ADD CONSTRAINT pi_jenis_chk CHECK (jenis IN ('izin','sakit','dinas_luar'))");
        DB::statement("ALTER TABLE pengajuan_izin ADD CONSTRAINT pi_status_chk CHECK (status IN ('diajukan','disetujui','ditolak','dibatalkan'))");
        DB::statement('ALTER TABLE pengajuan_izin ADD CONSTRAINT pi_tanggal_chk CHECK (tanggal_selesai >= tanggal_mulai)');
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_izin');
        Schema::dropIfExists('pengajuan_cuti');
        Schema::dropIfExists('saldo_cuti');
        Schema::dropIfExists('jenis_cuti');
    }
};
