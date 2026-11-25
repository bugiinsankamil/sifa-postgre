<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('penugasan_mengajar', function (Blueprint $table) {
            $table->unsignedInteger('cabang_id')->nullable()->after('id');
            $table->index('cabang_id');
            $table->foreign('cabang_id')->references('id')->on('cabang')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('penugasan_mengajar', function (Blueprint $table) {
            $table->dropForeign(['cabang_id']);
            $table->dropIndex(['cabang_id']);
            $table->dropColumn('cabang_id');
        });
    }
};