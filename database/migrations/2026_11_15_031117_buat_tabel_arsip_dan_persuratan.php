<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---- Klasifikasi arsip ------------------------------------------------
        Schema::create('klasifikasi_arsip', function (Blueprint $table) {
            $table->id();
            $table->foreignId('induk_id')->nullable()->constrained('klasifikasi_arsip')->nullOnDelete();
            $table->string('kode', 30)->unique();
            $table->string('nama');
            $table->unsignedSmallInteger('retensi_aktif_tahun')->nullable();
            $table->unsignedSmallInteger('retensi_inaktif_tahun')->nullable();
            $table->string('tindakan_akhir', 20)->nullable();  // musnah/permanen
            $table->timestamps();
        });

        // ---- Templat surat ----------------------------------------------------
        Schema::create('templat_surat', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nama');
            $table->string('jenis', 50);
            $table->text('isi');  // HTML dengan placeholder
            $table->jsonb('variabel')->nullable();
            $table->foreignId('cabang_id')->nullable()->constrained('cabang')->restrictOnDelete();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        // ---- Surat masuk ------------------------------------------------------
        Schema::create('surat_masuk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->string('nomor_agenda', 50)->unique();
            $table->string('nomor_surat')->nullable();
            $table->date('tanggal_surat')->nullable();
            $table->date('tanggal_terima');
            $table->string('pengirim');
            $table->string('perihal');
            $table->foreignId('klasifikasi_id')->nullable()->constrained('klasifikasi_arsip')->nullOnDelete();
            $table->string('sifat', 20)->default('biasa');  // biasa/penting/rahasia
            $table->text('ringkasan')->nullable();
            $table->foreignId('unit_kerja_id')->nullable()->constrained('unit_kerja')->nullOnDelete();
            $table->string('status', 20)->default('diterima');  // diterima/didisposisikan/diproses/selesai/diarsipkan
            $table->timestamps();

            $table->index(['cabang_id', 'status']);
            $table->index('tanggal_terima');
        });

        // ---- Disposisi --------------------------------------------------------
        Schema::create('disposisi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surat_masuk_id')->constrained('surat_masuk')->cascadeOnDelete();
            $table->foreignId('induk_id')->nullable()->constrained('disposisi')->nullOnDelete();
            $table->foreignId('dari_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('kepada_user_id')->constrained('users')->restrictOnDelete();
            $table->text('instruksi');
            $table->date('batas_waktu')->nullable();
            $table->string('status', 20)->default('baru');  // baru/dibaca/diproses/selesai
            $table->text('tanggapan')->nullable();
            $table->timestamp('dibaca_pada')->nullable();
            $table->timestamp('selesai_pada')->nullable();
            $table->timestamps();

            $table->index(['surat_masuk_id', 'status']);
            $table->index(['kepada_user_id', 'status']);
        });

        // ---- Surat keluar -----------------------------------------------------
        Schema::create('surat_keluar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->string('nomor_surat', 100)->unique();
            $table->date('tanggal_surat');
            $table->string('tujuan');
            $table->string('perihal');
            $table->string('jenis', 20);  // biasa/edaran/sk/mou/undangan/keterangan
            $table->foreignId('klasifikasi_id')->nullable()->constrained('klasifikasi_arsip')->nullOnDelete();
            $table->foreignId('templat_id')->nullable()->constrained('templat_surat')->nullOnDelete();
            $table->text('isi');
            $table->foreignId('pembuat_id')->constrained('pegawai')->restrictOnDelete();
            $table->foreignId('unit_kerja_id')->nullable()->constrained('unit_kerja')->nullOnDelete();
            $table->foreignId('penandatangan_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->string('status', 20)->default('draft');  // draft/review/disetujui/terkirim/diarsipkan
            $table->date('tanggal_kirim')->nullable();
            $table->timestamps();

            $table->index(['cabang_id', 'status']);
            $table->index('tanggal_surat');
        });

        // ---- Surat keluar tujuan ----------------------------------------------
        Schema::create('surat_keluar_tujuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surat_keluar_id')->constrained('surat_keluar')->cascadeOnDelete();
            $table->foreignId('pegawai_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->foreignId('orang_id')->nullable()->constrained('orang')->nullOnDelete();
            $table->string('nama_tujuan')->nullable();
            $table->timestamps();
        });

        // ---- Lokasi arsip fisik -----------------------------------------------
        Schema::create('lokasi_arsip_fisik', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->foreignId('induk_id')->nullable()->constrained('lokasi_arsip_fisik')->nullOnDelete();
            $table->string('kode', 30);
            $table->string('nama');  // ruang/lemari/rak/boks
            $table->timestamps();

            $table->unique(['cabang_id', 'kode']);
        });

        // ---- Dokumen ----------------------------------------------------------
        Schema::create('dokumen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->nullable()->constrained('cabang')->restrictOnDelete();  // null = yayasan
            $table->foreignId('klasifikasi_id')->nullable()->constrained('klasifikasi_arsip')->nullOnDelete();
            $table->string('nomor')->nullable();
            $table->string('judul');
            $table->text('deskripsi')->nullable();
            $table->string('jenis_ref', 50)->nullable();  // sk/mou/akta/izin/laporan/sertifikat/kontrak/lainnya
            $table->date('tanggal_dokumen')->nullable();
            $table->foreignId('unit_kerja_id')->nullable()->constrained('unit_kerja')->nullOnDelete();
            $table->string('tingkat_akses', 20)->default('internal');  // publik/internal/terbatas/rahasia
            $table->string('status', 20)->default('aktif');  // aktif/inaktif/dimusnahkan
            $table->date('tanggal_retensi_habis')->nullable();
            $table->foreignId('lokasi_fisik_id')->nullable()->constrained('lokasi_arsip_fisik')->nullOnDelete();
            $table->unsignedSmallInteger('versi_terkini')->default(1);
            $table->jsonb('tag')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['cabang_id', 'status']);
            $table->index('tingkat_akses');
        });

        // ---- Dokumen versi ----------------------------------------------------
        Schema::create('dokumen_versi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dokumen_id')->constrained('dokumen')->cascadeOnDelete();
            $table->unsignedSmallInteger('versi');
            $table->text('catatan')->nullable();
            $table->foreignId('dibuat_oleh')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['dokumen_id', 'versi']);
        });

        // ---- Dokumen akses ----------------------------------------------------
        Schema::create('dokumen_akses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dokumen_id')->constrained('dokumen')->cascadeOnDelete();
            $table->string('subjek_type', 30);  // user/role/unit_kerja
            $table->unsignedBigInteger('subjek_id');
            $table->string('hak', 20);  // lihat/ubah
            $table->timestamps();

            $table->unique(['dokumen_id', 'subjek_type', 'subjek_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumen_akses');
        Schema::dropIfExists('dokumen_versi');
        Schema::dropIfExists('dokumen');
        Schema::dropIfExists('lokasi_arsip_fisik');
        Schema::dropIfExists('surat_keluar_tujuan');
        Schema::dropIfExists('surat_keluar');
        Schema::dropIfExists('disposisi');
        Schema::dropIfExists('surat_masuk');
        Schema::dropIfExists('templat_surat');
        Schema::dropIfExists('klasifikasi_arsip');
    }
};
