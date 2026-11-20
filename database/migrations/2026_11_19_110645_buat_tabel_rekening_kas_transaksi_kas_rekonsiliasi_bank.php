<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rekening_kas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entitas_id')->constrained('entitas_akuntansi')->restrictOnDelete();
            $table->foreignId('cabang_id')->nullable()->constrained('cabang')->restrictOnDelete();
            $table->string('nama');
            $table->string('tipe', 10);
            $table->string('bank_ref', 50)->nullable();
            $table->string('nomor_rekening', 50)->nullable();
            $table->foreignId('akun_id')->constrained('akun')->restrictOnDelete();
            $table->decimal('saldo_awal', 15, 2)->default(0);
            $table->boolean('is_aktif')->default(true);
            $table->softDeletes();
            $table->timestamps();
            $table->index(['entitas_id', 'is_aktif']);
            $table->index('cabang_id');
        });
        DB::statement("ALTER TABLE rekening_kas ADD CONSTRAINT rk_tipe_chk CHECK (tipe IN ('kas','bank'))");

        Schema::create('rekonsiliasi_bank', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rekening_id')->constrained('rekening_kas')->restrictOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('bulan');
            $table->decimal('saldo_bank', 15, 2)->default(0);
            $table->decimal('saldo_buku', 15, 2)->default(0);
            $table->decimal('selisih', 15, 2)->default(0);
            $table->string('status', 20)->default('belum');
            $table->timestamps();
            $table->unique(['rekening_id', 'tahun', 'bulan']);
        });
        DB::statement("ALTER TABLE rekonsiliasi_bank ADD CONSTRAINT rb_bulan_chk CHECK (bulan >= 1 AND bulan <= 12)");
        DB::statement("ALTER TABLE rekonsiliasi_bank ADD CONSTRAINT rb_status_chk CHECK (status IN ('belum','selesai'))");

        Schema::create('transaksi_kas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entitas_id')->constrained('entitas_akuntansi')->restrictOnDelete();
            $table->foreignId('cabang_id')->nullable()->constrained('cabang')->restrictOnDelete();
            $table->foreignId('rekening_id')->constrained('rekening_kas')->restrictOnDelete();
            $table->date('tanggal');
            $table->string('tipe', 10);
            $table->decimal('nominal', 15, 2);
            $table->foreignId('akun_lawan_id')->nullable()->constrained('akun')->nullOnDelete();
            $table->foreignId('rekening_tujuan_id')->nullable()->constrained('rekening_kas')->nullOnDelete();
            $table->text('keterangan')->nullable();
            $table->string('sumber_type', 100)->nullable();
            $table->unsignedBigInteger('sumber_id')->nullable();
            $table->foreignId('jurnal_id')->nullable()->constrained('jurnal')->nullOnDelete();
            $table->foreignId('rekonsiliasi_id')->nullable()->constrained('rekonsiliasi_bank')->nullOnDelete();
            $table->string('status', 20)->default('posted');
            $table->timestamps();
            $table->index(['entitas_id', 'tanggal']);
            $table->index(['cabang_id', 'tanggal']);
            $table->index('rekening_id');
            $table->index(['sumber_type', 'sumber_id']);
        });
        DB::statement("ALTER TABLE transaksi_kas ADD CONSTRAINT tk_tipe_chk CHECK (tipe IN ('masuk','keluar','transfer'))");
        DB::statement("ALTER TABLE transaksi_kas ADD CONSTRAINT tk_status_chk CHECK (status IN ('draft','posted','batal'))");
        DB::statement("ALTER TABLE transaksi_kas ADD CONSTRAINT tk_transfer_chk CHECK (
            (tipe <> 'transfer' AND rekening_tujuan_id IS NULL)
            OR (tipe = 'transfer' AND rekening_tujuan_id IS NOT NULL AND rekening_tujuan_id <> rekening_id)
        )");
    }

    public function down(): void
    {
        Schema::dropIfExists('rekonsiliasi_bank');
        Schema::dropIfExists('transaksi_kas');
        Schema::dropIfExists('rekening_kas');
    }
};
