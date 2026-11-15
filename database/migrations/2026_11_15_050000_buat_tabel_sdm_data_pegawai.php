<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F2 Kepegawaian/SDM — tahap 1a (skema v0.2 bagian 3.1: data pegawai tambahan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pegawai_pendidikan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->string('jenjang_pendidikan_ref', 50); // referensi kategori pendidikan
            $table->string('institusi');
            $table->string('jurusan')->nullable();
            $table->unsignedSmallInteger('tahun_lulus')->nullable();
            $table->decimal('ipk', 3, 2)->nullable();
            $table->timestamps();
            $table->index('pegawai_id');
        });
        DB::statement('ALTER TABLE pegawai_pendidikan ADD CONSTRAINT pp_ipk_chk CHECK (ipk IS NULL OR (ipk >= 0 AND ipk <= 4))');
        DB::statement('ALTER TABLE pegawai_pendidikan ADD CONSTRAINT pp_tahun_chk CHECK (tahun_lulus IS NULL OR (tahun_lulus >= 1900 AND tahun_lulus <= 2100))');

        Schema::create('pegawai_keluarga', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->string('nama');
            $table->string('hubungan_ref', 50); // referensi kategori hubungan_keluarga
            $table->date('tanggal_lahir')->nullable();
            $table->boolean('is_tanggungan')->default(false);
            $table->timestamps();
            $table->index('pegawai_id');
        });

        // Satu baris per pegawai; NPWP disimpan terenkripsi di model.
        Schema::create('pegawai_pajak_bpjs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->unique()->constrained('pegawai')->cascadeOnDelete();
            $table->text('npwp')->nullable();
            $table->string('status_ptkp', 10)->nullable(); // TK/0, K/0, K/1, dst.
            $table->string('no_bpjs_kesehatan', 30)->nullable();
            $table->string('no_bpjs_tk', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('rekening_pegawai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->string('bank_ref', 50); // referensi kategori bank
            $table->text('nomor_rekening');   // terenkripsi di model
            $table->string('atas_nama');
            $table->boolean('is_utama')->default(false);
            $table->timestamps();
            $table->index('pegawai_id');
        });
        // Satu rekening utama per pegawai
        DB::statement('CREATE UNIQUE INDEX rekening_pegawai_satu_utama ON rekening_pegawai (pegawai_id) WHERE is_utama');

        Schema::create('pegawai_riwayat_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->string('status_kepegawaian_ref', 50);
            $table->date('tmt');
            $table->string('no_sk', 100)->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->unique(['pegawai_id', 'status_kepegawaian_ref', 'tmt'], 'prs_uk');
            $table->index('pegawai_id');
        });

        Schema::create('kontrak_kerja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->string('nomor', 100)->unique();
            $table->string('jenis', 20); // pkwt/tetap/honor
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->decimal('gaji_pokok', 15, 2)->nullable();
            $table->string('status', 20)->default('aktif'); // aktif/berakhir/diperpanjang/dibatalkan
            $table->timestamps();
            $table->index(['pegawai_id', 'status']);
            $table->index('tanggal_selesai'); // untuk peringatan kontrak akan berakhir
        });
        DB::statement("ALTER TABLE kontrak_kerja ADD CONSTRAINT kk_jenis_chk CHECK (jenis IN ('pkwt','tetap','honor'))");
        DB::statement("ALTER TABLE kontrak_kerja ADD CONSTRAINT kk_status_chk CHECK (status IN ('aktif','berakhir','diperpanjang','dibatalkan'))");
        DB::statement('ALTER TABLE kontrak_kerja ADD CONSTRAINT kk_tanggal_chk CHECK (tanggal_selesai IS NULL OR tanggal_selesai >= tanggal_mulai)');
        // Maksimal satu kontrak aktif per pegawai
        DB::statement("CREATE UNIQUE INDEX kontrak_kerja_satu_aktif ON kontrak_kerja (pegawai_id) WHERE status = 'aktif'");

        Schema::create('pegawai_sertifikasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->string('jenis_ref', 50); // referensi kategori jenis_sertifikasi
            $table->string('nomor', 100)->nullable();
            $table->string('penerbit')->nullable();
            $table->date('tanggal_terbit')->nullable();
            $table->date('tanggal_kedaluwarsa')->nullable();
            $table->timestamps();
            $table->index('pegawai_id');
        });
        DB::statement('ALTER TABLE pegawai_sertifikasi ADD CONSTRAINT ps_tanggal_chk CHECK (tanggal_kedaluwarsa IS NULL OR tanggal_terbit IS NULL OR tanggal_kedaluwarsa >= tanggal_terbit)');
    }

    public function down(): void
    {
        Schema::dropIfExists('pegawai_sertifikasi');
        Schema::dropIfExists('kontrak_kerja');
        Schema::dropIfExists('pegawai_riwayat_status');
        Schema::dropIfExists('rekening_pegawai');
        Schema::dropIfExists('pegawai_pajak_bpjs');
        Schema::dropIfExists('pegawai_keluarga');
        Schema::dropIfExists('pegawai_pendidikan');
    }
};

