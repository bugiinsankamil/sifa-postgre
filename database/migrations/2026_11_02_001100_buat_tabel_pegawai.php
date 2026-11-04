<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pegawai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orang_id')->unique()->constrained('orang')->restrictOnDelete();
            $table->string('nip_yayasan', 30)->unique();
            $table->string('tipe', 20);                      // guru/tendik/staf/pimpinan
            $table->string('status_kepegawaian_ref', 50);
            $table->date('tanggal_masuk');
            $table->date('tanggal_keluar')->nullable();
            $table->string('alasan_keluar')->nullable();
            $table->string('status', 20)->default('aktif');  // aktif/cuti/nonaktif/resign
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('tipe');
        });
        DB::statement('ALTER TABLE pegawai ADD CONSTRAINT pegawai_tanggal_chk CHECK (tanggal_keluar IS NULL OR tanggal_keluar >= tanggal_masuk)');

        Schema::create('jabatan', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();
            $table->string('nama');
            $table->string('kategori', 20);                  // struktural/fungsional/pendukung
            $table->foreignId('unit_kerja_id')->nullable()->constrained('unit_kerja')->nullOnDelete();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        // Riwayat penugasan. Satu pegawai boleh punya beberapa penugasan aktif.
        Schema::create('penugasan_pegawai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->restrictOnDelete();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->unsignedBigInteger('sekolah_id')->nullable();
            $table->foreignId('unit_kerja_id')->nullable()->constrained('unit_kerja')->nullOnDelete();
            $table->foreignId('jabatan_id')->constrained('jabatan')->restrictOnDelete();
            $table->date('tmt');                             // tanggal mulai tugas
            $table->date('tanggal_akhir')->nullable();
            $table->boolean('is_utama')->default(false);
            $table->string('no_sk', 100)->nullable();
            $table->string('status', 20)->default('aktif');
            $table->timestamps();

            $table->index(['pegawai_id', 'status']);
            $table->index(['cabang_id', 'status']);
            // Bila sekolah diisi, harus sekolah di cabang yang sama
            $table->foreign(['sekolah_id', 'cabang_id'])
                ->references(['id', 'cabang_id'])->on('sekolah')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE penugasan_pegawai ADD CONSTRAINT penugasan_pegawai_tanggal_chk CHECK (tanggal_akhir IS NULL OR tanggal_akhir >= tmt)');
        // Satu pegawai hanya boleh punya satu penugasan utama yang masih berjalan
        DB::statement('CREATE UNIQUE INDEX penugasan_pegawai_satu_utama ON penugasan_pegawai (pegawai_id) WHERE is_utama AND tanggal_akhir IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('penugasan_pegawai');
        Schema::dropIfExists('jabatan');
        Schema::dropIfExists('pegawai');
    }
};
