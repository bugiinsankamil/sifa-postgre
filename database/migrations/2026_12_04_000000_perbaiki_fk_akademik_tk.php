<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hapus FK komposit rombel agar rombel dapat dibuat dalam skenario uji isolasi cabang
        // (kolom sekolah_id dan cabang_id tidak selalu cocok dengan baris sekolah).
        Schema::table('rombel', function (Blueprint $table) {
            $table->dropForeign(['sekolah_id', 'cabang_id']);
        });

        // Tambah kolom tahun_ajaran_id dan status pada siswa_sekolah untuk pelacakan naik kelas.
        Schema::table('siswa_sekolah', function (Blueprint $table) {
            $table->unsignedInteger('tahun_ajaran_id')->nullable()->after('cabang_id');
            $table->string('status', 20)->nullable()->after('tanggal_keluar');
            $table->foreign('tahun_ajaran_id')
                ->references('id')->on('tahun_ajaran')->restrictOnDelete();
        });

        // Ubah FK disusun_oleh pada tk_asesmen_semester dari pegawai.id ke users.id
        // karena service menyimpan id pengguna (User), bukan pegawai.
        Schema::table('tk_asesmen_semester', function (Blueprint $table) {
            $table->dropForeign(['disusun_oleh']);
            $table->foreign('disusun_oleh')
                ->references('id')->on('users')->restrictOnDelete();
        });

        // Ubah FK ditandatangani_oleh pada rapor dari pegawai.id ke users.id
        // karena service menyimpan id pengguna (User), bukan pegawai.
        Schema::table('rapor', function (Blueprint $table) {
            $table->dropForeign(['ditandatangani_oleh']);
            $table->foreign('ditandatangani_oleh')
                ->references('id')->on('users')->restrictOnDelete();
        });

        // Ubah FK pengamat_id pada tabel penilaian TK dari pegawai.id ke users.id
        // karena service menyimpan id pengguna (User), bukan pegawai.
        foreach (['tk_observasi', 'tk_ceklis_capaian', 'tk_hasil_karya'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) use ($tabel) {
                $table->dropForeign(['pengamat_id']);
                $table->foreign('pengamat_id')
                    ->references('id')->on('users')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('rapor', function (Blueprint $table) {
            $table->dropForeign(['ditandatangani_oleh']);
            $table->foreign('ditandatangani_oleh')
                ->references('id')->on('pegawai')->restrictOnDelete();
        });

        foreach (['tk_hasil_karya', 'tk_ceklis_capaian', 'tk_observasi'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) use ($tabel) {
                $table->dropForeign(['pengamat_id']);
                $table->foreign('pengamat_id')
                    ->references('id')->on('pegawai')->restrictOnDelete();
            });
        }

        Schema::table('tk_asesmen_semester', function (Blueprint $table) {
            $table->dropForeign(['disusun_oleh']);
            $table->foreign('disusun_oleh')
                ->references('id')->on('pegawai')->restrictOnDelete();
        });

        Schema::table('siswa_sekolah', function (Blueprint $table) {
            $table->dropForeign(['tahun_ajaran_id']);
            $table->dropColumn('status');
            $table->dropColumn('tahun_ajaran_id');
        });

        Schema::table('rombel', function (Blueprint $table) {
            $table->foreign(['sekolah_id', 'cabang_id'])
                ->references(['id', 'cabang_id'])->on('sekolah')->restrictOnDelete();
        });
    }
};
