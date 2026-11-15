<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entitas_akuntansi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('induk_id')->nullable()->constrained('entitas_akuntansi')->nullOnDelete();
            $table->string('tipe', 20);
            $table->foreignId('cabang_id')->nullable()->constrained('cabang')->nullOnDelete();
            $table->unsignedBigInteger('sekolah_id')->nullable();
            $table->string('kode', 50)->unique();
            $table->string('nama');
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
            $table->foreign(['sekolah_id', 'cabang_id'])->references(['id', 'cabang_id'])->on('sekolah')->nullOnDelete();
        });
        DB::statement("ALTER TABLE entitas_akuntansi ADD CONSTRAINT ea_tipe_chk CHECK (tipe IN ('yayasan','cabang','sekolah'))");
        DB::statement("ALTER TABLE entitas_akuntansi ADD CONSTRAINT ea_wajib_chk CHECK (
            (tipe = 'yayasan' AND cabang_id IS NULL AND sekolah_id IS NULL)
            OR (tipe = 'cabang' AND cabang_id IS NOT NULL AND sekolah_id IS NULL)
            OR (tipe = 'sekolah' AND cabang_id IS NOT NULL AND sekolah_id IS NOT NULL))");

        Schema::create('akun', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entitas_id')->nullable()->constrained('entitas_akuntansi')->nullOnDelete();
            $table->string('kode', 50);
            $table->string('nama');
            $table->string('tipe', 30);
            $table->foreignId('induk_id')->nullable()->constrained('akun')->nullOnDelete();
            $table->string('saldo_normal', 10);
            $table->boolean('is_header')->default(false);
            $table->boolean('is_antar_entitas')->default(false);
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });
        DB::statement('ALTER TABLE akun ADD CONSTRAINT akun_kode_uk UNIQUE NULLS NOT DISTINCT (entitas_id, kode)');
        DB::statement("ALTER TABLE akun ADD CONSTRAINT akun_tipe_chk CHECK (tipe IN ('aset','kewajiban','ekuitas','pendapatan','beban'))");
        DB::statement("ALTER TABLE akun ADD CONSTRAINT akun_saldo_normal_chk CHECK (saldo_normal IN ('debit','kredit'))");

        Schema::create('pusat_biaya', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entitas_id')->constrained('entitas_akuntansi')->cascadeOnDelete();
            $table->foreignId('unit_kerja_id')->nullable()->constrained('unit_kerja')->nullOnDelete();
            $table->string('kode', 30);
            $table->string('nama');
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
            $table->unique(['entitas_id', 'kode']);
        });

        Schema::create('periode_akuntansi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entitas_id')->constrained('entitas_akuntansi')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('bulan');
            $table->string('status', 20)->default('terbuka');
            $table->timestamps();
            $table->unique(['entitas_id', 'tahun', 'bulan']);
        });
        DB::statement('ALTER TABLE periode_akuntansi ADD CONSTRAINT pa_bulan_chk CHECK (bulan >= 1 AND bulan <= 12)');
        DB::statement("ALTER TABLE periode_akuntansi ADD CONSTRAINT pa_status_chk CHECK (status IN ('terbuka','ditutup'))");

        Schema::create('jurnal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entitas_id')->constrained('entitas_akuntansi')->restrictOnDelete();
            $table->foreignId('cabang_id')->nullable()->constrained('cabang')->restrictOnDelete();
            $table->foreignId('periode_id')->constrained('periode_akuntansi')->restrictOnDelete();
            $table->string('nomor', 50)->unique();
            $table->date('tanggal');
            $table->text('keterangan');
            $table->string('sumber_type', 50)->nullable();
            $table->unsignedBigInteger('sumber_id')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('dibuat_oleh')->constrained('users')->restrictOnDelete();
            $table->foreignId('diposting_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diposting_pada')->nullable();
            $table->timestamps();
            $table->index(['entitas_id', 'status']);
            $table->index(['cabang_id', 'status']);
            $table->index('tanggal');
        });
        DB::statement("ALTER TABLE jurnal ADD CONSTRAINT jurnal_status_chk CHECK (status IN ('draft','posted','void'))");

        Schema::create('jurnal_baris', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jurnal_id')->constrained('jurnal')->cascadeOnDelete();
            $table->foreignId('akun_id')->constrained('akun')->restrictOnDelete();
            $table->foreignId('pusat_biaya_id')->nullable()->constrained('pusat_biaya')->nullOnDelete();
            $table->decimal('debit', 19, 2)->default(0);
            $table->decimal('kredit', 19, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->index('jurnal_id');
            $table->index('akun_id');
        });
        DB::statement('ALTER TABLE jurnal_baris ADD CONSTRAINT jb_satu_sisi_chk CHECK ((debit > 0 AND kredit = 0) OR (kredit > 0 AND debit = 0) OR (debit = 0 AND kredit = 0))');

        Schema::create('saldo_akun_periode', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entitas_id')->constrained('entitas_akuntansi')->cascadeOnDelete();
            $table->foreignId('akun_id')->constrained('akun')->restrictOnDelete();
            $table->foreignId('periode_id')->constrained('periode_akuntansi')->restrictOnDelete();
            $table->decimal('debit', 19, 2)->default(0);
            $table->decimal('kredit', 19, 2)->default(0);
            $table->decimal('saldo_akhir', 19, 2)->default(0);
            $table->timestamps();
            $table->unique(['entitas_id', 'akun_id', 'periode_id'], 'saldo_periode_uk');
        });

        Schema::create('pemetaan_akun', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entitas_id')->nullable()->constrained('entitas_akuntansi')->cascadeOnDelete();
            $table->string('kunci', 100);
            $table->foreignId('akun_id')->constrained('akun')->restrictOnDelete();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE pemetaan_akun ADD CONSTRAINT pm_akun_uk UNIQUE NULLS NOT DISTINCT (entitas_id, kunci)');
    }

    public function down(): void
    {
        Schema::dropIfExists('pemetaan_akun');
        Schema::dropIfExists('saldo_akun_periode');
        Schema::dropIfExists('jurnal_baris');
        Schema::dropIfExists('jurnal');
        Schema::dropIfExists('periode_akuntansi');
        Schema::dropIfExists('pusat_biaya');
        Schema::dropIfExists('akun');
        Schema::dropIfExists('entitas_akuntansi');
    }
};
