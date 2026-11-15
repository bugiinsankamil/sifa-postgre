<?php

namespace App\Services\Sdm;

use App\Exceptions\Sdm\SdmException;
use App\Models\AbsensiPegawai;
use App\Models\KpiIndikator;
use App\Models\KpiPenilaian;
use App\Models\KpiPenilaianDetail;
use App\Models\KpiPeriode;
use App\Models\KpiTemplat;
use App\Models\Pegawai;
use Illuminate\Support\Facades\DB;

/**
 * Penilaian kinerja berbasis KPI.
 * - Buat penilaian dari templat (otomatis isi detail indikator).
 * - Isi realisasi manual atau otomatis (dari absensi).
 * - Hitung skor akhir berdasarkan bobot dan capaian.
 */
class KpiService
{
    /**
     * Buat penilaian KPI dari templat.
     */
    public function buatPenilaian(KpiPeriode $periode, Pegawai $pegawai, Pegawai $penilai, KpiTemplat $templat): KpiPenilaian
    {
        // Cek duplikasi
        $ada = KpiPenilaian::query()
            ->where('periode_id', $periode->id)
            ->where('pegawai_id', $pegawai->id)
            ->exists();
        if ($ada) {
            throw new SdmException('Penilaian KPI untuk pegawai ini pada periode ini sudah ada.');
        }

        return DB::transaction(function () use ($periode, $pegawai, $penilai, $templat) {
            $penilaian = KpiPenilaian::create([
                'periode_id' => $periode->id,
                'pegawai_id' => $pegawai->id,
                'penilai_id' => $penilai->id,
                'templat_id' => $templat->id,
                'status' => 'draft',
            ]);

            // Salin indikator dari templat ke detail penilaian
            foreach ($templat->indikator as $ti) {
                KpiPenilaianDetail::create([
                    'penilaian_id' => $penilaian->id,
                    'indikator_id' => $ti->indikator_id,
                    'bobot' => $ti->bobot,
                    'target' => $ti->target,
                ]);
            }

            return $penilaian;
        });
    }

    /**
     * Isi realisasi manual untuk satu indikator.
     */
    public function isiRealisasi(KpiPenilaianDetail $detail, float $realisasi, ?string $catatan = null): KpiPenilaianDetail
    {
        $detail->update([
            'realisasi' => $realisasi,
            'catatan' => $catatan,
        ]);

        return $detail;
    }

    /**
     * Isi realisasi otomatis dari absensi (indikator kehadiran).
     */
    public function isiRealisasiKehadiran(KpiPenilaianDetail $detail): KpiPenilaianDetail
    {
        $penilaian = $detail->penilaian;
        $periode = $penilaian->periode;
        $pegawai = $penilaian->pegawai;

        // Hitung kehadiran dari absensi_pegawai
        $totalHari = $periode->tanggal_mulai->diffInDays($periode->tanggal_selesai) + 1;
        $hadir = AbsensiPegawai::query()
            ->where('pegawai_id', $pegawai->id)
            ->whereBetween('tanggal', [$periode->tanggal_mulai, $periode->tanggal_selesai])
            ->whereIn('status', ['hadir', 'terlambat', 'izin', 'sakit', 'cuti', 'dinas'])
            ->count();

        $persen = $totalHari > 0 ? round(($hadir / $totalHari) * 100, 2) : 0;

        $detail->update(['realisasi' => $persen]);

        return $detail;
    }

    /**
     * Hitung skor untuk satu detail berdasarkan capaian.
     */
    public function hitungSkor(KpiPenilaianDetail $detail): KpiPenilaianDetail
    {
        if ($detail->realisasi === null) {
            throw new SdmException('Realisasi belum diisi.');
        }

        $indikator = $detail->indikator;
        $target = (float) $detail->target;
        $realisasi = (float) $detail->realisasi;

        if ($indikator->arah === 'semakin_tinggi') {
            $skor = $target > 0 ? min(100, round(($realisasi / $target) * 100, 2)) : 0;
        } else {
            // semakin_rendah
            $skor = $realisasi > 0 ? min(100, round(($target / $realisasi) * 100, 2)) : 100;
        }

        $detail->update(['skor' => $skor]);

        return $detail;
    }

    /**
     * Finalisasi penilaian: hitung skor akhir dan predikat.
     */
    public function finalisasi(KpiPenilaian $penilaian): KpiPenilaian
    {
        if ($penilaian->status !== 'draft') {
            throw new SdmException('Penilaian sudah difinalisasi.');
        }

        return DB::transaction(function () use ($penilaian) {
            $totalSkor = 0;
            $totalBobot = 0;

            foreach ($penilaian->detail as $detail) {
                if ($detail->skor === null) {
                    $this->hitungSkor($detail);
                }
                $totalSkor += ($detail->skor * $detail->bobot);
                $totalBobot += $detail->bobot;
            }

            $skorAkhir = $totalBobot > 0 ? round($totalSkor / $totalBobot, 2) : 0;

            $predikat = match (true) {
                $skorAkhir >= 90 => 'A',
                $skorAkhir >= 75 => 'B',
                $skorAkhir >= 60 => 'C',
                default => 'D',
            };

            $penilaian->update([
                'skor_akhir' => $skorAkhir,
                'predikat' => $predikat,
                'status' => 'dinilai',
            ]);

            return $penilaian;
        });
    }

    /**
     * Setujui penilaian KPI.
     */
    public function setujui(KpiPenilaian $penilaian): KpiPenilaian
    {
        if ($penilaian->status !== 'dinilai') {
            throw new SdmException('Penilaian harus difinalisasi terlebih dahulu.');
        }

        $penilaian->update(['status' => 'disetujui']);

        return $penilaian;
    }
}
