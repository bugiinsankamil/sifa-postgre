<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cabang', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama');
            $table->text('alamat')->nullable();
            $table->foreignId('wilayah_id')->nullable()->constrained('wilayah')->nullOnDelete();
            $table->string('telepon', 30)->nullable();
            $table->string('email')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->date('tanggal_berdiri')->nullable();
            $table->string('status', 20)->default('aktif');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('jenjang', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 10)->unique();
            $table->string('nama');
            $table->unsignedSmallInteger('urutan');
            $table->timestamps();
        });

        // Satu sekolah = satu jenjang di satu cabang
        Schema::create('sekolah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->foreignId('jenjang_id')->constrained('jenjang')->restrictOnDelete();
            $table->string('nama');
            $table->string('npsn', 20)->nullable()->unique();
            $table->string('nss', 20)->nullable();
            $table->text('alamat')->nullable();
            $table->string('telepon', 30)->nullable();
            $table->string('email')->nullable();
            $table->date('tanggal_operasional')->nullable();
            $table->string('status', 20)->default('aktif');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['cabang_id', 'jenjang_id']);
            // Target FK komposit: tabel lain memakai (sekolah_id, cabang_id) agar keduanya selalu cocok.
            $table->unique(['id', 'cabang_id']);
        });

        Schema::create('tingkat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenjang_id')->constrained('jenjang')->restrictOnDelete();
            $table->string('kode', 10);
            $table->string('nama');
            $table->unsignedSmallInteger('urutan');
            $table->timestamps();

            $table->unique(['jenjang_id', 'kode']);
        });

        Schema::create('unit_kerja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('unit_kerja')->nullOnDelete();
            $table->foreignId('cabang_id')->nullable()->constrained('cabang')->restrictOnDelete();
            $table->unsignedBigInteger('sekolah_id')->nullable();
            $table->string('kode', 30);
            $table->string('nama');
            $table->string('tipe', 20);                     // yayasan/cabang/divisi/bagian
            $table->string('status', 20)->default('aktif');
            $table->timestamps();

            $table->index('kode');
            $table->foreign(['sekolah_id', 'cabang_id'])
                ->references(['id', 'cabang_id'])->on('sekolah')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE unit_kerja ADD CONSTRAINT unit_kerja_sekolah_butuh_cabang CHECK (sekolah_id IS NULL OR cabang_id IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_kerja');
        Schema::dropIfExists('tingkat');
        Schema::dropIfExists('sekolah');
        Schema::dropIfExists('jenjang');
        Schema::dropIfExists('cabang');
    }
};
