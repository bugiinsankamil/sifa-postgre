<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_tagihan', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('tipe', 20);
            $table->foreignId('akun_pendapatan_id')->nullable()->constrained('akun')->nullOnDelete();
            $table->foreignId('jenjang_id')->nullable()->constrained('jenjang')->nullOnDelete();
            $table->boolean('boleh_cicil')->default(false);
            $table->boolean('is_aktif')->default(true);
            $table->softDeletes();
            $table->timestamps();
            $table->index('is_aktif');
        });
        DB::statement("ALTER TABLE jenis_tagihan ADD CONSTRAINT jt_tipe_chk CHECK (tipe IN ('bulanan','tahunan','sekali','uang_pangkal','seragam','lainnya'))");

        Schema::create('tarif_tagihan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_tagihan_id')->constrained('jenis_tagihan')->restrictOnDelete();
            $table->foreignId('sekolah_id')->constrained('sekolah')->restrictOnDelete();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->restrictOnDelete();
            $table->foreignId('tingkat_id')->nullable()->constrained('tingkat')->nullOnDelete();
            $table->decimal('nominal', 15, 2);
            $table->boolean('is_aktif')->default(true);
            $table->json('jadwal')->nullable();
            $table->unique(['jenis_tagihan_id', 'sekolah_id', 'tahun_ajaran_id', 'tingkat_id']);
            $table->softDeletes();
            $table->timestamps();
            $table->index(['sekolah_id', 'tahun_ajaran_id']);
        });

        Schema::create('diskon_beasiswa', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('tipe', 10);
            $table->decimal('nilai', 15, 2);
            $table->string('sumber_dana')->nullable();
            $table->text('kriteria')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->softDeletes();
            $table->timestamps();
            $table->index('is_aktif');
        });
        DB::statement("ALTER TABLE diskon_beasiswa ADD CONSTRAINT db_tipe_chk CHECK (tipe IN ('persen','nominal'))");

        Schema::create('siswa_diskon', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->restrictOnDelete();
            $table->foreignId('diskon_id')->constrained('diskon_beasiswa')->restrictOnDelete();
            $table->foreignId('jenis_tagihan_id')->nullable()->constrained('jenis_tagihan')->nullOnDelete();
            $table->date('tanggal_mulai');
            $table->date('tanggal_akhir')->nullable();
            $table->string('no_sk')->nullable();
            $table->string('status', 20)->default('aktif');
            $table->unique(['siswa_id', 'diskon_id', 'jenis_tagihan_id']);
            $table->softDeletes();
            $table->timestamps();
            $table->index(['siswa_id', 'status']);
        });
        DB::statement("ALTER TABLE siswa_diskon ADD CONSTRAINT sd_status_chk CHECK (status IN ('aktif','nonaktif'))");

        Schema::create('tagihan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->foreignId('entitas_id')->constrained('entitas_akuntansi')->restrictOnDelete();
            $table->foreignId('siswa_id')->nullable()->constrained('siswa')->nullOnDelete();
            $table->foreignId('pendaftar_id')->nullable()->constrained('spmb_pendaftar')->nullOnDelete();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->restrictOnDelete();
            $table->foreignId('jenis_tagihan_id')->constrained('jenis_tagihan')->restrictOnDelete();
            $table->string('nomor')->unique();
            $table->string('periode_label')->nullable();
            $table->date('tanggal_terbit');
            $table->date('jatuh_tempo');
            $table->decimal('nominal', 15, 2);
            $table->decimal('diskon', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->decimal('terbayar', 15, 2)->default(0);
            $table->string('status', 20)->default('belum');
            $table->softDeletes();
            $table->timestamps();
            $table->index(['cabang_id', 'tanggal_terbit']);
            $table->index(['entitas_id', 'tahun_ajaran_id']);
            $table->index('siswa_id');
            $table->index('pendaftar_id');
            $table->index('status');
        });
        DB::statement("ALTER TABLE tagihan ADD CONSTRAINT tg_xor_siswa_pendaftar CHECK ((siswa_id IS NOT NULL) <> (pendaftar_id IS NOT NULL))");
        DB::statement("ALTER TABLE tagihan ADD CONSTRAINT tg_status_chk CHECK (status IN ('belum','sebagian','lunas','batal'))");

        Schema::create('pembayaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->foreignId('entitas_id')->constrained('entitas_akuntansi')->restrictOnDelete();
            $table->foreignId('siswa_id')->nullable()->constrained('siswa')->nullOnDelete();
            $table->foreignId('pendaftar_id')->nullable()->constrained('spmb_pendaftar')->nullOnDelete();
            $table->string('nomor')->unique();
            $table->date('tanggal');
            $table->string('metode', 20);
            $table->decimal('nominal', 15, 2);
            $table->foreignId('rekening_id')->constrained('rekening_kas')->restrictOnDelete();
            $table->string('referensi_eksternal')->nullable()->unique();
            $table->string('status', 20)->default('menunggu');
            $table->foreignId('diterima_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('transaksi_kas_id')->nullable()->constrained('transaksi_kas')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['cabang_id', 'tanggal']);
            $table->index(['entitas_id', 'tanggal']);
            $table->index('siswa_id');
            $table->index('pendaftar_id');
            $table->index('status');
        });
        DB::statement("ALTER TABLE pembayaran ADD CONSTRAINT pb_xor_siswa_pendaftar CHECK ((siswa_id IS NOT NULL) <> (pendaftar_id IS NOT NULL))");
        DB::statement("ALTER TABLE pembayaran ADD CONSTRAINT pb_metode_chk CHECK (metode IN ('tunai','transfer','virtual_account','qris','gateway'))");
        DB::statement("ALTER TABLE pembayaran ADD CONSTRAINT pb_status_chk CHECK (status IN ('menunggu','berhasil','gagal','batal'))");

        Schema::create('pembayaran_alokasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pembayaran_id')->constrained('pembayaran')->restrictOnDelete();
            $table->foreignId('tagihan_id')->constrained('tagihan')->restrictOnDelete();
            $table->decimal('nominal', 15, 2);
            $table->unique(['pembayaran_id', 'tagihan_id']);
            $table->timestamps();
        });

        Schema::create('virtual_account', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->restrictOnDelete();
            $table->string('bank_ref', 50);
            $table->string('nomor')->unique();
            $table->string('status', 20)->default('aktif');
            $table->timestamp('kedaluwarsa_pada')->nullable();
            $table->timestamps();
            $table->index('siswa_id');
            $table->index('status');
        });
        DB::statement("ALTER TABLE virtual_account ADD CONSTRAINT va_status_chk CHECK (status IN ('aktif','nonaktif','kedaluwarsa'))");

        Schema::create('pembayaran_gateway_log', function (Blueprint $table) {
            $table->id();
            $table->string('penyedia', 50);
            $table->string('referensi')->unique();
            $table->jsonb('payload');
            $table->string('status_proses', 20)->default('baru');
            $table->timestamp('diterima_pada')->nullable();
            $table->timestamps();
            $table->index('referensi');
            $table->index('status_proses');
        });
        DB::statement("ALTER TABLE pembayaran_gateway_log ADD CONSTRAINT pgl_status_chk CHECK (status_proses IN ('baru','diproses','berhasil','gagal'))");

        Schema::create('pengembalian_dana', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pembayaran_id')->constrained('pembayaran')->restrictOnDelete();
            $table->decimal('nominal', 15, 2);
            $table->text('alasan');
            $table->date('tanggal');
            $table->string('status', 20)->default('diaju');
            $table->timestamps();
            $table->index('pembayaran_id');
            $table->index('status');
        });
        DB::statement("ALTER TABLE pengembalian_dana ADD CONSTRAINT pd_status_chk CHECK (status IN ('diaju','disetujui','ditolak','selesai'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran_alokasi');
        Schema::dropIfExists('pembayaran_gateway_log');
        Schema::dropIfExists('virtual_account');
        Schema::dropIfExists('pengembalian_dana');
        Schema::dropIfExists('pembayaran');
        Schema::dropIfExists('tagihan');
        Schema::dropIfExists('siswa_diskon');
        Schema::dropIfExists('diskon_beasiswa');
        Schema::dropIfExists('tarif_tagihan');
        Schema::dropIfExists('jenis_tagihan');
    }
};
