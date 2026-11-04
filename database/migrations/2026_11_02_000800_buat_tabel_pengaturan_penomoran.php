<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 20);                    // global/cabang/sekolah
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('kunci', 100);
            $table->jsonb('nilai');
            $table->timestamps();
        });
        // COALESCE agar baris global (scope_id NULL) juga unik, tanpa bergantung pada versi PostgreSQL
        DB::statement('CREATE UNIQUE INDEX pengaturan_scope_kunci_unik ON pengaturan (scope, COALESCE(scope_id, 0), kunci)');

        Schema::create('penomoran', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50);
            $table->foreignId('cabang_id')->nullable()->constrained('cabang')->restrictOnDelete();
            $table->unsignedSmallInteger('tahun')->nullable();
            $table->string('pola', 150);                    // {SEQ:4}/FTN/{BULAN_ROMAWI}/{TAHUN}
            $table->unsignedBigInteger('nomor_terakhir')->default(0);
            $table->timestamps();
        });
        DB::statement('CREATE UNIQUE INDEX penomoran_unik ON penomoran (kode, COALESCE(cabang_id, 0), COALESCE(tahun, 0))');
    }

    public function down(): void
    {
        Schema::dropIfExists('penomoran');
        Schema::dropIfExists('pengaturan');
    }
};
