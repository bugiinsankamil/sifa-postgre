<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tahun_ajaran', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();           // 2026/2027
            $table->string('nama');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->boolean('is_aktif')->default(false);
            $table->timestamps();
        });

        Schema::create('semester', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->restrictOnDelete();
            $table->string('nama', 30);                     // Ganjil/Genap
            $table->unsignedSmallInteger('urutan');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->boolean('is_aktif')->default(false);
            $table->timestamps();

            $table->unique(['tahun_ajaran_id', 'urutan']);
        });

        DB::statement('ALTER TABLE tahun_ajaran ADD CONSTRAINT tahun_ajaran_tanggal_chk CHECK (tanggal_selesai > tanggal_mulai)');
        DB::statement('ALTER TABLE semester ADD CONSTRAINT semester_tanggal_chk CHECK (tanggal_selesai > tanggal_mulai)');

        // Hanya satu tahun ajaran dan satu semester yang boleh aktif
        DB::statement('CREATE UNIQUE INDEX tahun_ajaran_satu_aktif ON tahun_ajaran (is_aktif) WHERE is_aktif');
        DB::statement('CREATE UNIQUE INDEX semester_satu_aktif ON semester (is_aktif) WHERE is_aktif');
    }

    public function down(): void
    {
        Schema::dropIfExists('semester');
        Schema::dropIfExists('tahun_ajaran');
    }
};
