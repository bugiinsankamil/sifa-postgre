<?php

namespace App\Services\Akademik;

use App\Exceptions\Akademik\PenilaianException;
use App\Models\KomponenPenilaian;
use App\Models\NilaiAkhir;
use App\Models\NilaiSiswa;
use App\Models\Penilaian;
use App\Models\PenugasanMengajar;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PenilaianService
{
    /** Buat penilaian (komponen) untuk satu penugasan mengajar. */
    public function buatPenilaian(
        PenugasanMengajar $penugasan,
        Semester $semester,
        KomponenPenilaian $komponen,
        string $judul,
        string $tanggal,
        ?float $nilaiMaks = null,
        ?User $pengguna = null
    ): Penilaian {
        if ($komponen->is_aktif !== true) {
            throw new PenilaianException('Komponen penilaian tidak aktif.');
        }

        return DB::transaction(function () use ($penugasan, $semester, $komponen, $judul, $tanggal, $nilaiMaks, $pengguna) {
            $penilaian = Penilaian::create([
                'penugasan_mengajar_id' => $penugasan->id,
                'semester_id' => $semester->id,
                'komponen_id' => $komponen->id,
                'judul' => $judul,
                'tanggal' => $tanggal,
                'nilai_maks' => $nilaiMaks ?? 100.00,
                'status' => 'draft',
                'sumber' => 'manual',
            ]);

            return $penilaian->fresh();
        });
    }

    /** Input nilai siswa untuk satu penilaian. Idemen via unique (penilaian_id, siswa_id). */
    public function inputNilai(
        Penilaian $penilaian,
        Siswa $siswa,
        ?float $nilai = null,
        ?float $nilaiRemedial = null,
        ?string $catatan = null,
        User $dicatatOleh
    ): NilaiSiswa {
        if ($penilaian->status === 'final') {
            throw new PenilaianException('Penilaian sudah final. Nilai tidak dapat diubah lagi.');
        }

        return DB::transaction(function () use ($penilaian, $siswa, $nilai, $nilaiRemedial, $catatan, $dicatatOleh) {
            $nilaiSiswa = NilaiSiswa::updateOrCreate(
                ['penilaian_id' => $penilaian->id, 'siswa_id' => $siswa->id],
                [
                    'nilai' => $nilai,
                    'nilai_remedial' => $nilaiRemedial,
                    'catatan' => $catatan,
                ]
            );

            return $nilaiSiswa->fresh();
        });
    }

    /** Finalisasi penilaian (draft -> final). Tidak dapat diubah kembali. */
    public function finalisasiPenilaian(Penilaian $penilaian, User $disetujuiOleh): Penilaian
    {
        if ($penilaian->status === 'final') {
            throw new PenilaianException('Penilaian sudah final.');
        }

        return DB::transaction(function () use ($penilaian, $disetujuiOleh) {
            $penilaian->update(['status' => 'final']);

            return $penilaian->fresh();
        });
    }
}