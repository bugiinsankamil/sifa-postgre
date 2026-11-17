<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuan_cuti', function (Blueprint $table) {
            $table->text('alasan_penolakan')->nullable()->after('disetujui_pada');
        });
        Schema::table('pengajuan_izin', function (Blueprint $table) {
            $table->text('alasan_penolakan')->nullable()->after('disetujui_pada');
        });

        Schema::table('absensi_pegawai', function (Blueprint $table) {
            $table->foreignId('pengajuan_cuti_id')->nullable()->after('diajukan_oleh')
                ->constrained('pengajuan_cuti')->nullOnDelete();
            $table->foreignId('pengajuan_izin_id')->nullable()->after('pengajuan_cuti_id')
                ->constrained('pengajuan_izin')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('absensi_pegawai', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pengajuan_izin_id');
            $table->dropConstrainedForeignId('pengajuan_cuti_id');
        });
        Schema::table('pengajuan_izin', function (Blueprint $table) {
            $table->dropColumn('alasan_penolakan');
        });
        Schema::table('pengajuan_cuti', function (Blueprint $table) {
            $table->dropColumn('alasan_penolakan');
        });
    }
};
