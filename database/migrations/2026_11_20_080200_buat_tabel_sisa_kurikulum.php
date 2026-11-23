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
        // Create kurikulum_sekolah table
        Schema::create('kurikulum_sekolah', function ($table) {
            $table->id();
            $table->unsignedInteger('kurikulum_id');
            $table->unsignedInteger('sekolah_id');
            $table->unsignedInteger('tahun_ajaran_id');
            $table->unsignedInteger('tingkat_id')->nullable();
            $table->timestamps();

            // Constraints and indexes
            $table->unique(['sekolah_id', 'tahun_ajaran_id', 'tingkat_id'], 'kurikulum_sekolah_unique');

            $table->index('kurikulum_id');
            $table->foreign('kurikulum_id')
                ->references('id')
                ->on('kurikulum')
                ->on_delete('cascade');

            $table->index('sekolah_id');
            $table->foreign('sekolah_id')
                ->references('id')
                ->on('sekolah')
                ->on_delete('cascade');

            $table->index('tahun_ajaran_id');
            $table->foreign('tahun_ajaran_id')
                ->references('id')
                ->on('tahun_ajaran')
                ->on_delete('cascade');

            $table->index('tingkat_id');
            $table->foreign('tingkat_id')
                ->references('id')
                ->on('tingkat')
                ->on_delete('set null');
        });

        // Create kompetensi_guru table
        Schema::create('kompetensi_guru', function ($table) {
            $table->id();
            $table->unsignedInteger('pegawai_id');
            $table->unsignedInteger('mata_pelajaran_id');
            $table->unsignedInteger('tingkat_id')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            // Constraints and indexes
            $table->index('pegawai_id');
            $table->foreign('pegawai_id')
                ->references('id')
                ->on('pegawai')
                ->on_delete('cascade');

            $table->index('mata_pelajaran_id');
            $table->foreign('mata_pelajaran_id')
                ->references('id')
                ->on('mata_pelajaran')
                ->on_delete('cascade');

            $table->index('tingkat_id');
            $table->foreign('tingkat_id')
                ->references('id')
                ->on('tingkat')
                ->on_delete('set null');
        });

        // Create tujuan_pembelajaran table
        Schema::create('tujuan_pembelajaran', function ($table) {
            $table->id();
            $table->unsignedInteger('kurikulum_id');
            $table->unsignedInteger('mata_pelajaran_id');
            $table->unsignedInteger('tingkat_id');
            $table->string('fase')->default('A');
            $table->string('kode')->nullable();
            $table->text('elemen')->nullable();
            $table->text('uraian');
            $table->unsignedInteger('urutan')->default(1);
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();

            // Constraints and indexes
            $table->index(['kurikulum_id', 'mata_pelajaran_id', 'tingkat_id']);
            $table->index('fase');
            $table->index('kode');

            $table->foreign('kurikulum_id')
                ->references('id')
                ->on('kurikulum')
                ->on_delete('cascade');

            $table->foreign('mata_pelajaran_id')
                ->references('id')
                ->on('mata_pelajaran')
                ->on_delete('cascade');

            $table->foreign('tingkat_id')
                ->references('id')
                ->on('tingkat')
                ->on_delete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tujuan_pembelajaran');
        Schema::dropIfExists('kompetensi_guru');
        Schema::dropIfExists('kurikulum_sekolah');
    }
};