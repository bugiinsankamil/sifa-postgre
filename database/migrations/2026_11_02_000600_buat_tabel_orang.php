<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu-satunya tempat identitas orang. Siswa, pegawai, dan orang tua hanyalah peran.
        Schema::create('orang', function (Blueprint $table) {
            $table->id();
            $table->text('nik')->nullable();                          // terenkripsi (cast 'encrypted' di model)
            $table->string('nik_hash', 64)->nullable()->unique();     // HMAC-SHA256 dari NIK: cek duplikat dan pencarian
            $table->string('nama_lengkap');
            $table->string('nama_panggilan', 100)->nullable();
            $table->string('gelar_depan', 50)->nullable();
            $table->string('gelar_belakang', 50)->nullable();
            $table->char('jenis_kelamin', 1);                         // L/P
            $table->string('tempat_lahir', 100)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('agama_ref', 50)->nullable();
            $table->string('status_nikah_ref', 50)->nullable();
            $table->string('golongan_darah', 3)->nullable();
            $table->string('kewarganegaraan', 50)->nullable()->default('WNI');
            $table->string('telepon', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('foto_path')->nullable();
            $table->string('status_hidup', 20)->default('hidup');
            $table->timestamps();
            $table->softDeletes();

            $table->index('tanggal_lahir');
        });

        DB::statement("ALTER TABLE orang ADD CONSTRAINT orang_jenis_kelamin_chk CHECK (jenis_kelamin IN ('L', 'P'))");
        // Pencarian nama parsial yang cepat: WHERE nama_lengkap ILIKE '%budi%'
        DB::statement('CREATE INDEX orang_nama_lengkap_trgm ON orang USING gin (nama_lengkap gin_trgm_ops)');

        Schema::create('orang_alamat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orang_id')->constrained('orang')->cascadeOnDelete();
            $table->string('jenis', 20);                              // ktp/domisili
            $table->text('alamat');
            $table->string('rt', 5)->nullable();
            $table->string('rw', 5)->nullable();
            $table->foreignId('wilayah_id')->nullable()->constrained('wilayah')->nullOnDelete();
            $table->string('kode_pos', 10)->nullable();
            $table->boolean('is_utama')->default(false);
            $table->timestamps();

            $table->index('orang_id');
        });

        Schema::create('orang_kontak_darurat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orang_id')->constrained('orang')->cascadeOnDelete();
            $table->string('nama');
            $table->string('hubungan_ref', 50);
            $table->string('telepon', 30);
            $table->timestamps();

            $table->index('orang_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orang_kontak_darurat');
        Schema::dropIfExists('orang_alamat');
        Schema::dropIfExists('orang');
    }
};
