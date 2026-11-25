<?php

namespace App\Services\Akademik;

use App\Exceptions\Akademik\PresensiSiswaException;
use App\Models\JurnalMengajar;
use App\Models\PenugasanMengajar;
use App\Models\Pegawai;
use App\Models\PresensiSiswa;
use App\Models\Rombel;
use App\Models\RombelSiswa;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Presensi siswa per rombel/tanggal.
 * Dukung dua mode: harian TK/SD (jurnal_mengajar_id NULL) dan per jam SMP.
 * Guru hanya boleh mencatat rombel yang ia ajarkan (penugasan utama) atau wali kelasnya.
 */
class PresensiSiswaService
{
    /**
     * Catat presensi massal. Idemen via updateOrCreate pada (siswa_id, tanggal, jurnal_mengajar_id).
     *
     * @param  array<int, array>  $data  ['siswa_id' => int, 'status' => string, 'keterangan' => ?string]
     * @return int Jumlah baris presensi yang ditulis.
     */
    public function catatPresensi(
        Rombel $rombel,
        string $tanggal,
        array $data,
        ?JurnalMengajar $jurnal = null,
        User $dicatatOleh
    ): int {
        $this->pastikanBolehMencatat($rombel, $dicatatOleh);

        $disimpan = 0;

        DB::transaction(function () use ($rombel, $tanggal, $data, $jurnal, $dicatatOleh, &$disimpan) {
            foreach ($data as $baris) {
                $siswaId = $baris['siswa_id'] ?? null;
                if (blank($siswaId)) {
                    continue;
                }

                $anggota = RombelSiswa::query()
                    ->where('rombel_id', $rombel->id)
                    ->where('siswa_id', $siswaId)
                    ->whereNull('tanggal_keluar')
                    ->where('status', 'aktif')
                    ->first();

                if ($anggota === null) {
                    throw new PresensiSiswaException('Siswa ID '.$siswaId.' bukan anggota aktif rombel '.$rombel->nama);
                }

                $duplikat = $jurnal === null
                    ? PresensiSiswa::query()
                        ->where('siswa_id', $siswaId)
                        ->where('tanggal', $tanggal)
                        ->first()
                    : PresensiSiswa::query()
                        ->where('siswa_id', $siswaId)
                        ->where('tanggal', $tanggal)
                        ->where('jurnal_mengajar_id', $jurnal->id)
                        ->first();

                if ($duplikat !== null) {
                    throw new PresensiSiswaException('Presensi siswa ID '.$siswaId.' untuk tanggal '.$tanggal.' sudah dicatat.');
                }

                PresensiSiswa::query()->create([
                    'siswa_id' => $siswaId,
                    'tanggal' => $tanggal,
                    'jurnal_mengajar_id' => $jurnal?->id,
                    'cabang_id' => $rombel->cabang_id,
                    'rombel_id' => $rombel->id,
                    'status' => $baris['status'] ?? 'hadir',
                    'keterangan' => $baris['keterangan'] ?? null,
                    'dicatat_oleh' => $dicatatOleh->id,
                ]);

                $disimpan++;
            }
        });

        return $disimpan;
    }

    /** Koreksi satu baris presensi (status/keterangan) dengan catat siapa yang memperbaiki. */
    public function koreksiPresensi(PresensiSiswa $presensi, array $data, User $dicatatOleh): PresensiSiswa
    {
        $presensi->update(array_filter($data, fn ($key) => in_array($key, ['status', 'keterangan'], true), ARRAY_FILTER_USE_KEY));
        $presensi->update(['dicatat_oleh' => $dicatatOleh->id]);

        return $presensi->fresh();
    }

    /**
     * Rekap presensi per rombel dalam rentang tanggal.
     * @return array<string, array{total:int, hadir:int, sakit:int, izin:int, terlambat:int, alpa:int}>
     */
    public function rekapPresensi(Rombel $rombel, string $tanggalMulai, string $tanggalSelesai): array
    {
        $kumpulan = PresensiSiswa::query()
            ->where('rombel_id', $rombel->id)
            ->whereBetween('tanggal', [$tanggalMulai, $tanggalSelesai])
            ->with('siswa.orang')
            ->get()
            ->groupBy(fn ($p) => $p->siswa->orang->nama_lengkap);

        return $kumpulan->map(function ($baris) {
            return [
                'total' => $baris->count(),
                'hadir' => $baris->where('status', 'hadir')->count(),
                'sakit' => $baris->where('status', 'sakit')->count(),
                'izin' => $baris->where('status', 'izin')->count(),
                'terlambat' => $baris->where('status', 'terlambat')->count(),
                'alpa' => $baris->where('status', 'alpa')->count(),
            ];
        })->all();
    }

    /**
     * Pastikan pengguna boleh mencatat presensi rombel ini:
     * wali kelas rombel, atau guru utama (peran 'utama') yang mengajar rombel.
     */
    private function pastikanBolehMencatat(Rombel $rombel, User $pengguna): void
    {
        if ($pengguna->isSysadmin()) {
            return;
        }

        $pegawai = $this->pegawaiDariPengguna($pengguna);

        if ($pegawai !== null && $rombel->wali_kelas_id === $pegawai->id) {
            return;
        }

        $mengajar = PenugasanMengajar::query()
            ->where('pegawai_id', $pegawai?->id)
            ->where('rombel_id', $rombel->id)
            ->where('peran', 'utama')
            ->where('status', 'aktif')
            ->exists();

        if (! $mengajar) {
            throw new PresensiSiswaException('Anda bukan wali kelas atau guru utama rombel ini.');
        }
    }

    private function pegawaiDariPengguna(User $pengguna): ?Pegawai
    {
        if (blank($pengguna->orang_id)) {
            return null;
        }

        return Pegawai::query()->where('orang_id', $pengguna->orang_id)->first();
    }
}
