<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referensi', function (Blueprint $table) {
            $table->id();
            $table->string('kategori', 50);
            $table->string('kode', 50);
            $table->string('label');
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('is_aktif')->default(true);
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->unique(['kategori', 'kode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referensi');
    }
};
