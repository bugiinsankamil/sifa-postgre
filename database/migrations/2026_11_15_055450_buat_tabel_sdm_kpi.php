<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F2 Kepegawaian/SDM — tahap 2c: KPI/penilaian kinerja (skema 3.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_periode', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->foreignId('cabang_id')->nullable()->constrained('cabang')->nullOnDelete(); // kosong = yayasan
            $table->string('status', 20)->default('draft'); // draft/berjalan/ditutup
            $table->timestamps();
        });
        DB::statement("ALTER TABLE kpi_periode ADD CONSTRAINT kp_status_chk CHECK (status IN ('draft','berjalan','ditutup'))");
        DB::statement('ALTER TABLE kpi_periode ADD CONSTRAINT kp_tanggal_chk CHECK (tanggal_selesai >= tanggal_mulai)');

        Schema::create('kpi_indikator', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            $table->string('satuan', 20)->nullable();
            $table->string('arah', 20); // semakin_tinggi/semakin_rendah
            $table->string('sumber_data', 20)->default('manual'); // manual/otomatis
            $table->string('kunci_otomatis', 50)->nullable(); // misalnya kehadiran_persen
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });
        DB::statement("ALTER TABLE kpi_indikator ADD CONSTRAINT ki_arah_chk CHECK (arah IN ('semakin_tinggi','semakin_rendah'))");
        DB::statement("ALTER TABLE kpi_indikator ADD CONSTRAINT ki_sumber_chk CHECK (sumber_data IN ('manual','otomatis'))");

        Schema::create('kpi_templat', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('tipe_pegawai', 20)->nullable(); // guru/tendik/staf/pimpinan
            $table->foreignId('jabatan_id')->nullable()->constrained('jabatan')->nullOnDelete();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('kpi_templat_indikator', function (Blueprint $table) {
            $table->id();
            $table->foreignId('templat_id')->constrained('kpi_templat')->cascadeOnDelete();
            $table->foreignId('indikator_id')->constrained('kpi_indikator')->restrictOnDelete();
            $table->unsignedSmallInteger('bobot'); // 0-100
            $table->decimal('target', 15, 2);
            $table->timestamps();

            $table->unique(['templat_id', 'indikator_id']);
        });
        DB::statement('ALTER TABLE kpi_templat_indikator ADD CONSTRAINT kti_bobot_chk CHECK (bobot > 0 AND bobot <= 100)');

        Schema::create('kpi_penilaian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_id')->constrained('kpi_periode')->restrictOnDelete();
            $table->foreignId('pegawai_id')->constrained('pegawai')->restrictOnDelete();
            $table->foreignId('penilai_id')->constrained('pegawai')->restrictOnDelete();
            $table->foreignId('templat_id')->constrained('kpi_templat')->restrictOnDelete();
            $table->decimal('skor_akhir', 5, 2)->nullable();
            $table->string('predikat', 20)->nullable(); // A/B/C/D
            $table->string('status', 20)->default('draft'); // draft/dinilai/disetujui
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['periode_id', 'pegawai_id']);
            $table->index(['periode_id', 'status']);
        });
        DB::statement("ALTER TABLE kpi_penilaian ADD CONSTRAINT kpn_status_chk CHECK (status IN ('draft','dinilai','disetujui'))");
        DB::statement("ALTER TABLE kpi_penilaian ADD CONSTRAINT kpn_predikat_chk CHECK (predikat IS NULL OR predikat IN ('A','B','C','D'))");

        Schema::create('kpi_penilaian_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penilaian_id')->constrained('kpi_penilaian')->cascadeOnDelete();
            $table->foreignId('indikator_id')->constrained('kpi_indikator')->restrictOnDelete();
            $table->unsignedSmallInteger('bobot');
            $table->decimal('target', 15, 2);
            $table->decimal('realisasi', 15, 2)->nullable();
            $table->decimal('skor', 5, 2)->nullable(); // 0-100
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['penilaian_id', 'indikator_id']);
        });
        DB::statement('ALTER TABLE kpi_penilaian_detail ADD CONSTRAINT kpd_skor_chk CHECK (skor IS NULL OR (skor >= 0 AND skor <= 100))');
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_penilaian_detail');
        Schema::dropIfExists('kpi_penilaian');
        Schema::dropIfExists('kpi_templat_indikator');
        Schema::dropIfExists('kpi_templat');
        Schema::dropIfExists('kpi_indikator');
        Schema::dropIfExists('kpi_periode');
    }
};
