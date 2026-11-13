<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templat_pesan', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50);
            $table->string('kanal', 20);                         // whatsapp/email
            $table->string('judul')->nullable();                 // dipakai kanal email
            $table->text('isi');                                 // memakai variabel {{nama_variabel}}
            $table->jsonb('variabel');                           // daftar variabel yang boleh dipakai
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();

            $table->unique(['kode', 'kanal']);
        });

        Schema::create('antrean_pesan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->nullable()->constrained('cabang')->restrictOnDelete();
            $table->string('kanal', 20);
            $table->string('tujuan');                            // nomor (62812xxxx) atau alamat email
            $table->foreignId('templat_id')->nullable()->constrained('templat_pesan')->nullOnDelete();
            $table->string('judul')->nullable();
            $table->text('isi');                                 // isi akhir setelah variabel diganti
            $table->string('status', 20)->default('antri');      // antri/diproses/terkirim/gagal/dibatalkan
            $table->unsignedSmallInteger('percobaan')->default(0);
            $table->timestamp('dijadwalkan_pada')->useCurrent();
            $table->timestamp('diproses_pada')->nullable();
            $table->timestamp('dikirim_pada')->nullable();
            $table->jsonb('respons')->nullable();
            $table->text('galat')->nullable();
            $table->string('kunci_unik', 150)->nullable()->unique(); // mencegah pesan yang sama terkirim dua kali
            $table->nullableMorphs('konteks');                   // data sumber pesan (mis. spmb_pendaftar)
            $table->timestamps();

            $table->index(['status', 'dijadwalkan_pada']);
            $table->index('cabang_id');
        });

        DB::statement("ALTER TABLE antrean_pesan ADD CONSTRAINT antrean_pesan_status_chk CHECK (status IN ('antri', 'diproses', 'terkirim', 'gagal', 'dibatalkan'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('antrean_pesan');
        Schema::dropIfExists('templat_pesan');
    }
};
