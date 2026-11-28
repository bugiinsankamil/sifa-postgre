<?php

namespace App\Services\Akademik;

use App\Exceptions\Akademik\OlahNilaiException;
use App\Models\AturanOlahNilai;
use App\Models\NilaiAkhir;
use App\Models\NilaiSiswa;
use App\Models\PenugasanMengajar;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use App\Support\CabangKonteks;
use Illuminate\Support\Facades\DB;

class OlahNilaiService
{
    /** Hitung nilai akhir untuk semua siswa dalam satu penugasan mengajar + semester. Nilai yang sudah final tidak diubah ulang. */
    public function hitungNilaiAkhir(
        PenugasanMengajar $penugasan,
        Semester $semester,
        ?User $pengguna = null
    ): int {
        $this->pastikanLingkupCabang($penugasan);

        $aturan = $this->cariAturanOlah($penugasan);

        if (!$aturan) {
            throw new OlahNilaiException('Aturan olah nilai tidak ditemukan untuk mata pelajaran ini.');
        }

        $siswaList = $this->dapatkanSiswaAktif($penugasan);

        $finalIds = NilaiAkhir::where('penugasan_mengajar_id', $penugasan->id)
            ->where('semester_id', $semester->id)
            ->where('status', 'final')
            ->pluck('siswa_id')
            ->toArray();

        $dihitung = 0;

        DB::transaction(function () use ($penugasan, $semester, $aturan, $siswaList, $finalIds, &$dihitung) {
            foreach ($siswaList as $siswa) {
                if (in_array($siswa->id, $finalIds, true)) {
                    continue;
                }

                $nilaiAkhir = $this->hitungNilaiAkhirSiswa($penugasan, $siswa, $aturan, $semester);

                if ($nilaiAkhir['nilai'] === null) {
                    continue;
                }

                NilaiAkhir::updateOrCreate(
                    [
                        'penugasan_mengajar_id' => $penugasan->id,
                        'siswa_id' => $siswa->id,
                        'semester_id' => $semester->id,
                    ],
                    [
                        'nilai_akhir' => $nilaiAkhir['nilai'],
                        'skala_butir_id' => $nilaiAkhir['skala_butir_id'] ?? null,
                        'deskripsi' => $nilaiAkhir['deskripsi'] ?? null,
                        'kkm' => $aturan->kkm,
                        'is_tuntas' => $nilaiAkhir['is_tuntas'],
                        'status' => 'draft',
                    ]
                );

                $dihitung++;
            }
        });

        return $dihitung;
    }

    /** Finalisasi semua nilai akhir draft untuk penugasan ini. */
    public function finalisasiNilaiAkhir(
        PenugasanMengajar $penugasan,
        Semester $semester,
        User $disetujuiOleh
    ): int {
        $this->pastikanLingkupCabang($penugasan);

        $draftCount = NilaiAkhir::where('penugasan_mengajar_id', $penugasan->id)
            ->where('semester_id', $semester->id)
            ->where('status', 'draft')
            ->count();

        if ($draftCount === 0) {
            throw new OlahNilaiException('Tidak ada nilai akhir draft yang dapat difinalisasi.');
        }

        $final = NilaiAkhir::where('penugasan_mengajar_id', $penugasan->id)
            ->where('semester_id', $semester->id)
            ->where('status', 'draft')
            ->update(['status' => 'final']);

        return (int) $final;
    }

    /** Pastikan nilai final tidak dapat diubah kembali. */
    public function pastikanBolehMencatat(PenugasanMengajar $penugasan, Semester $semester): bool
    {
        $nilaiAkhir = NilaiAkhir::where('penugasan_mengajar_id', $penugasan->id)
            ->where('semester_id', $semester->id)
            ->where('status', 'final')
            ->first();

        return is_null($nilaiAkhir);
    }

