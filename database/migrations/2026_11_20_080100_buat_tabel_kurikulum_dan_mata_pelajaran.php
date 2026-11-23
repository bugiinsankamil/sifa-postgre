<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create kurikulum table
        Schema::create('kurikulum', function ($table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();

            // Index for active kurikulum per cabang
            $table->index(['kode', 'is_aktif']);
        });

        // Create mata_pelajaran table
        Schema::create('mata_pelajaran', function ($table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('kelompok_ref')->default('umum'); // umum/agama/muatan_lokal/pilihan
            $table->unsignedInteger('jenjang_id')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();

            // Indexes
            $table->index(['kode', 'is_aktif']);
            $table->index('jenjang_id');
            $table->foreign('jenjang_id')
                ->references('id')
                ->on('jenjang')
                ->on_delete('set null');
        });

        // Create struktur_kurikulum table
        Schema::create('struktur_kurikulum', function ($table) {
            $table->id();
            $table->unsignedInteger('kurikulum_id');
            $table->unsignedInteger('tingkat_id');
            $table->unsignedInteger('mata_pelajaran_id');
            $table->integer('jam_per_minggu');
            $table->boolean('is_wajib')->default(true);
            $table->decimal('kkm', 5, 2)->default(75.00);
            $table->timestamps();

            // Constraints and indexes
            $table->unique(['kurikulum_id', 'tingkat_id', 'mata_pelajaran_id'], 'struktur_kurikulum_unique');

            $table->index('kurikulum_id');
            $table->foreign('kurikulum_id')
                ->references('id')
                ->on('kurikulum')
                ->on_delete('cascade');

            $table->index('tingkat_id');
            $table->foreign('tingkat_id')
                ->references('id')
                ->on('tingkat')
                ->on_delete('restrict');

            $table->index('mata_pelajaran_id');
            $table->foreign('mata_pelajaran_id')
                ->references('id')
                ->on('mata_pelajaran')
                ->on_delete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('struktur_kurikulum');
        Schema::dropIfExists('mata_pelajaran');
        Schema::dropIfExists('kurikulum');
    }
};