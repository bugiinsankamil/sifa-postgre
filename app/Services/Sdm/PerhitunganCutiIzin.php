<?php

namespace App\Services\Sdm;

use App\Models\Cabang;
use App\Models\HariLibur;
use App\Models\Pegawai;
use App\Models\ShiftKerja;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * Menghitung hari efektif cuti/izin.
 * Aturan bisnis: hari kerja berdasarkan shift pegawai + kalender libur per cabang.
 * Setengah hari didukung (sesi pagi/siang/penuh). Libur dilewati dari hitungan.
 */
class PerhitunganCutiIzin
{
    /**
     * Hitung rincian hari efektif untuk rentang tanggal.
     *
     * @param  array<int, string>  $sesiPerTanggal  Map tanggal => 'penuh'|'pagi'|'siang'. Default penuh.
     * @return array<string, mixed>
     */
    public function hitung(Pegawai $pegawai, string $tanggalMulai, string $tanggalSelesai, ?Cabang $cabang = null, array $sesiPerTanggal = []): array
    {
        $mulai = Carbon::parse($tanggalMulai);
        $selesai = Carbon::parse($tanggalSelesai);

        $absensiSvc = app(AbsensiService::class);
        $daftarTanggal = CarbonPeriod::create($mulai, '1 day', $selesai)->toArray();

        $rincian = [];
        $jumlahHari = '0.0';
        $jumlahSetengahHari = 0;
        $perTahun = [];

        foreach ($daftarTanggal as $tanggal) {
            $tanggalStr = $tanggal->toDateString();
            $sesi = $sesiPerTanggal[$tanggalStr] ?? 'penuh';
            $nilai = $this->nilaiSesi($sesi);

            $penugasan = $absensiSvc->penugasanAktifPada($pegawai, $tanggalStr, false);
            if ($penugasan === null) {
                $rincian[] = [
                    'tanggal' => $tanggalStr,
                    'tahun' => (int) $tanggal->format('Y'),
                    'nilai' => $nilai,
                    'sesi' => $sesi,
                    'status' => 'tidak_aktif',
                ];
                continue;
            }

            $shift = $absensiSvc->shiftPada($pegawai, $tanggalStr);
            $hariDalamMinggu = (int) $tanggal->format('N');
            $hariKerja = $shift !== null ? ($shift->hari_kerja ?? []) : [];

            if ($shift !== null && ! in_array($hariDalamMinggu, $hariKerja, true)) {
                $rincian[] = [
                    'tanggal' => $tanggalStr,
                    'tahun' => (int) $tanggal->format('Y'),
                    'nilai' => $nilai,
                    'sesi' => $sesi,
                    'status' => 'libur',
                ];
                continue;
            }

            $cabangCek = $cabang ?? $penugasan->cabang;
            if ($cabangCek !== null && $this->adalahHariLibur($cabangCek->id, $tanggalStr)) {
                $rincian[] = [
                    'tanggal' => $tanggalStr,
                    'tahun' => (int) $tanggal->format('Y'),
                    'nilai' => $nilai,
                    'sesi' => $sesi,
                    'status' => 'libur',
                ];
                continue;
            }

            $tahun = (int) $tanggal->format('Y');
            $perTahun[$tahun] = bcadd($perTahun[$tahun] ?? '0.0', $nilai, 1);
            $jumlahHari = bcadd($jumlahHari, $nilai, 1);
            if ($nilai === '0.5') {
                $jumlahSetengahHari++;
            }

            $rincian[] = [
                'tanggal' => $tanggalStr,
                'tahun' => $tahun,
                'nilai' => $nilai,
                'sesi' => $sesi,
                'status' => 'terhitung',
            ];
        }

        return [
            'jumlah_hari' => $jumlahHari,
            'jumlah_setengah_hari' => $jumlahSetengahHari,
            'per_tahun' => $perTahun,
            'rincian' => $rincian,
        ];
    }

    public function nilaiSesi(string $sesi): string
    {
        return match ($sesi) {
            'pagi', 'siang' => '0.5',
            default => '1.0',
        };
    }

    private function adalahHariLibur(int $cabangId, string $tanggal): bool
    {
        return HariLibur::query()
            ->where(fn ($q) => $q->whereNull('cabang_id')->orWhere('cabang_id', $cabangId))
            ->where('tanggal', $tanggal)
            ->exists();
    }
}
