<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---- Periode dan jalur ------------------------------------------------------
        Schema::create('spmb_periode', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cabang_id');
            $table->unsignedBigInteger('sekolah_id');
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->restrictOnDelete();
            $table->string('nama');
            $table->unsignedSmallInteger('gelombang')->default(1);
            $table->date('tanggal_buka');
            $table->date('tanggal_tutup');
            $table->unsignedInteger('kuota');
            $table->decimal('biaya_pendaftaran', 15, 2)->default(0);
            $table->string('status', 20)->default('draft');   // draft/dibuka/ditutup/selesai
            $table->timestamps();

            $table->unique(['sekolah_id', 'tahun_ajaran_id', 'gelombang']);
            // Target FK komposit untuk spmb_pendaftar: periode, cabang, dan sekolah selalu cocok
            $table->unique(['id', 'cabang_id', 'sekolah_id']);
            $table->index(['cabang_id', 'status']);
            $table->foreign(['sekolah_id', 'cabang_id'])
                ->references(['id', 'cabang_id'])->on('sekolah')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE spmb_periode ADD CONSTRAINT spmb_periode_tanggal_chk CHECK (tanggal_tutup >= tanggal_buka)');

        Schema::create('spmb_jalur', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_id')->constrained('spmb_periode')->restrictOnDelete();
            $table->string('nama');
            $table->unsignedInteger('kuota')->nullable();
            $table->jsonb('persyaratan')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();

            $table->unique(['periode_id', 'nama']);
            $table->unique(['id', 'periode_id']);
        });

        Schema::create('spmb_persyaratan_berkas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_id')->constrained('spmb_periode')->cascadeOnDelete();
            $table->string('nama');
            $table->boolean('is_wajib')->default(true);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();

            $table->unique(['id', 'periode_id']);
            $table->index('periode_id');
        });

        // ---- Prospek (CRM): calon pelanggan sebelum mendaftar -------------------
        Schema::create('spmb_kampanye', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->nullable()->constrained('cabang')->restrictOnDelete();
            $table->string('nama');
            $table->string('kanal_ref', 50);                  // referensi: spmb_kanal_kampanye
            $table->date('tanggal_mulai');
            $table->date('tanggal_akhir')->nullable();
            $table->decimal('anggaran', 15, 2)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE spmb_kampanye ADD CONSTRAINT spmb_kampanye_tanggal_chk CHECK (tanggal_akhir IS NULL OR tanggal_akhir >= tanggal_mulai)');

        Schema::create('spmb_kontak', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cabang_id');
            $table->unsignedBigInteger('sekolah_id')->nullable();   // sekolah yang diminati
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->restrictOnDelete();
            $table->string('nama_kontak');                    // orang tua/wali yang dihubungi
            $table->string('telepon', 30);                    // dinormalisasi: 62812xxxx
            $table->string('whatsapp', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('nama_calon_siswa')->nullable();
            $table->foreignId('jenjang_minat_id')->nullable()->constrained('jenjang')->nullOnDelete();
            $table->string('sumber_ref', 50);                 // referensi: spmb_sumber
            $table->foreignId('kampanye_id')->nullable()->constrained('spmb_kampanye')->nullOnDelete();
            $table->foreignId('perujuk_id')->nullable()->constrained('orang')->nullOnDelete();
            $table->foreignId('petugas_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->string('status', 20)->default('baru');    // baru/dihubungi/tertarik/mendaftar/tidak_lanjut
            $table->string('alasan_tidak_lanjut')->nullable();
            $table->timestamp('kontak_pertama_pada')->useCurrent();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['cabang_id', 'tahun_ajaran_id', 'status']);
            $table->index(['petugas_id', 'status']);
            $table->index('kampanye_id');
            $table->foreign(['sekolah_id', 'cabang_id'])
                ->references(['id', 'cabang_id'])->on('sekolah')->restrictOnDelete();
        });
        // Mencegah kontak ganda: nomor yang sama boleh muncul lagi untuk anak yang berbeda (kakak-adik),
        // dan boleh menghubungi cabang yang berbeda.
        DB::statement("CREATE UNIQUE INDEX spmb_kontak_unik ON spmb_kontak (cabang_id, tahun_ajaran_id, telepon, LOWER(COALESCE(nama_calon_siswa, '')))");

        Schema::create('spmb_tindak_lanjut', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kontak_id')->constrained('spmb_kontak')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('kanal', 20);                      // whatsapp/telepon/kunjungan/email
            $table->foreignId('petugas_id')->constrained('pegawai')->restrictOnDelete();
            $table->string('hasil', 150);
            $table->text('catatan')->nullable();
            $table->date('jadwal_berikutnya')->nullable();
            $table->string('status_setelah', 20)->nullable();
            $table->timestamps();

            $table->index(['kontak_id', 'tanggal']);
            $table->index(['jadwal_berikutnya']);
        });

        Schema::create('spmb_kegiatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->foreignId('periode_id')->nullable()->constrained('spmb_periode')->nullOnDelete();
            $table->string('nama');
            $table->string('jenis', 30);                      // open_house/school_tour/webinar/lainnya
            $table->date('tanggal');
            $table->string('lokasi')->nullable();
            $table->unsignedInteger('kuota')->nullable();
            $table->timestamps();

            $table->index(['cabang_id', 'tanggal']);
        });

        Schema::create('spmb_kegiatan_peserta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_id')->constrained('spmb_kegiatan')->cascadeOnDelete();
            $table->foreignId('kontak_id')->constrained('spmb_kontak')->cascadeOnDelete();
            $table->string('status', 20)->default('daftar');  // daftar/hadir/tidak_hadir
            $table->timestamps();

            $table->unique(['kegiatan_id', 'kontak_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spmb_kegiatan_peserta');
        Schema::dropIfExists('spmb_kegiatan');
        Schema::dropIfExists('spmb_tindak_lanjut');
        Schema::dropIfExists('spmb_kontak');
        Schema::dropIfExists('spmb_kampanye');
        Schema::dropIfExists('spmb_persyaratan_berkas');
        Schema::dropIfExists('spmb_jalur');
        Schema::dropIfExists('spmb_periode');
    }
};
