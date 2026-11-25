<?php

namespace App\Services\Akademik;

use App\Exceptions\Akademik\ModulAjarException;
use App\Models\ModulAjar;
use App\Models\ModulAjarTujuan;
use App\Models\ModulAjarVersi;
use App\Models\Pegawai;
use App\Models\Semester;
use App\Models\Sekolah;
use App\Models\TahunAjaran;
use App\Models\Tingkat;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ModulAjarService
{
    /** Buat modul ajar baru (status draft). */
    public function buatModulAjar(
        Sekolah $sekolah,
        TahunAjaran $tahunAjaran,
        ?Semester $semester,
        Pegawai $pegawai,
        string $judul,
        ?int $alokasiWaktu = null,
        ?array $isi = null,
        ?Tingkat $tingkat = null,
        ?User $pengguna = null
    ): ModulAjar {
        return DB::transaction(function () use ($sekolah, $tahunAjaran, $semester, $pegawai, $judul, $alokasiWaktu, $isi, $tingkat, $pengguna) {
            $modul = ModulAjar::create([
                'cabang_id' => $sekolah->cabang_id,
                'sekolah_id' => $sekolah->id,
                'tahun_ajaran_id' => $tahunAjaran->id,
                'semester_id' => $semester?->id,
                'mata_pelajaran_id' => null, // diisi nanti jika perlu
                'tingkat_id' => $tingkat?->id,
                'pegawai_id' => $pegawai->id,
                'judul' => $judul,
                'alokasi_waktu' => $alokasiWaktu,
                'isi' => $isi,
                'versi' => 1,
                'status' => 'draft',
            ]);

            return $modul->fresh();
        });
    }

    /** Ajukan persetujuan modul ajar (draft -> diajukan). */
    public function ajukanPersetujuan(ModulAjar $modul, User $pengaju): ModulAjar
    {
        if ($modul->status !== 'draft') {
            throw new ModulAjarException('Modul ajar hanya dapat diajukan dari status draft.');
        }

        return DB::transaction(function () use ($modul, $pengaju) {
            $modul->update(['status' => 'diajukan']);

            return $modul->fresh();
        });
    }

    /** Setujui modul ajar (diajukan -> disetujui). Membuat versi 1 secara otomatis. */
    public function setujuiModulAjar(ModulAjar $modul, User $disetujuiOleh): ModulAjar
    {
        if ($modul->status !== 'diajukan') {
            throw new ModulAjarException('Hanya modul ajar dengan status diajukan yang dapat disetujui.');
        }

        return DB::transaction(function () use ($modul, $disetujuiOleh) {
            $modul->update(['status' => 'disetujui']);

            // Buat versi pertama
            ModulAjarVersi::create([
                'modul_ajar_id' => $modul->id,
                'versi' => $modul->versi,
                'isi' => $modul->isi,
                'dibuat_oleh' => $disetujuiOleh->id,
                'catatan' => 'Versi awal saat disetujui.',
            ]);

            return $modul->fresh();
        });
    }

    /** Tolak modul ajar (diajukan -> revisi). Alasan wajib. */
    public function tolakModulAjar(ModulAjar $modul, User $penolak, string $alasan): ModulAjar
    {
        if (blank($alasan)) {
            throw new ModulAjarException('Alasan penolakan wajib diisi.');
        }

        if ($modul->status !== 'diajukan') {
            throw new ModulAjarException('Hanya modul ajar dengan status diajukan yang dapat ditolak.');
        }

        return DB::transaction(function () use ($modul, $alasan) {
            $modul->update(['status' => 'revisi']);

            return $modul->fresh();
        });
    }

    /** Revisi modul ajar (revisi/draft -> draft). */
    public function revisiModulAjar(ModulAjar $modul, array $data, User $pengguna): ModulAjar
    {
        if (! in_array($modul->status, ['draft', 'revisi'], true)) {
            throw new ModulAjarException('Hanya modul ajar dengan status draft atau revisi yang dapat diedit.');
        }

        $modul->update(array_filter($data, fn ($key) => in_array($key, ['judul', 'alokasi_waktu', 'isi', 'tingkat_id', 'semester_id'], true), ARRAY_FILTER_USE_KEY));
        $modul->update(['status' => 'draft']);

        return $modul->fresh();
    }

    /** Buat versi baru dari modul ajar yang sudah disetujui. */
    public function buatVersi(ModulAjar $modul, ?array $isiBaru, ?string $catatan, User $pengguna): ModulAjarVersi
    {
        if ($modul->status !== 'disetujui') {
            throw new ModulAjarException('Hanya modul ajar dengan status disetujui yang dapat membuat versi baru.');
        }

        return DB::transaction(function () use ($modul, $isiBaru, $catatan, $pengguna) {
            $versiBaru = $modul->versi + 1;

            $versi = ModulAjarVersi::create([
                'modul_ajar_id' => $modul->id,
                'versi' => $versiBaru,
                'isi' => $isiBaru,
                'dibuat_oleh' => $pengguna->id,
                'catatan' => $catatan,
            ]);

            $modul->update(['versi' => $versiBaru]);

            return $versi->fresh();
        });
    }

    /** Nonaktifkan modul ajar (disetujui -> revisi sebagai tanda perlu review ulang). */
    public function nonaktifkanModulAjar(ModulAjar $modul, User $pengguna): ModulAjar
    {
        if ($modul->status !== 'disetujui') {
            throw new ModulAjarException('Hanya modul ajar dengan status disetujui yang dapat dinonaktifkan.');
        }

        $modul->update(['status' => 'revisi']);

        return $modul->fresh();
    }
}