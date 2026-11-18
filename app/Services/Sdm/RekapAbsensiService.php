<?php

namespace App\Services\Sdm;

use App\Exceptions\Sdm\SdmException;
use App\Models\AbsensiPegawai;
use Illuminate\Support\Collection;

/**
 * Rekap absensi bulanan per pegawai dan ekspor CSV.
 * Query memakai global scope BerLingkupCabang, jadi pengguna hanya melihat cabang yang berhak diaksesnya.
 */
class RekapAbsensiService
{
    public const STATUS = ['hadir', 'terlambat', 'izin', 'sakit', 'cuti', 'dinas', 'alpa'];

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function rekapBulanan(int $tahun, int $bulan, ?int $cabangId = null): Collection
    {
        if ($bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > 2100) {
            throw new SdmException('Bulan atau tahun rekap tidak valid.');
        }

        $mulai = sprintf('%04d-%02d-01', $tahun, $bulan);
        $akhir = date('Y-m-t', strtotime($mulai));

        $baris = AbsensiPegawai::query()
            ->with(['pegawai.orang', 'cabang'])
            ->whereBetween('tanggal', [$mulai, $akhir])
            ->when($cabangId !== null, fn ($q) => $q->where('cabang_id', $cabangId))
            ->orderBy('cabang_id')
            ->orderBy('pegawai_id')
            ->get();

        return $baris->groupBy(fn (AbsensiPegawai $a) => $a->cabang_id.'-'.$a->pegawai_id)
            ->map(function (Collection $kelompok) {
                $pertama = $kelompok->first();
                $hitung = $kelompok->countBy('status');

                $hasil = [
                    'cabang' => $pertama->cabang?->nama,
                    'nip' => $pertama->pegawai?->nip_yayasan,
                    'nama' => $pertama->pegawai?->orang?->nama_lengkap,
                ];
                foreach (self::STATUS as $status) {
                    $hasil[$status] = (int) ($hitung[$status] ?? 0);
                }
                $hasil['terlambat_menit'] = (int) $kelompok->sum('terlambat_menit');
                $hasil['pulang_cepat_menit'] = (int) $kelompok->sum('pulang_cepat_menit');

                return $hasil;
            })
            ->values();
    }

    /**
     * Isi CSV rekap bulanan (UTF-8 dengan BOM agar terbaca di Excel).
     */
    public function csv(int $tahun, int $bulan, ?int $cabangId = null): string
    {
        $judul = ['Cabang', 'NIP', 'Nama', 'Hadir', 'Terlambat', 'Izin', 'Sakit', 'Cuti', 'Dinas', 'Alpa', 'Menit terlambat', 'Menit pulang cepat'];

        $buffer = fopen('php://temp', 'r+');
        fputcsv($buffer, $judul);

        foreach ($this->rekapBulanan($tahun, $bulan, $cabangId) as $r) {
            fputcsv($buffer, array_map([$this, 'aman'], [
                $r['cabang'], $r['nip'], $r['nama'],
                $r['hadir'], $r['terlambat'], $r['izin'], $r['sakit'], $r['cuti'], $r['dinas'], $r['alpa'],
                $r['terlambat_menit'], $r['pulang_cepat_menit'],
            ]));
        }

        rewind($buffer);
        $isi = stream_get_contents($buffer);
        fclose($buffer);

        return "\xEF\xBB\xBF".$isi;
    }

    /** Netralkan sel yang diawali karakter rumus agar tidak dieksekusi spreadsheet (CSV injection). */
    private function aman(mixed $nilai): mixed
    {
        if (is_string($nilai) && $nilai !== '' && in_array($nilai[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$nilai;
        }

        return $nilai;
    }
}
