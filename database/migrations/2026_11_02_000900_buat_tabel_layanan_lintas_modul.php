<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---- Approval generik ------------------------------------------------
        Schema::create('approval_alur', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nama');
            $table->string('modul', 50);
            $table->foreignId('cabang_id')->nullable()->constrained('cabang')->restrictOnDelete();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        // Beberapa baris dengan urutan yang sama = beberapa approver pada langkah yang sama
        Schema::create('approval_alur_langkah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alur_id')->constrained('approval_alur')->cascadeOnDelete();
            $table->unsignedSmallInteger('urutan');
            $table->string('tipe_approver', 30);            // role/jabatan/atasan_langsung/user
            $table->string('approver_ref', 100)->nullable();
            $table->string('mode', 20)->default('semua');   // semua/salah_satu
            $table->decimal('nominal_min', 15, 2)->nullable();
            $table->decimal('nominal_maks', 15, 2)->nullable();
            $table->timestamps();

            $table->index(['alur_id', 'urutan']);
        });

        Schema::create('approval_permohonan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alur_id')->constrained('approval_alur')->restrictOnDelete();
            $table->morphs('subjek');                       // subjek_type + subjek_id (+ index)
            $table->foreignId('pemohon_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('proses');
            $table->unsignedSmallInteger('langkah_saat_ini')->nullable();
            $table->timestamp('diajukan_pada');
            $table->timestamp('selesai_pada')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('approval_aksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permohonan_id')->constrained('approval_permohonan')->cascadeOnDelete();
            $table->foreignId('langkah_id')->constrained('approval_alur_langkah')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('aksi', 20);                     // setuju/tolak/revisi
            $table->text('catatan')->nullable();
            $table->timestamp('waktu');
            $table->timestamps();

            $table->index('permohonan_id');
        });

        Schema::create('approval_delegasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dari_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('kepada_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('alur_id')->nullable()->constrained('approval_alur')->cascadeOnDelete();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->timestamps();

            $table->index(['dari_user_id', 'tanggal_mulai', 'tanggal_selesai'], 'approval_delegasi_periode_idx');
        });
        DB::statement('ALTER TABLE approval_delegasi ADD CONSTRAINT approval_delegasi_chk CHECK (tanggal_selesai >= tanggal_mulai AND dari_user_id <> kepada_user_id)');

        // ---- Lampiran dan komentar polimorfik ------------------------------------
        Schema::create('lampiran', function (Blueprint $table) {
            $table->id();
            $table->morphs('lampiran');                     // lampiran_type + lampiran_id (+ index)
            $table->string('jenis', 50)->nullable();
            $table->string('disk', 30);
            $table->text('path');
            $table->string('nama_asli');
            $table->string('mime', 100);
            $table->unsignedBigInteger('ukuran');
            $table->string('checksum', 64)->nullable();
            $table->text('teks_ekstrak')->nullable();       // hasil OCR/ekstraksi untuk pencarian
            $table->foreignId('diunggah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('checksum');
        });

        Schema::create('komentar', function (Blueprint $table) {
            $table->id();
            $table->morphs('komentar');                     // komentar_type + komentar_id (+ index)
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('komentar')->cascadeOnDelete();
            $table->text('isi');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('komentar');
        Schema::dropIfExists('lampiran');
        Schema::dropIfExists('approval_delegasi');
        Schema::dropIfExists('approval_aksi');
        Schema::dropIfExists('approval_permohonan');
        Schema::dropIfExists('approval_alur_langkah');
        Schema::dropIfExists('approval_alur');
    }
};
