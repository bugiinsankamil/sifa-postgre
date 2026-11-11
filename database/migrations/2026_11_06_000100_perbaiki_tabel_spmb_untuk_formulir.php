<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jenis kelamin wali dibutuhkan saat konversi (orang.jenis_kelamin wajib). Ayah/ibu diturunkan dari
        // hubungan; untuk wali lain diisi pada formulir.
        Schema::table('spmb_pendaftar_wali', function (Blueprint $table) {
            $table->char('jenis_kelamin', 1)->nullable();
        });
        DB::statement("ALTER TABLE spmb_pendaftar_wali ADD CONSTRAINT spmb_pendaftar_wali_jk_chk CHECK (jenis_kelamin IS NULL OR jenis_kelamin IN ('L', 'P'))");

        // Mencegah pendaftaran ganda pada periode yang sama bagi anak yang sama (tanpa NIK sekalipun).
        // Gagal bila sudah ada data ganda: bersihkan dulu duplikatnya.
        DB::statement('CREATE UNIQUE INDEX spmb_pendaftar_nama_lahir_unik ON spmb_pendaftar (periode_id, LOWER(nama_lengkap), tanggal_lahir)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS spmb_pendaftar_nama_lahir_unik');
        DB::statement('ALTER TABLE spmb_pendaftar_wali DROP CONSTRAINT IF EXISTS spmb_pendaftar_wali_jk_chk');
        Schema::table('spmb_pendaftar_wali', function (Blueprint $table) {
            $table->dropColumn('jenis_kelamin');
        });
    }
};
