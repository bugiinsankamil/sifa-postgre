<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('unit_kerja', function (Blueprint $table) {
            $table->foreignId('kepala_pegawai_id')->nullable()->after('cabang_id')
                ->constrained('pegawai')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_kerja', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kepala_pegawai_id');
        });
    }
};
