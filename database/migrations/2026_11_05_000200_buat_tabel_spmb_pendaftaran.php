<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Biodata pendaftar berdiri sendiri. Baris orang dan siswa baru dibuat saat konversi
        // (pendaftar diterima dan daftar ulang), sehingga pendaftar yang tidak jadi tidak mengotori data pokok.
        Schema::create('spmb_pendaftar', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('periode_id');
            $table->unsignedBigInteger('jalur_id');
            $table->unsignedBigInteger('cabang_id');
            $table->unsignedBigInteger('sekolah_id');
            $table->foreignId('kontak_id')->nullable()->constrained('spmb_kontak')->nullOnDelete();
            $table->string('nomor_pendaftaran', 40)->unique();
            $table->date('tanggal_daftar');
            $table->string('nama_lengkap');
            $table->char('jenis_kelamin', 1);
            $table->string('tempat_lahir', 100);
            $table->date('tanggal_lahir');
            $table->text('nik')->nullable();                              // terenkripsi
            $table->string('nik_hash', 64)->nullable();                   // HMAC-SHA256, unik per periode
            $table->string('nisn', 20)->nullable();
            $table->string('agama_ref', 50)->nullable();
            $table->text('alamat')->nullable();
            $table->foreignId('wilayah_id')->nullable()->constrained('wilayah')->nullOnDelete();
            $table->string('asal_sekolah')->nullable();
            // draft/terdaftar/diverifikasi/seleksi/lulus/cadangan/tidak_lulus/daftar_ulang/mundur/menjadi_siswa
            $table->string('status', 30)->default('draft');
            $table->foreignId('siswa_id')->nullable()->unique()->constrained('siswa')->nullOnDelete();
            $table->timestamps();

            $table->unique(['periode_id', 'nik_hash']);
            $table->unique(['id', 'periode_id']);
            $table->index(['cabang_id', 'status']);
            $table->index(['periode_id', 'status']);
            $table->foreign(['periode_id', 'cabang_id', 'sekolah_id'])
                ->references(['id', 'cabang_id', 'sekolah_id'])->on('spmb_periode')->restrictOnDelete();
            $table->foreign(['jalur_id', 'periode_id'])
                ->references(['id', 'periode_id'])->on('spmb_jalur')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE spmb_pendaftar ADD CONSTRAINT spmb_pendaftar_jenis_kelamin_chk CHECK (jenis_kelamin IN ('L', 'P'))");
        DB::statement('CREATE INDEX spmb_pendaftar_nama_trgm ON spmb_pendaftar USING gin (nama_lengkap gin_trgm_ops)');

        Schema::create('spmb_pendaftar_wali', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftar_id')->constrained('spmb_pendaftar')->cascadeOnDelete();
            $table->string('hubungan', 20);                              // ayah/ibu/wali
            $table->string('nama');
            $table->text('nik')->nullable();                             // terenkripsi
            $table->string('telepon', 30)->nullable();
            $table->string('pekerjaan_ref', 50)->nullable();
            $table->string('penghasilan_ref', 50)->nullable();
            $table->boolean('is_kontak_utama')->default(false);
            $table->timestamps();

            $table->index('pendaftar_id');
        });

        Schema::create('spmb_berkas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pendaftar_id');
            $table->unsignedBigInteger('persyaratan_id');
            $table->unsignedBigInteger('periode_id');                    // harus sama dengan periode pendaftar dan persyaratan
            $table->string('status_verifikasi', 20)->default('menunggu'); // menunggu/valid/tidak_valid
            $table->text('catatan')->nullable();
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['pendaftar_id', 'persyaratan_id']);
            $table->foreign(['pendaftar_id', 'periode_id'])
                ->references(['id', 'periode_id'])->on('spmb_pendaftar')->cascadeOnDelete();
            $table->foreign(['persyaratan_id', 'periode_id'])
                ->references(['id', 'periode_id'])->on('spmb_persyaratan_berkas')->restrictOnDelete();
        });

        Schema::create('spmb_tahap_seleksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_id')->constrained('spmb_periode')->cascadeOnDelete();
            $table->string('nama');                                      // administrasi/tes_akademik/observasi/wawancara/tes_baca_quran
            $table->unsignedSmallInteger('urutan');
            $table->decimal('bobot', 5, 2)->default(0);
            $table->decimal('nilai_lulus', 6, 2)->nullable();
            $table->unsignedBigInteger('ujian_id')->nullable();          // FK ke ujian (CBT) dipasang di fase F4
            $table->timestamps();

            $table->unique(['periode_id', 'urutan']);
            $table->unique(['id', 'periode_id']);
        });

        Schema::create('spmb_nilai_seleksi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pendaftar_id');
            $table->unsignedBigInteger('tahap_id');
            $table->unsignedBigInteger('periode_id');                    // pendaftar dan tahap harus berada di periode yang sama
            $table->date('tanggal');
            $table->decimal('nilai', 6, 2)->nullable();
            $table->foreignId('penguji_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['pendaftar_id', 'tahap_id']);
            $table->foreign(['pendaftar_id', 'periode_id'])
                ->references(['id', 'periode_id'])->on('spmb_pendaftar')->cascadeOnDelete();
            $table->foreign(['tahap_id', 'periode_id'])
                ->references(['id', 'periode_id'])->on('spmb_tahap_seleksi')->restrictOnDelete();
        });

        Schema::create('spmb_hasil', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftar_id')->unique()->constrained('spmb_pendaftar')->cascadeOnDelete();
            $table->decimal('skor_total', 6, 2)->nullable();
            $table->unsignedInteger('peringkat')->nullable();
            $table->string('keputusan', 20);                             // lulus/cadangan/tidak_lulus
            $table->date('tanggal_keputusan');
            $table->foreignId('ditetapkan_oleh')->constrained('users')->restrictOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index('keputusan');
        });

        Schema::create('spmb_daftar_ulang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftar_id')->unique()->constrained('spmb_pendaftar')->cascadeOnDelete();
            $table->date('tanggal')->nullable();
            $table->string('status', 20)->default('menunggu');           // menunggu/selesai/batal
            $table->foreignId('rombel_id')->nullable()->constrained('rombel')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spmb_daftar_ulang');
        Schema::dropIfExists('spmb_hasil');
        Schema::dropIfExists('spmb_nilai_seleksi');
        Schema::dropIfExists('spmb_tahap_seleksi');
        Schema::dropIfExists('spmb_berkas');
        Schema::dropIfExists('spmb_pendaftar_wali');
        Schema::dropIfExists('spmb_pendaftar');
    }
};
