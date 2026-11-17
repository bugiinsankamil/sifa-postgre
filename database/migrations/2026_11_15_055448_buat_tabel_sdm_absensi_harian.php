<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F2 Kepegawaian/SDM — tahap 2a: absensi harian dan log mentah mesin (skema 3.2).
 * Absensi_pegawai adalah hasil olahan log mentah; duplikasi log dihindari lewat id_unik_perangkat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absensi_log_mentah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->foreignId('perangkat_id')->nullable()->constrained('perangkat_absensi')->nullOnDelete();
            $table->foreignId('pegawai_id')->nullable()->constrained('pegawai')->restrictOnDelete();
            $table->string('pin', 30)->nullable();
            $table->timestamp('waktu');
            $table->string('metode', 20)->default('mesin'); // mesin/gps/qrcode/manual
            $table->string('tipe', 10)->default('masuk'); // masuk/pulang
            $table->string('id_unik_perangkat', 100)->nullable(); // SN/scan id dari mesin, unik per perangkat
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestamp('diproses_pada')->nullable();
            $table->timestamps();

            $table->index(['cabang_id', 'perangkat_id', 'waktu']);
            $table->index(['pegawai_id', 'waktu']);
            $table->index('diproses_pada');
        });
        DB::statement("ALTER TABLE absensi_log_mentah ADD CONSTRAINT alm_metode_chk CHECK (metode IN ('mesin','gps','qrcode','manual'))");
        DB::statement("ALTER TABLE absensi_log_mentah ADD CONSTRAINT alm_tipe_chk CHECK (tipe IN ('masuk','pulang'))");
        // Deduplikasi log di tingkat database: satu scan id per perangkat
        DB::statement('CREATE UNIQUE INDEX absensi_log_mentah_dedup ON absensi_log_mentah (perangkat_id, id_unik_perangkat) WHERE perangkat_id IS NOT NULL AND id_unik_perangkat IS NOT NULL');

        Schema::create('absensi_pegawai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->restrictOnDelete();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->date('tanggal');
            $table->foreignId('shift_id')->nullable()->constrained('shift_kerja')->nullOnDelete();
            $table->time('jam_masuk')->nullable();
            $table->time('jam_pulang')->nullable();
            $table->string('status', 20)->default('alpa'); // hadir/terlambat/izin/sakit/cuti/dinas/alpa
            $table->string('metode', 20)->default('manual'); // mesin/gps/qrcode/manual
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('foto_path')->nullable();
            $table->unsignedSmallInteger('terlambat_menit')->default(0);
            $table->unsignedSmallInteger('pulang_cepat_menit')->default(0);
            $table->string('keterangan')->nullable();
            $table->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['pegawai_id', 'tanggal']);
            $table->index(['cabang_id', 'tanggal']);
            $table->index(['pegawai_id', 'tanggal']);
        });
        DB::statement("ALTER TABLE absensi_pegawai ADD CONSTRAINT ap_status_chk CHECK (status IN ('hadir','terlambat','izin','sakit','cuti','dinas','alpa'))");
        DB::statement("ALTER TABLE absensi_pegawai ADD CONSTRAINT ap_metode_chk CHECK (metode IN ('mesin','gps','qrcode','manual'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi_pegawai');
        Schema::dropIfExists('absensi_log_mentah');
    }
};
