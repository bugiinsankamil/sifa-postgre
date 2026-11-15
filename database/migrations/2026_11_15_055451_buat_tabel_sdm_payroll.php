<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F2 Kepegawaian/SDM — tahap 2d: Payroll (skema 3.6).
 * Gaji + potongan, snapshot rincian slip agar tidak berubah bila komponen diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('komponen_gaji', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();
            $table->string('nama');
            $table->string('tipe', 20); // pendapatan/potongan
            $table->string('cara_hitung', 20); // tetap/persen/formula/per_kehadiran
            $table->jsonb('formula')->nullable();
            $table->boolean('kena_pajak')->default(false);
            $table->boolean('kena_bpjs')->default(false);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });
        DB::statement("ALTER TABLE komponen_gaji ADD CONSTRAINT kg_tipe_chk CHECK (tipe IN ('pendapatan','potongan'))");
        DB::statement("ALTER TABLE komponen_gaji ADD CONSTRAINT kg_cara_chk CHECK (cara_hitung IN ('tetap','persen','formula','per_kehadiran'))");

        Schema::create('skema_gaji', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('skema_gaji_komponen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skema_id')->constrained('skema_gaji')->cascadeOnDelete();
            $table->foreignId('komponen_id')->constrained('komponen_gaji')->restrictOnDelete();
            $table->decimal('nominal', 15, 2)->nullable();
            $table->decimal('persen', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['skema_id', 'komponen_id']);
        });

        Schema::create('gaji_pegawai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->restrictOnDelete();
            $table->foreignId('skema_id')->nullable()->constrained('skema_gaji')->nullOnDelete();
            $table->decimal('gaji_pokok', 15, 2);
            $table->date('tanggal_berlaku');
            $table->string('no_sk', 100)->nullable();
            $table->string('status', 20)->default('aktif'); // aktif/berakhir
            $table->timestamps();

            $table->index(['pegawai_id', 'status']);
            $table->index('tanggal_berlaku');
        });
        DB::statement("ALTER TABLE gaji_pegawai ADD CONSTRAINT gp_status_chk CHECK (status IN ('aktif','berakhir'))");
        // Satu gaji aktif per pegawai
        DB::statement("CREATE UNIQUE INDEX gaji_pegawai_satu_aktif ON gaji_pegawai (pegawai_id) WHERE status = 'aktif'");

        Schema::create('gaji_pegawai_komponen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gaji_pegawai_id')->constrained('gaji_pegawai')->cascadeOnDelete();
            $table->foreignId('komponen_id')->constrained('komponen_gaji')->restrictOnDelete();
            $table->decimal('nominal', 15, 2);
            $table->timestamps();

            $table->unique(['gaji_pegawai_id', 'komponen_id']);
        });

        Schema::create('periode_gaji', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabang')->restrictOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->unsignedSmallInteger('bulan');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->string('status', 20)->default('draft'); // draft/dihitung/disetujui/dibayar/ditutup
            $table->timestamps();

            $table->unique(['cabang_id', 'tahun', 'bulan']);
        });
        DB::statement("ALTER TABLE periode_gaji ADD CONSTRAINT pg_status_chk CHECK (status IN ('draft','dihitung','disetujui','dibayar','ditutup'))");
        DB::statement('ALTER TABLE periode_gaji ADD CONSTRAINT pg_tanggal_chk CHECK (tanggal_selesai >= tanggal_mulai)');
        DB::statement('ALTER TABLE periode_gaji ADD CONSTRAINT pg_bulan_chk CHECK (bulan >= 1 AND bulan <= 12)');

        Schema::create('slip_gaji', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_id')->constrained('periode_gaji')->cascadeOnDelete();
            $table->foreignId('pegawai_id')->constrained('pegawai')->restrictOnDelete();
            $table->decimal('total_pendapatan', 15, 2)->default(0);
            $table->decimal('total_potongan', 15, 2)->default(0);
            $table->decimal('pph21', 15, 2)->default(0);
            $table->decimal('gaji_bersih', 15, 2)->default(0);
            $table->string('status', 20)->default('draft'); // draft/dihitung/disetujui/dibayar
            $table->date('tanggal_bayar')->nullable();
            $table->foreignId('rekening_pegawai_id')->nullable()->constrained('rekening_pegawai')->nullOnDelete();
            $table->timestamps();

            $table->unique(['periode_id', 'pegawai_id']);
            $table->index(['periode_id', 'status']);
        });
        DB::statement("ALTER TABLE slip_gaji ADD CONSTRAINT sg_status_chk CHECK (status IN ('draft','dihitung','disetujui','dibayar'))");

        Schema::create('slip_gaji_rincian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slip_id')->constrained('slip_gaji')->cascadeOnDelete();
            $table->foreignId('komponen_id')->nullable()->constrained('komponen_gaji')->nullOnDelete();
            $table->string('nama'); // snapshot
            $table->string('tipe', 20); // snapshot
            $table->decimal('nominal', 15, 2); // snapshot
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->index('slip_id');
        });
        DB::statement("ALTER TABLE slip_gaji_rincian ADD CONSTRAINT sgr_tipe_chk CHECK (tipe IN ('pendapatan','potongan'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('slip_gaji_rincian');
        Schema::dropIfExists('slip_gaji');
        Schema::dropIfExists('periode_gaji');
        Schema::dropIfExists('gaji_pegawai_komponen');
        Schema::dropIfExists('gaji_pegawai');
        Schema::dropIfExists('skema_gaji_komponen');
        Schema::dropIfExists('skema_gaji');
        Schema::dropIfExists('komponen_gaji');
    }
};
