<?php

namespace App\Services\Akademik;

use App\Exceptions\Akademik\PenilaianTkException;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TkAsesmenSemester;
use App\Models\TkAspek;
use App\Models\TkCeklisCapaian;
use App\Models\TkHasilKarya;
use App\Models\TkObservasi;
use App\Models\User;
use App\Support\CabangKonteks;
use Illuminate\Support\Facades\DB;

class PenilaianTkService
{
    /** Catat observasi harian siswa TK. */
    public function catatObservasi(
        Siswa $siswa,
        Semester $semester,
        string $tanggal,
        string $uraian,
        ?string $kegiatan = null,
        ?string $interpretasi = null,
        ?string $tindakLanjut = null,
        ?int $temaId = null,
        ?int $aspekId = null,
        User $pengamat
    ): TkObservasi {
        $this->pastikanLingkupCabang($siswa);

        return DB::transaction(function () use ($siswa, $semester, $tanggal, $uraian, $kegiatan, $interpretasi, $tindakLanjut, $temaId, $aspekId, $pengamat) {
            $observasi = TkObservasi::create([
                'siswa_id' => $siswa->id,
                'cabang_id' => $siswa->cabang_id,
                'rombel_id' => $siswa->rombelSiswaAktif->first()?->rombel_id ?? null,
                'semester_id' => $semester->id,
                'tema_id' => $temaId,
                'aspek_id' => $aspekId,
                'tanggal' => $tanggal,
                'kegiatan' => $kegiatan,
                'uraian' => $uraian,
                'interpretasi' => $interpretasi,
                'tindak_lanjut' => $tindakLanjut,
                'pengamat_id' => $pengamat->id,
            ]);

            return $observasi->fresh();
        });
    }

    /** Catat capaian indikator per siswa. Setiap penilaian disimpan sebagai baris baru. */
    public function catatCeklisCapaian(
        Siswa $siswa,
        Semester $semester,
        int $indikatorId,
        string $tanggal,
        ?int $skalaButirId = null,
        ?string $catatan = null,
        ?int $observasiId = null,
        User $pengamat
    ): TkCeklisCapaian {
        $this->pastikanLingkupCabang($siswa);

        return DB::transaction(function () use ($siswa, $semester, $indikatorId, $tanggal, $skalaButirId, $catatan, $observasiId, $pengamat) {
            $ceklis = TkCeklisCapaian::create([
                'siswa_id' => $siswa->id,
                'cabang_id' => $siswa->cabang_id,
                'semester_id' => $semester->id,
                'indikator_id' => $indikatorId,
                'tanggal' => $tanggal,
                'skala_butir_id' => $skalaButirId,
                'pengamat_id' => $pengamat->id,
                'catatan' => $catatan,
                'observasi_id' => $observasiId,
            ]);

            return $ceklis->fresh();
        });
    }

    /** Catat hasil karya siswa per tema/aspek. */
    public function catatHasilKarya(
        Siswa $siswa,
        Semester $semester,
        string $tanggal,
        string $judul,
        ?string $uraian = null,
        ?int $temaId = null,
        ?int $aspekId = null,
        User $pengamat
    ): TkHasilKarya {
        $this->pastikanLingkupCabang($siswa);

        return DB::transaction(function () use ($siswa, $semester, $tanggal, $judul, $uraian, $temaId, $aspekId, $pengamat) {
            $karya = TkHasilKarya::create([
                'siswa_id' => $siswa->id,
                'cabang_id' => $siswa->cabang_id,
                'semester_id' => $semester->id,
                'tema_id' => $temaId,
                'aspek_id' => $aspekId,
                'tanggal' => $tanggal,
                'judul' => $judul,
                'uraian' => $uraian,
                'pengamat_id' => $pengamat->id,
            ]);

            return $karya->fresh();
        });
    }

    /** Susun asesmen semester per aspek. Idemen per siswa + semester + aspek. */
    public function susunAsesmenSemester(
        Siswa $siswa,
        Semester $semester,
        TkAspek $aspek,
        string $deskripsi,
        ?int $skalaButirId = null,
        User $disusunOleh
    ): TkAsesmenSemester {
        $this->pastikanLingkupCabang($siswa);

        return DB::transaction(function () use ($siswa, $semester, $aspek, $deskripsi, $skalaButirId, $disusunOleh) {
            $asesmen = TkAsesmenSemester::updateOrCreate(
                [
                    'siswa_id' => $siswa->id,
                    'semester_id' => $semester->id,
                    'aspek_id' => $aspek->id,
                ],
                [
                    'skala_butir_id' => $skalaButirId,
                    'deskripsi' => $deskripsi,
                    'disusun_oleh' => $disusunOleh->id,
                    'status' => 'draft',
                ]
            );

            return $asesmen->fresh();
        });
    }

    /** Finalisasi asesmen semester. Setelah final, tidak dapat diubah ulang. */
    public function finalisasiAsesmen(TkAsesmenSemester $asesmen, User $disetujuiOleh): TkAsesmenSemester
    {
        $this->pastikanLingkupCabangViaAsesmen($asesmen);

        return DB::transaction(function () use ($asesmen, $disetujuiOleh) {
            if ($asesmen->status === 'final') {
                throw new PenilaianTkException('Asesmen semester sudah final. Tidak dapat diubah ulang.');
            }

            $asesmen->update(['status' => 'final']);

            return $asesmen->fresh();
        });
    }

    /* ------------------------------------------------------------------ */
    /* Private helpers                                                    */
    /* ------------------------------------------------------------------ */

    private function pastikanLingkupCabang(Siswa $siswa): void
    {
        $konteks = app(CabangKonteks::class);
        $diizinkan = $konteks->cabangDiizinkan();
        $cabangId = $this->cabangIdSiswa($siswa);

        if ($diizinkan !== null && $cabangId !== null && ! in_array((int) $cabangId, $diizinkan, true)) {
            throw new PenilaianTkException('Tidak berhak mengelola penilaian TK untuk cabang ini.');
        }
    }

    private function cabangIdSiswa(Siswa $siswa): ?int
    {
        return $siswa->rombelSiswaAktif()->withoutGlobalScopes()->first()?->cabang_id;
    }

    private function pastikanLingkupCabangViaAsesmen(TkAsesmenSemester $asesmen): void
    {
        $siswa = $asesmen->siswa;
        $this->pastikanLingkupCabang($siswa);
    }
}