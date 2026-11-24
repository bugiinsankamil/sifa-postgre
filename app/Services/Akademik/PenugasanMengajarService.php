<?php

namespace App\Services\Akademik;

use App\Exceptions\Akademik\JadwalException;
use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\PenugasanMengajar;
use App\Models\Rombel;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PenugasanMengajarService
{
    /** Terbuat/validasi penugasan mengajar untuk rombel, mata pelajaran, dan guru. */
    public function tugaskan(
        Rombel $rombel,
        MataPelajaran $mataPelajaran,
        Pegawai $pegawai,
        TahunAjaran $tahunAjaran,
        Semester $semester,
        string $peran = 'utama',
        ?User $pengguna = null
    ): PenugasanMengajar {
        return DB::transaction(function () use ($rombel, $mataPelajaran, $pegawai, $tahunAjaran, $semester, $peran, $pengguna) {
            // Validasi: guru sudah ada penugasan rombel lain di semester yang sama
            $sudahAda = PenugasanMengajar::where('rombel_id', $rombel->id)
                ->where('mata_pelajaran_id', $mataPelajaran->id)
                ->where('semester_id', $semester->id)
                ->where('pegawai_id', $pegawai->id)
                ->where('status', 'aktif')
                ->exists();

            if ($sudahAda) {
                throw new JadwalException(
                    'Guru '.$pegawai->nama.' sudah memiliki penugasan '.$mataPelajaran->nama.' di rombel '.$rombel->nama.' semester '.$semester->nama.'.'
                );
            }

            // Validasi: pastikan rombel terbelong ke sekolah yang sama dengan guru
            // (asumsi: rombel milik sekolah, pegawai terlanjur terikat)

            $penugasan = PenugasanMengajar::create([
                'sekolah_id' => $rombel->sekolah_id,
                'tahun_ajaran_id' => $tahunAjaran->id,
                'semester_id' => $semester->id,
                'rombel_id' => $rombel->id,
                'mata_pelajaran_id' => $mataPelajaran->id,
                'pegawai_id' => $pegawai->id,
                'peran' => $peran,
                'cabang_id' => $rombel->cabang_id,
                'status' => 'aktif',
            ]);

            return $penugasan->fresh();
        });
    }

    /** Cek apakah ada bentrok jadwal untuk penugasan ini. */
    public function cekBentrok(PenugasanMengajar $penugasan, JadwalPelajaran $kecuali = null): array
    {
        $query = JadwalPelajaran::where('penugasan_mengajar_id', $penugasan->id);

        // Jika ada pengecualian (sementara mengedit), lewati baris tersebut
        if ($kecuali) {
            $query->where('id', '!=', $kecuali->id);
        }

        $bentrok = [];

        // Cek bentrok berdasarkan jam_pelajaran
        $jpQuery = $query->with('jamPelajaran');
        $jams = $jpQuery->get();

        // Grup berdasarkan (hari, urutan)
        $groups = $jams->groupBy(function ($j) {
            return [
                'hari' => $j->jam_pelajaran->hari,
                'urutan' => $j->jam_pelajaran->urutan,
            ];
        });

        foreach ($groups as $key => $items) {
            if (count($items) > 1) {
                $bentrok[] = [
                    'kunci' => "Hari {$key['hari']}, Urutan {$key['urutan']}",
                    'detail' => $items->keyBy('id')->toArray(),
                ];
            }
        }

        // Cek bentrok berdasarkan ruang (jika ruang dialokasikan)
        $ruangQuery = $query->whereNotNull('ruang_id')->whereNotNull('ruang');
        $ruangs = $ruangQuery->get();

        foreach ($ruangs as $j) {
            $bentrok[] = [
                'kunci' => "Ruang {$j->ruang->nama} ({$j->jam_pelajaran->hari}, {$j->jam_pelajaran->urutan})",
                'detail' => $j->toArray(),
            ];
        }

        return $bentrok;
    }

    /** Dapatkan semua penugasan aktif untuk rombel tertentu. */
    public function dapatkanAktifByRombel(Rombel $rombel, TahunAjaran $tahunAjaran, Semester $semester): \Illuminate\Support\Collection
    {
        return PenugasanMengajar::where('rombel_id', $rombel->id)
            ->where('tahun_ajaran_id', $tahunAjaran->id)
            ->where('semester_id', $semester->id)
            ->where('status', 'aktif')
            ->get();
    }
}