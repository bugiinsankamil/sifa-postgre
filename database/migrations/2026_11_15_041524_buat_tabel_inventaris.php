<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ============ 8.1 Tanah, bangunan, ruang ============
        
        Schema::create('tanah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->foreignId('entitas_id')->nullable()->comment('NULL sampai modul Keuangan ada');
            $table->string('kode_aset', 50)->unique();
            $table->string('nama');
            $table->text('alamat')->nullable();
            $table->foreignId('wilayah_id')->nullable()->constrained('wilayah')->nullOnDelete();
            $table->decimal('luas_m2', 12, 2);
            $table->string('nomor_sertifikat')->nullable();
            $table->string('jenis_hak', 20)->nullable();
            $table->string('atas_nama')->nullable();
            $table->string('asal_perolehan', 30);
            $table->date('tanggal_perolehan')->nullable();
            $table->decimal('nilai_perolehan', 15, 2)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 20)->default('aktif');
            $table->timestamps();
            $table->index(['cabang_id', 'status']);
        });

        Schema::create('bangunan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->foreignId('entitas_id')->nullable()->comment('NULL sampai modul Keuangan ada');
            $table->foreignId('tanah_id')->constrained('tanah')->restrictOnDelete();
            $table->string('kode_aset', 50)->unique();
            $table->string('nama');
            $table->decimal('luas_m2', 10, 2);
            $table->unsignedSmallInteger('jumlah_lantai')->default(1);
            $table->unsignedSmallInteger('tahun_dibangun')->nullable();
            $table->string('nomor_pbg')->nullable();
            $table->string('kondisi', 20)->default('baik');
            $table->decimal('nilai_perolehan', 15, 2)->nullable();
            $table->string('status', 20)->default('aktif');
            $table->timestamps();
            $table->index(['cabang_id', 'status']);
        });

        Schema::create('ruang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->foreignId('sekolah_id')->nullable()->constrained('sekolah')->nullOnDelete();
            $table->foreignId('bangunan_id')->nullable()->constrained('bangunan')->nullOnDelete();
            $table->string('kode', 30);
            $table->string('nama');
            $table->unsignedSmallInteger('lantai')->nullable();
            $table->string('jenis_ref', 50)->nullable();
            $table->decimal('luas_m2', 8, 2)->nullable();
            $table->unsignedSmallInteger('kapasitas')->nullable();
            $table->string('kondisi', 20)->default('baik');
            $table->foreignId('penanggung_jawab_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->string('status', 20)->default('aktif');
            $table->timestamps();
            $table->unique(['cabang_id', 'kode']);
            $table->index(['cabang_id', 'status']);
        });

        // ============ 8.2 Katalog dan aset tetap ============
        
        Schema::create('kategori_aset', function (Blueprint $table) {
            $table->id();
            $table->foreignId('induk_id')->nullable()->constrained('kategori_aset')->nullOnDelete();
            $table->string('kode', 50)->unique();
            $table->string('nama');
            $table->string('jenis', 30);
            $table->unsignedSmallInteger('masa_manfaat_tahun')->nullable();
            $table->string('metode_penyusutan', 30)->nullable();
            $table->unsignedBigInteger('akun_aset_id')->nullable()->comment('FK ke akun ditambah saat modul Keuangan ada');
            $table->unsignedBigInteger('akun_penyusutan_id')->nullable()->comment('FK ke akun ditambah saat modul Keuangan ada');
            $table->unsignedBigInteger('akun_beban_id')->nullable()->comment('FK ke akun ditambah saat modul Keuangan ada');
            $table->timestamps();
            $table->index('jenis');
        });

        Schema::create('barang', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nama');
            $table->foreignId('kategori_id')->constrained('kategori_aset')->restrictOnDelete();
            $table->string('jenis', 30);
            $table->string('satuan_ref', 30);
            $table->string('merek')->nullable();
            $table->text('spesifikasi')->nullable();
            $table->decimal('harga_standar', 15, 2)->nullable();
            $table->unsignedInteger('stok_minimum')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
            $table->index(['jenis', 'is_aktif']);
        });

        Schema::create('aset_unit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->foreignId('entitas_id')->nullable()->comment('NULL sampai modul Keuangan ada');
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();
            $table->string('kode_aset', 50)->unique();
            $table->string('nomor_seri')->nullable();
            $table->date('tanggal_perolehan');
            $table->decimal('harga_perolehan', 15, 2);
            $table->string('sumber_dana_ref', 30);
            $table->unsignedBigInteger('penerimaan_barang_baris_id')->nullable();
            $table->decimal('nilai_residu', 15, 2)->default(0);
            $table->unsignedSmallInteger('masa_manfaat_tahun')->nullable();
            $table->string('kondisi', 20)->default('baik');
            $table->string('status', 20)->default('aktif');
            $table->foreignId('ruang_id')->nullable()->constrained('ruang')->nullOnDelete();
            $table->foreignId('penanggung_jawab_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->string('kode_qr', 100)->unique();
            $table->string('foto_path')->nullable();
            $table->timestamps();
            $table->index(['cabang_id', 'status']);
            $table->index('kode_qr');
        });

        Schema::create('aset_kendaraan', function (Blueprint $table) {
            $table->foreignId('aset_unit_id')->primary()->constrained('aset_unit')->cascadeOnDelete();
            $table->string('nomor_polisi', 20)->unique();
            $table->string('nomor_rangka', 50)->nullable();
            $table->string('nomor_mesin', 50)->nullable();
            $table->date('masa_berlaku_stnk')->nullable();
            $table->date('masa_berlaku_pajak')->nullable();
            $table->timestamps();
        });

        Schema::create('penyusutan_aset', function (Blueprint $table) {
            $table->id();
            $table->string('aset_type', 30);
            $table->unsignedBigInteger('aset_id');
            $table->unsignedBigInteger('periode_id')->comment('FK ke periode_akuntansi ditambah saat modul Keuangan ada');
            $table->decimal('nilai_penyusutan', 15, 2);
            $table->decimal('akumulasi', 15, 2);
            $table->decimal('nilai_buku', 15, 2);
            $table->unsignedBigInteger('jurnal_id')->nullable()->comment('FK ke jurnal ditambah saat modul Keuangan ada');
            $table->timestamps();
            $table->unique(['aset_type', 'aset_id', 'periode_id'], 'penyusutan_unique');
            $table->index(['aset_type', 'aset_id']);
        });

        // ============ 8.3 Barang habis pakai dan stok ============
        
        Schema::create('gudang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->string('kode', 30);
            $table->string('nama');
            $table->foreignId('ruang_id')->nullable()->constrained('ruang')->nullOnDelete();
            $table->foreignId('penanggung_jawab_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
            $table->unique(['cabang_id', 'kode']);
            $table->index(['cabang_id', 'is_aktif']);
        });

        Schema::create('stok_barang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gudang_id')->constrained('gudang')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();
            $table->integer('jumlah')->default(0);
            $table->timestamps();
            $table->unique(['gudang_id', 'barang_id']);
            $table->index(['barang_id', 'jumlah']);
        });

        Schema::create('mutasi_stok', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gudang_id')->constrained('gudang')->restrictOnDelete();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();
            $table->timestamp('tanggal');
            $table->string('tipe', 30);
            $table->integer('jumlah');
            $table->decimal('harga_satuan', 15, 2)->nullable();
            $table->integer('saldo_setelah');
            $table->string('sumber_type', 50);
            $table->unsignedBigInteger('sumber_id');
            $table->foreignId('dibuat_oleh')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['gudang_id', 'barang_id', 'tanggal']);
            $table->index(['sumber_type', 'sumber_id']);
        });

        Schema::create('penerimaan_barang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->string('nomor', 50)->unique();
            $table->unsignedBigInteger('pesanan_pembelian_id')->nullable()->comment('FK ke pesanan_pembelian ditambah saat modul Keuangan ada');
            $table->unsignedBigInteger('pemasok_id')->nullable()->comment('FK ke pemasok ditambah saat modul Keuangan ada');
            $table->foreignId('gudang_id')->constrained('gudang')->restrictOnDelete();
            $table->date('tanggal');
            $table->string('nomor_surat_jalan')->nullable();
            $table->foreignId('diterima_oleh')->constrained('pegawai')->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->index(['cabang_id', 'status']);
        });

        Schema::create('penerimaan_barang_baris', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penerimaan_id')->constrained('penerimaan_barang')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();
            $table->unsignedInteger('jumlah');
            $table->decimal('harga_satuan', 15, 2);
            $table->string('kondisi', 20)->default('baik');
            $table->timestamps();
            $table->index('penerimaan_id');
        });

        Schema::table('aset_unit', function (Blueprint $table) {
            $table->foreign('penerimaan_barang_baris_id')->references('id')->on('penerimaan_barang_baris')->nullOnDelete();
        });

        Schema::create('permintaan_barang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->string('nomor', 50)->unique();
            $table->foreignId('unit_kerja_id')->constrained('unit_kerja')->restrictOnDelete();
            $table->foreignId('pemohon_id')->constrained('pegawai')->restrictOnDelete();
            $table->date('tanggal');
            $table->text('keperluan')->nullable();
            $table->string('status', 20)->default('diajukan');
            $table->timestamps();
            $table->index(['cabang_id', 'status']);
        });

        Schema::create('permintaan_barang_baris', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permintaan_id')->constrained('permintaan_barang')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();
            $table->unsignedInteger('jumlah_diminta');
            $table->unsignedInteger('jumlah_disetujui')->nullable();
            $table->timestamps();
            $table->index('permintaan_id');
        });

        Schema::create('pengeluaran_barang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->string('nomor', 50)->unique();
            $table->foreignId('gudang_id')->constrained('gudang')->restrictOnDelete();
            $table->foreignId('permintaan_id')->nullable()->constrained('permintaan_barang')->nullOnDelete();
            $table->foreignId('penerima_id')->constrained('pegawai')->restrictOnDelete();
            $table->date('tanggal');
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->index(['cabang_id', 'status']);
        });

        Schema::create('pengeluaran_barang_baris', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengeluaran_id')->constrained('pengeluaran_barang')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();
            $table->unsignedInteger('jumlah');
            $table->timestamps();
            $table->index('pengeluaran_id');
        });

        Schema::create('stok_opname', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gudang_id')->constrained('gudang')->restrictOnDelete();
            $table->date('tanggal');
            $table->string('status', 20)->default('draft');
            $table->foreignId('dibuat_oleh')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['gudang_id', 'status']);
        });

        Schema::create('stok_opname_baris', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opname_id')->constrained('stok_opname')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barang')->restrictOnDelete();
            $table->integer('stok_sistem');
            $table->integer('stok_fisik');
            $table->integer('selisih');
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->unique(['opname_id', 'barang_id']);
        });

        // ============ 8.4 Peminjaman, mutasi, pemeliharaan, penghapusan ============
        
        Schema::create('peminjaman', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->string('nomor', 50)->unique();
            $table->foreignId('peminjam_id')->constrained('orang')->restrictOnDelete();
            $table->foreignId('unit_kerja_id')->nullable()->constrained('unit_kerja')->nullOnDelete();
            $table->text('keperluan');
            $table->date('tanggal_pinjam');
            $table->date('tanggal_rencana_kembali');
            $table->date('tanggal_kembali')->nullable();
            $table->string('status', 20)->default('diajukan');
            $table->timestamps();
            $table->index(['cabang_id', 'status']);
            $table->index('peminjam_id');
        });

        Schema::create('peminjaman_baris', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peminjaman_id')->constrained('peminjaman')->cascadeOnDelete();
            $table->foreignId('aset_unit_id')->constrained('aset_unit')->restrictOnDelete();
            $table->string('kondisi_keluar', 20);
            $table->string('kondisi_kembali', 20)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->index('peminjaman_id');
            $table->index('aset_unit_id');
        });

        Schema::create('reservasi_ruang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ruang_id')->constrained('ruang')->restrictOnDelete();
            $table->foreignId('peminjam_id')->constrained('orang')->restrictOnDelete();
            $table->text('keperluan');
            $table->timestamp('mulai_pada');
            $table->timestamp('selesai_pada');
            $table->string('status', 20)->default('diajukan');
            $table->timestamps();
            $table->index(['ruang_id', 'status']);
            $table->index(['mulai_pada', 'selesai_pada']);
        });

        Schema::create('mutasi_aset', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 50)->unique();
            $table->foreignId('cabang_asal_id')->constrained('cabang')->restrictOnDelete();
            $table->foreignId('cabang_tujuan_id')->constrained('cabang')->restrictOnDelete();
            $table->foreignId('ruang_tujuan_id')->nullable()->constrained('ruang')->nullOnDelete();
            $table->date('tanggal');
            $table->text('alasan');
            $table->string('status', 20)->default('diajukan');
            $table->timestamps();
            $table->index(['cabang_asal_id', 'status']);
        });

        Schema::create('mutasi_aset_baris', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mutasi_id')->constrained('mutasi_aset')->cascadeOnDelete();
            $table->foreignId('aset_unit_id')->constrained('aset_unit')->restrictOnDelete();
            $table->foreignId('ruang_asal_id')->nullable()->constrained('ruang')->nullOnDelete();
            $table->string('kondisi', 20);
            $table->timestamps();
            $table->index('mutasi_id');
            $table->index('aset_unit_id');
        });

        Schema::create('laporan_kerusakan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->foreignId('pelapor_id')->constrained('orang')->restrictOnDelete();
            $table->foreignId('aset_unit_id')->nullable()->constrained('aset_unit')->nullOnDelete();
            $table->foreignId('ruang_id')->nullable()->constrained('ruang')->nullOnDelete();
            $table->text('uraian');
            $table->string('prioritas', 20)->default('normal');
            $table->string('status', 20)->default('baru');
            $table->timestamps();
            $table->index(['cabang_id', 'status']);
        });

        Schema::create('pemeliharaan_aset', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->foreignId('aset_unit_id')->nullable()->constrained('aset_unit')->nullOnDelete();
            $table->foreignId('ruang_id')->nullable()->constrained('ruang')->nullOnDelete();
            $table->foreignId('laporan_kerusakan_id')->nullable()->constrained('laporan_kerusakan')->nullOnDelete();
            $table->string('jenis', 20);
            $table->date('tanggal');
            $table->text('uraian');
            $table->decimal('biaya', 15, 2)->nullable();
            $table->string('pelaksana')->nullable();
            $table->date('tanggal_berikutnya')->nullable();
            $table->string('status', 20)->default('dijadwalkan');
            $table->timestamps();
            $table->index(['cabang_id', 'status']);
            $table->index('tanggal_berikutnya');
        });

        Schema::create('penghapusan_aset', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->foreignId('aset_unit_id')->constrained('aset_unit')->restrictOnDelete();
            $table->date('tanggal');
            $table->string('alasan', 30);
            $table->decimal('nilai_buku', 15, 2);
            $table->decimal('nilai_jual', 15, 2)->nullable();
            $table->string('no_berita_acara')->nullable();
            $table->unsignedBigInteger('penjualan_id')->nullable()->comment('FK ke penjualan ditambah saat modul Keuangan ada');
            $table->string('status', 20)->default('diajukan');
            $table->timestamps();
            $table->index(['cabang_id', 'status']);
            $table->index('aset_unit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penghapusan_aset');
        Schema::dropIfExists('pemeliharaan_aset');
        Schema::dropIfExists('laporan_kerusakan');
        Schema::dropIfExists('mutasi_aset_baris');
        Schema::dropIfExists('mutasi_aset');
        Schema::dropIfExists('reservasi_ruang');
        Schema::dropIfExists('peminjaman_baris');
        Schema::dropIfExists('peminjaman');
        Schema::dropIfExists('stok_opname_baris');
        Schema::dropIfExists('stok_opname');
        Schema::dropIfExists('pengeluaran_barang_baris');
        Schema::dropIfExists('pengeluaran_barang');
        Schema::dropIfExists('permintaan_barang_baris');
        Schema::dropIfExists('permintaan_barang');

        Schema::table('aset_unit', function (Blueprint $table) {
            $table->dropForeign(['penerimaan_barang_baris_id']);
        });

        Schema::dropIfExists('penerimaan_barang_baris');
        Schema::dropIfExists('penerimaan_barang');
        Schema::dropIfExists('mutasi_stok');
        Schema::dropIfExists('stok_barang');
        Schema::dropIfExists('gudang');
        Schema::dropIfExists('penyusutan_aset');
        Schema::dropIfExists('aset_kendaraan');
        Schema::dropIfExists('aset_unit');
        Schema::dropIfExists('barang');
        Schema::dropIfExists('kategori_aset');
        Schema::dropIfExists('ruang');
        Schema::dropIfExists('bangunan');
        Schema::dropIfExists('tanah');
    }
};
