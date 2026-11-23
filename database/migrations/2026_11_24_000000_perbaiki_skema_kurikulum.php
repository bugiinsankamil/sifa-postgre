<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // kurikulum_sekolah: tambah cabang_id agar isolasi cabang bisa diterapkan secara langsung,
        // dan pasang FK komposit terhadap sekolah(id, cabang_id).
        Schema::table('kurikulum_sekolah', function (Blueprint $table) {
            $table->unsignedInteger('cabang_id')->nullable()->after('sekolah_id');

            $table->foreign(['sekolah_id', 'cabang_id'])
                ->references(['id', 'cabang_id'])
                ->on('sekolah')
                ->restrictOnDelete();

            $table->index('cabang_id');
        });

        // Hanya satu kurikulum default (tanpa tingkat) per sekolah + tahun ajaran.
        DB::statement(
            "CREATE UNIQUE INDEX kurikulum_sekolah_default_uniq ON kurikulum_sekolah (sekolah_id, tahun_ajaran_id) WHERE tingkat_id IS NULL"
        );

        // kompetensi_guru: tambah cabang_id (diambil dari pegawai saat membuat data).
        Schema::table('kompetensi_guru', function (Blueprint $table) {
            $table->unsignedInteger('cabang_id')->nullable()->after('pegawai_id');
            $table->index('cabang_id');
        });

        // Struktur kurikulum: pastikan kkm non-negatif.
        Schema::table('struktur_kurikulum', function (Blueprint $table) {
            DB::statement(
                "ALTER TABLE struktur_kurikulum ADD CONSTRAINT struktur_kurikulum_kkm_non_negatif CHECK (kkm >= 0)"
            );
        });
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS kurikulum_sekolah_default_uniq");

        Schema::table('struktur_kurikulum', function (Blueprint $table) {
            DB::statement("ALTER TABLE struktur_kurikulum DROP CONSTRAINT IF EXISTS struktur_kurikulum_kkm_non_negatif");
        });

        Schema::table('kompetensi_guru', function (Blueprint $table) {
            $table->dropIndex(['cabang_id']);
            $table->dropColumn('cabang_id');
        });

        Schema::table('kurikulum_sekolah', function (Blueprint $table) {
            $table->dropForeign(['sekolah_id', 'cabang_id']);
            $table->dropIndex(['cabang_id']);
            $table->dropColumn('cabang_id');
        });
    }
};