    /** Koreksi satu nilai akhir (hanya jika status draft). Catat siapa yang memperbaiki. */
    public function koreksiNilaiAkhir(
        NilaiAkhir $nilaiAkhir,
        ?float $nilaiBaru = null,
        ?string $deskripsiBaru = null,
        ?bool $isTuntas = null,
        User $diperbaruiOleh
    ): NilaiAkhir {
        $this->pastikanLingkupCabangViaNilaiAkhir($nilaiAkhir);

        if ($nilaiAkhir->status === 'final') {
            throw new OlahNilaiException('Nilai akhir sudah final. Koreksi tidak diperbolehkan.');
        }

        $nilaiAkhir->update(array_filter([
            'nilai_akhir' => $nilaiBaru,
            'deskripsi' => $deskripsiBaru,
            'is_tuntas' => $isTuntas,
        ], fn ($v) => !is_null($v)));

        return $nilaiAkhir->fresh();
    }

    /* ------------------------------------------------------------------ */
    /* Private helpers                                                    */
    /* ------------------------------------------------------------------ */

    private function cariAturanOlah(PenugasanMengajar $penugasan): ?AturanOlahNilai
    {
        // Prioritas: mata pelajaran > tingkat > kurikulum > default (semua nullable)
        $aturan = AturanOlahNilai::where('sekolah_id', $penugasan->sekolah_id)
            ->where('tahun_ajaran_id', $penugasan->tahun_ajaran_id)
            ->where('mata_pelajaran_id', $penugasan->mata_pelajaran_id)
            ->where('tingkat_id', $penugasan->rombel->tingkat_id)
            ->first();

        if ($aturan) {
            return $aturan;
        }

        // Fallback: tingkat saja
        $aturan = AturanOlahNilai::where('sekolah_id', $penugasan->sekolah_id)
            ->where('tahun_ajaran_id', $penugasan->tahun_ajaran_id)
            ->whereNull('mata_pelajaran_id')
            ->where('tingkat_id', $penugasan->rombel->tingkat_id)
            ->whereNull('kurikulum_id')
            ->first();

        if ($aturan) {
            return $aturan;
        }

        // Fallback: kurikulum saja
        $aturan = AturanOlahNilai::where('sekolah_id', $penugasan->sekolah_id)
            ->where('tahun_ajaran_id', $penugasan->tahun_ajaran_id)
            ->whereNull('mata_pelajaran_id')
            ->whereNull('tingkat_id')
            ->whereNotNull('kurikulum_id')
            ->first();

        if ($aturan) {
            return $aturan;
        }

        // Default: semua nullable
        return AturanOlahNilai::where('sekolah_id', $penugasan->sekolah_id)
            ->where('tahun_ajaran_id', $penugasan->tahun_ajaran_id)
            ->whereNull('mata_pelajaran_id')
            ->whereNull('tingkat_id')
            ->whereNull('kurikulum_id')
            ->first();
    }

    private function dapatkanSiswaAktif(PenugasanMengajar $penugasan): \Illuminate\Support\Collection
    {
        return Siswa::join('rombel_siswa', 'siswa.id', '=', 'rombel_siswa.siswa_id')
            ->where('rombel_siswa.rombel_id', $penugasan->rombel_id)
            ->where('rombel_siswa.tahun_ajaran_id', $penugasan->tahun_ajaran_id)
            ->where('rombel_siswa.tanggal_keluar', null)
            ->where('rombel_siswa.status', 'aktif')
            ->where('siswa.status', 'aktif')
            ->get(['siswa.*']);
    }

