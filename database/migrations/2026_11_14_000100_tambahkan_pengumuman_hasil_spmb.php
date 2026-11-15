<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spmb_periode', function (Blueprint $table) {
            $table->timestamp('hasil_diumumkan_pada')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('spmb_periode', function (Blueprint $table) {
            $table->dropColumn('hasil_diumumkan_pada');
        });
    }
};
