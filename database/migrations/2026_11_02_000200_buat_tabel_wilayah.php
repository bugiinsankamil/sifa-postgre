<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wilayah', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 15)->unique();          // kode Kemendagri, mis. 16.71.01.1001
            $table->string('nama');
            $table->string('level', 20);                    // provinsi/kab_kota/kecamatan/kelurahan
            $table->string('parent_kode', 15)->nullable()->index();
            $table->string('kode_pos', 10)->nullable();
            $table->timestamps();

            $table->index('level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wilayah');
    }
};