    private function hitungNilaiAkhirSiswa(
        PenugasanMengajar $penugasan,
        Siswa $siswa,
        AturanOlahNilai $aturan,
        ?Semester $semester = null
    ): array {
        $query = NilaiSiswa::join('penilaian', 'nilai_siswa.penilaian_id', '=', 'penilaian.id')
            ->where('penilaian.penugasan_mengajar_id', $penugasan->id)
            ->where('nilai_siswa.siswa_id', $siswa->id);

        if ($semester) {
            $query->where('penilaian.semester_id', $semester->id);
        }

        $nilaiList = $query
            ->select('nilai_siswa.*', 'komponen_id', 'bobot')
            ->join('komponen_penilaian', 'penilaian.komponen_id', '=', 'komponen_penilaian.id')
            ->get();

        if ($nilaiList->isEmpty()) {
            return ['nilai' => null, 'skala_butir_id' => null, 'deskripsi' => null, 'is_tuntas' => false];
        }

        switch ($aturan->metode) {
            case 'rata_sumatif':
                $nilai = $nilaiList->avg(fn ($n) => $this->nilaiEfektif($n->nilai, $n->nilai_remedial));
                break;
            case 'nilai_tertinggi':
                $nilai = $nilaiList->max(fn ($n) => $this->nilaiEfektif($n->nilai, $n->nilai_remedial));
                break;
            case 'formula':
                $nilai = $this->evalFormula($aturan->formula ?? [], $nilaiList);
                break;
            case 'rata_berbobot':
            default:
                $totalBobot = $nilaiList->sum(fn ($n) => (float) $n->bobot);
                if ($totalBobot <= 0) {
                    $nilai = $nilaiList->avg(fn ($n) => $this->nilaiEfektif($n->nilai, $n->nilai_remedial));
                } else {
                    $nilai = $nilaiList->sum(fn ($n) => $this->nilaiEfektif($n->nilai, $n->nilai_remedial) * (float) $n->bobot) / $totalBobot;
                }
                break;
        }

        $nilai = $nilai !== null ? $this->bulatkan($nilai, $aturan->pembulatan ?? 'standar') : null;

        $skalaButir = null;
        $isTuntas = false;
        if ($nilai !== null && !blank($aturan->kkm)) {
            $isTuntas = $nilai >= (float) $aturan->kkm;
            if ($aturan->skala_id) {
                $query = \App\Models\SkalaPenilaianButir::where('skala_id', $aturan->skala_id)
                    ->where('nilai_min', '<=', $nilai)
                    ->where('nilai_maks', '>=', $nilai);
                $skalaButir = $query->first();
            }
        }

        return [
            'nilai' => $nilai,
            'skala_butir_id' => $skalaButir?->id,
            'deskripsi' => $skalaButir?->deskripsi_templat ?? null,
            'is_tuntas' => $isTuntas,
        ];
    }

    /** Nilai efektif yang digunakan dalam perhitungan: bila remedial tersedia, gunakan rata-rata nilai asli dan remedial. */
    private function nilaiEfektif(?float $nilai, ?float $nilaiRemedial): ?float
    {
        if ($nilai === null && $nilaiRemedial === null) {
            return null;
        }

        if ($nilai !== null && $nilaiRemedial !== null) {
            return ((float) $nilai + (float) $nilaiRemedial) / 2;
        }

        return (float) ($nilaiRemedial ?? $nilai);
    }

    private function bulatkan(float $nilai, string $metode): float
    {
        return match ($metode) {
            'atas' => ceil($nilai * 100) / 100,
            'bawah' => floor($nilai * 100) / 100,
            default => round($nilai, 2),
        };
    }

    private function evalFormula(array $formula, \Illuminate\Support\Collection $nilaiList): float
    {
        return $nilaiList->avg(fn ($n) => (float) ($n->nilai_remedial ?? $n->nilai));
    }

    /** Pastikan pengguna boleh mengakses data cabang milik penugasan ini. */
    private function pastikanLingkupCabang(PenugasanMengajar $penugasan): void
    {
        $konteks = app(CabangKonteks::class);
        $diizinkan = $konteks->cabangDiizinkan();

        if ($diizinkan !== null && !in_array((int) $penugasan->cabang_id, $diizinkan, true)) {
            throw new OlahNilaiException('Tidak berhak mengelola nilai untuk cabang ini.');
        }
    }

    /** Pastikan pengguna boleh mengakses cabang dari nilai akhir yang terkait. */
    private function pastikanLingkupCabangViaNilaiAkhir(NilaiAkhir $nilaiAkhir): void
    {
        $penugasan = $nilaiAkhir->penugasanMengajar;
        $this->pastikanLingkupCabang($penugasan);
    }
}