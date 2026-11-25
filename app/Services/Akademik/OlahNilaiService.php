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
use Illuminate\Support\Facades\DB;

class OlahNilaiService
{
    /** Hitung nilai akhir untuk semua siswa dalam satu penugasan mengajar + semester. */
    public function hitungNilaiAkhir(
        PenugasanMengajar $penugasan,
        Semester $semester,
        ?User $pengguna = null
    ): int {
        $aturan = $this->cariAturanOlah($penugasan);

        if (!$aturan) {
            throw new OlahNilaiException('Aturan olah nilai tidak ditemukan untuk mata pelajaran ini.');
        }

        $siswaList = $this->dapatkanSiswaAktif($penugasan);

        $dihitung = 0;

        DB::transaction(function () use ($penugasan, $semester, $aturan, $siswaList, &$dihitung) {
            foreach ($siswaList as $siswa) {
                $nilaiAkhir = $this->hitungNilaiAkhirSiswa($penugasan, $siswa, $aturan);

                NilaiAkhir::updateOrCreate(
                    [
                        'penugasan_mengajar_id' => $penugasan->id,
                        'siswa_id' => $siswa->id,
                    ],
                    [
                        'semester_id' => $semester->id,
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
        $final = NilaiAkhir::where('penugasan_mengajar_id', $penugasan->id)
            ->where('semester_id', $semester->id)
            ->where('status', 'draft')
            ->update(['status' => 'final']);

        return (int) $final;
    }

    /** Koreksi satu nilai akhir (hanya jika status draft). Catat siapa yang memperbaiki. */
    public function koreksiNilaiAkhir(
        NilaiAkhir $nilaiAkhir,
        ?float $nilaiBaru = null,
        ?string $deskripsiBaru = null,
        ?bool $isTuntas = null,
        User $diperbaruiOleh
    ): NilaiAkhir {
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
        AturanOlahNilai $aturan
    ): array {
        $nilaiList = NilaiSiswa::join('penilaian', 'nilai_siswa.penilaian_id', '=', 'penilaian.id')
            ->where('penilaian.penugasan_mengajar_id', $penugasan->id)
            ->where('nilai_siswa.siswa_id', $siswa->id)
            ->where('penilaian.status', 'final')
            ->select('nilai_siswa.*', 'komponen_id', 'bobot')
            ->join('komponen_penilaian', 'penilaian.komponen_id', '=', 'komponen_penilaian.id')
            ->get();

        if ($nilaiList->isEmpty()) {
            return ['nilai' => null, 'skala_butir_id' => null, 'deskripsi' => null, 'is_tuntas' => false];
        }

        switch ($aturan->metode) {
            case 'rata_sumatif':
                $nilai = $nilaiList->avg(fn ($n) => (float) ($n->nilai_remedial ?? $n->nilai));
                break;
            case 'nilai_tertinggi':
                $nilai = $nilaiList->max(fn ($n) => (float) ($n->nilai_remedial ?? $n->nilai));
                break;
            case 'formula':
                $nilai = $this->evalFormula($aturan->formula ?? [], $nilaiList);
                break;
            case 'rata_berbobot':
            default:
                $totalBobot = $nilaiList->sum(fn ($n) => (float) $n->komponen->bobot);
                if ($totalBobot <= 0) {
                    $nilai = $nilaiList->avg(fn ($n) => (float) ($n->nilai_remedial ?? $n->nilai));
                } else {
                    $nilai = $nilaiList->sum(fn ($n) => (float) ($n->nilai_remedial ?? $n->nilai) * (float) $n->komponen->bobot) / $totalBobot;
                }
                break;
        }

        $nilai = $this->bulatkan($nilai, $aturan->pembulatan ?? 'standar');

        $skalaButir = null;
        $isTuntas = false;
        if ($aturan->skala_id && $nilai !== null) {
            $query = \App\Models\SkalaPenilaianButir::where('skala_id', $aturan->skala_id);
            if (!blank($aturan->kkm)) {
                $query->where('nilai_min', '<=', $nilai)->where('nilai_maks', '>=', $nilai);
            }
            $skalaButir = $query->first();
            $isTuntas = $nilai >= ($aturan->kkm ?? 0);
        }

        return [
            'nilai' => round($nilai, 2),
            'skala_butir_id' => $skalaButir?->id,
            'deskripsi' => $skalaButir?->deskripsi_templat ?? null,
            'is_tuntas' => $isTuntas,
        ];
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
}