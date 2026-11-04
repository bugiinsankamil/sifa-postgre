<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orang_id')->unique()->constrained('orang')->restrictOnDelete();
            $table->string('nis_yayasan', 30)->unique();
            $table->string('nisn', 20)->nullable()->unique();
            $table->unsignedSmallInteger('anak_ke')->nullable();
            $table->unsignedSmallInteger('jumlah_saudara')->nullable();
            $table->date('tanggal_masuk_yayasan');
            $table->string('status', 20)->default('aktif');  // aktif/lulus/pindah/keluar/cuti
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        // Riwayat keanggotaan siswa di satuan pendidikan (naik jenjang, pindah antarcabang)
        Schema::create('siswa_sekolah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->restrictOnDelete();
            $table->unsignedBigInteger('sekolah_id');
            $table->unsignedBigInteger('cabang_id');
            $table->string('nis_lokal', 30)->nullable();
            $table->date('tanggal_masuk');
            $table->string('jenis_masuk', 30);               // baru/pindahan/naik_jenjang
            $table->string('asal_sekolah')->nullable();
            $table->date('tanggal_keluar')->nullable();
            $table->string('jenis_keluar', 30)->nullable();  // lulus/pindah/keluar
            $table->timestamps();

            $table->index('siswa_id');
            $table->index(['sekolah_id', 'tanggal_keluar']);
            $table->index('cabang_id');
            $table->foreign(['sekolah_id', 'cabang_id'])
                ->references(['id', 'cabang_id'])->on('sekolah')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE siswa_sekolah ADD CONSTRAINT siswa_sekolah_tanggal_chk CHECK (tanggal_keluar IS NULL OR tanggal_keluar >= tanggal_masuk)');
        // Seorang siswa tidak boleh tercatat di dua satuan pendidikan pada periode yang sama.
        // Rentang setengah terbuka [masuk, keluar): tanggal keluar boleh sama dengan tanggal masuk berikutnya.
        DB::statement("ALTER TABLE siswa_sekolah ADD CONSTRAINT siswa_sekolah_tanpa_tumpang_tindih EXCLUDE USING gist (siswa_id WITH =, daterange(tanggal_masuk, tanggal_keluar, '[)') WITH &&)");

        Schema::create('siswa_wali', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('orang_id')->constrained('orang')->restrictOnDelete();
            $table->string('hubungan', 20);                  // ayah/ibu/wali
            $table->string('pekerjaan_ref', 50)->nullable();
            $table->string('pendidikan_ref', 50)->nullable();
            $table->string('penghasilan_ref', 50)->nullable();
            $table->boolean('is_kontak_utama')->default(false);
            $table->boolean('is_penanggung_biaya')->default(false);
            $table->boolean('is_serumah')->default(false);
            $table->timestamps();

            $table->unique(['siswa_id', 'orang_id']);
            $table->index('orang_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siswa_wali');
        Schema::dropIfExists('siswa_sekolah');
        Schema::dropIfExists('siswa');
    }
};
