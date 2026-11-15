<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F2 Kepegawaian/SDM — tahap 1b: master absensi (skema 3.2) dan pelatihan (skema 3.5).
 * Absensi harian (absensi_pegawai, absensi_log_mentah, pegawai_shift olahan) dikerjakan
 * pada tahap berikutnya bersama cuti, KPI, dan payroll.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ============ 3.2 Master absensi ============

        Schema::create('lokasi_absensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->string('nama');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_meter')->default(100);
            $table->timestamps();
            $table->unique(['cabang_id', 'nama']);
        });
        DB::statement('ALTER TABLE lokasi_absensi ADD CONSTRAINT la_radius_chk CHECK (radius_meter > 0)');

        Schema::create('perangkat_absensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->string('nama');
            $table->string('tipe', 20); // fingerprint/wajah/qr
            $table->string('nomor_seri', 100)->unique();
            $table->string('alamat_ip', 45)->nullable();
            $table->string('status', 20)->default('aktif'); // aktif/nonaktif
            $table->timestamps();
        });
        DB::statement("ALTER TABLE perangkat_absensi ADD CONSTRAINT pa_tipe_chk CHECK (tipe IN ('fingerprint','wajah','qr'))");
        DB::statement("ALTER TABLE perangkat_absensi ADD CONSTRAINT pa_status_chk CHECK (status IN ('aktif','nonaktif'))");

        Schema::create('shift_kerja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->string('nama');
            $table->time('jam_masuk');
            $table->time('jam_pulang');
            $table->unsignedSmallInteger('toleransi_menit')->default(0);
            $table->jsonb('hari_kerja'); // contoh: [1,2,3,4,5] (1=Senin .. 7=Minggu)
            $table->timestamps();
            $table->unique(['cabang_id', 'nama']);
        });
        DB::statement('ALTER TABLE shift_kerja ADD CONSTRAINT sk_jam_chk CHECK (jam_pulang > jam_masuk)');

        Schema::create('pegawai_shift', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shift_kerja')->restrictOnDelete();
            $table->date('tanggal_mulai');
            $table->date('tanggal_akhir')->nullable();
            $table->timestamps();
            $table->index(['pegawai_id', 'tanggal_mulai']);
        });
        DB::statement('ALTER TABLE pegawai_shift ADD CONSTRAINT pgs_tanggal_chk CHECK (tanggal_akhir IS NULL OR tanggal_akhir >= tanggal_mulai)');
        // Satu shift berjalan per pegawai
        DB::statement('CREATE UNIQUE INDEX pegawai_shift_satu_berjalan ON pegawai_shift (pegawai_id) WHERE tanggal_akhir IS NULL');

        // ============ 3.5 Pelatihan ============

        Schema::create('pelatihan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->nullable()->constrained('cabang')->nullOnDelete(); // kosong = yayasan
            $table->string('judul');
            $table->string('jenis_ref', 50); // referensi kategori jenis_pelatihan
            $table->string('penyelenggara')->nullable();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->string('lokasi')->nullable();
            $table->decimal('biaya', 15, 2)->nullable();
            $table->string('status', 20)->default('direncanakan'); // direncanakan/berjalan/selesai/dibatalkan
            $table->timestamps();
            $table->index(['cabang_id', 'status']);
        });
        DB::statement("ALTER TABLE pelatihan ADD CONSTRAINT plt_status_chk CHECK (status IN ('direncanakan','berjalan','selesai','dibatalkan'))");
        DB::statement('ALTER TABLE pelatihan ADD CONSTRAINT plt_tanggal_chk CHECK (tanggal_selesai >= tanggal_mulai)');

        Schema::create('pelatihan_peserta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pelatihan_id')->constrained('pelatihan')->cascadeOnDelete();
            $table->foreignId('pegawai_id')->constrained('pegawai')->restrictOnDelete();
            $table->string('status', 20)->default('terdaftar'); // terdaftar/hadir/lulus/tidak_hadir
            $table->decimal('nilai', 5, 2)->nullable();
            $table->string('nomor_sertifikat', 100)->nullable();
            $table->timestamps();
            $table->unique(['pelatihan_id', 'pegawai_id']);
        });
        DB::statement("ALTER TABLE pelatihan_peserta ADD CONSTRAINT plp_status_chk CHECK (status IN ('terdaftar','hadir','lulus','tidak_hadir'))");
        DB::statement('ALTER TABLE pelatihan_peserta ADD CONSTRAINT plp_nilai_chk CHECK (nilai IS NULL OR (nilai >= 0 AND nilai <= 100))');
    }

    public function down(): void
    {
        Schema::dropIfExists('pelatihan_peserta');
        Schema::dropIfExists('pelatihan');
        Schema::dropIfExists('pegawai_shift');
        Schema::dropIfExists('shift_kerja');
        Schema::dropIfExists('perangkat_absensi');
        Schema::dropIfExists('lokasi_absensi');
    }
};
