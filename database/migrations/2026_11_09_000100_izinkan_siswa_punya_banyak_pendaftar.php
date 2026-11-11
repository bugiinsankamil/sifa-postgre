<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Seorang siswa bisa punya beberapa pendaftaran dari waktu ke waktu (mis. masuk TK, lalu daftar SD).
// Unique pada spmb_pendaftar.siswa_id terlalu ketat dan membuat konversi kedua gagal. Diganti dengan
// aturan yang benar: satu siswa hanya boleh muncul sekali per periode.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spmb_pendaftar', function (Blueprint $table) {
            $table->dropUnique(['siswa_id']);
            $table->index('siswa_id');
        });

        DB::statement('CREATE UNIQUE INDEX spmb_pendaftar_siswa_periode_unik ON spmb_pendaftar (periode_id, siswa_id) WHERE siswa_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS spmb_pendaftar_siswa_periode_unik');

        Schema::table('spmb_pendaftar', function (Blueprint $table) {
            $table->dropIndex(['siswa_id']);
            $table->unique('siswa_id');
        });
    }
};
