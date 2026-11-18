<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paket B: pelacakan log yang gagal/belum terpetakan agar tidak diulang tanpa batas dan tampil di panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absensi_log_mentah', function (Blueprint $table) {
            $table->text('galat')->nullable()->after('diproses_pada');
            $table->unsignedSmallInteger('percobaan')->default(0)->after('galat');
            $table->timestamp('gagal_pada')->nullable()->after('percobaan');

            $table->index(['diproses_pada', 'percobaan'], 'alm_antrean_idx');
        });
    }

    public function down(): void
    {
        Schema::table('absensi_log_mentah', function (Blueprint $table) {
            $table->dropIndex('alm_antrean_idx');
            $table->dropColumn(['galat', 'percobaan', 'gagal_pada']);
        });
    }
};
