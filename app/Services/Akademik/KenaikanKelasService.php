<?php

namespace App\Services\Akademik;

use App\Events\KenaikanKelasDitetapkan;
use App\Exceptions\Akademik\KenaikanKelasException;
use App\Models\KeputusanKenaikan;
use App\Models\Rombel;
use App\Models\RombelSiswa;
use App\Models\Sekolah;
use App\Models\Siswa;
use App\Models\SiswaSekolah;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Support\CabangKonteks;
use Illuminate\Support\Facades\DB;

class KenaikanKelasService
{
    /** Tetapkan keputusan kenaikan kelas per siswa untuk satu tahun ajaran. Idemen. */
    public function tetapkanKeputusan(
        Siswa $siswa,
        Rombel $rombel,
        TahunAjaran $tahunAjaran,
        string $keputusan,
        ?Rombel $rombelTujuan = null,
        ?string $catatan = null,
        User $ditetapkanOleh
    ): KeputusanKenaikan {
        $this->pastikanLingkupCabang($rombel);

        if (! in_array($keputusan, ['naik', 'tinggal', 'lulus', 'pindah'], true)) {
            throw new KenaikanKelasException('Keputusan harus naik, tinggal, lulus, atau pindah.');
        }

        return DB::transaction(function () use ($siswa, $rombel, $tahunAjaran, $keputusan, $rombelTujuan, $catatan, $ditetapkanOleh) {
            $existing = KeputusanKenaikan::query()
                ->where('siswa_id', $siswa->id)
                ->where('tahun_ajaran_id', $tahunAjaran->id)
                ->first();

            if ($existing) {
                return $existing->fresh();
            }

            $keputusanModel = KeputusanKenaikan::create([
                'siswa_id' => $siswa->id,
                'rombel_id' => $rombel->id,
                'cabang_id' => $rombel->cabang_id,
                'tahun_ajaran_id' => $tahunAjaran->id,
                'rombel_tujuan_id' => $rombelTujuan?->id,
                'keputusan' => $keputusan,
                'catatan' => $catatan,
                'ditetapkan_oleh' => $ditetapkanOleh->id,
                'tanggal' => now()->toDateString(),
            ]);

            DB::afterCommit(function () use ($keputusanModel) {
                event(new KenaikanKelasDitetapkan($keputusanModel->fresh()));
            });

            return $keputusanModel->fresh();
        });
    }

    /** Perbarui keputusan yang sudah ada (belum diproses ke rombel tahun berikutnya). */
    public function ubahKeputusan(
        KeputusanKenaikan $keputusan,
        ?string $keputusanBaru = null,
        ?Rombel $rombelTujuan = null,
        ?string $catatan = null,
        User $diperbaruiOleh
    ): KeputusanKenaikan {
        $this->pastikanLingkupCabangViaKeputusan($keputusan);

        return DB::transaction(function () use ($keputusan, $keputusanBaru, $rombelTujuan, $catatan, $diperbaruiOleh) {
            $data = [];
            if ($keputusanBaru !== null) {
                if (! in_array($keputusanBaru, ['naik', 'tinggal', 'lulus', 'pindah'], true)) {
                    throw new KenaikanKelasException('Keputusan harus naik, tinggal, lulus, atau pindah.');
                }
                $data['keputusan'] = $keputusanBaru;
            }
            if ($rombelTujuan !== null) {
                $data['rombel_tujuan_id'] = $rombelTujuan->id;
            }
            if ($catatan !== null) {
                $data['catatan'] = $catatan;
            }

            if (! empty($data)) {
                $keputusan->update($data);
            }

            return $keputusan->fresh();
        });
    }

    /** Form SiswaSekolah dan RombelSiswa untuk tahun ajaran berikutnya dari keputusan yang ditetapkan. Idemen. */
    public function bentukRombelTahunBerikutnya(
        KeputusanKenaikan $keputusan,
        Rombel $rombelTujuan,
        TahunAjaran $tahunAjaranBerikutnya,
        User $dibentukOleh
    ): void {
        $this->pastikanLingkupCabangViaKeputusan($keputusan);

        if ($keputusan->keputusan !== 'naik') {
            throw new KenaikanKelasException('Hanya siswa dengan keputusan naik yang dapat dipindah ke rombel tujuan.');
        }

        DB::transaction(function () use ($keputusan, $rombelTujuan, $tahunAjaranBerikutnya, $dibentukOleh) {
            SiswaSekolah::updateOrCreate(
                [
                    'siswa_id' => $keputusan->siswa_id,
                    'tahun_ajaran_id' => $tahunAjaranBerikutnya->id,
                ],
                [
                    'cabang_id' => $rombelTujuan->cabang_id,
                    'tanggal_masuk' => now()->toDateString(),
                    'status' => 'aktif',
                ]
            );

            $sudahAda = RombelSiswa::query()
                ->where('siswa_id', $keputusan->siswa_id)
                ->where('rombel_id', $rombelTujuan->id)
                ->where('tahun_ajaran_id', $tahunAjaranBerikutnya->id)
                ->exists();

            if (! $sudahAda) {
                RombelSiswa::create([
                    'siswa_id' => $keputusan->siswa_id,
                    'rombel_id' => $rombelTujuan->id,
                    'cabang_id' => $rombelTujuan->cabang_id,
                    'tahun_ajaran_id' => $tahunAjaranBerikutnya->id,
                    'status' => 'aktif',
                ]);
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /* Private helpers                                                    */
    /* ------------------------------------------------------------------ */

    private function pastikanLingkupCabang(Rombel $rombel): void
    {
        $konteks = app(CabangKonteks::class);
        $diizinkan = $konteks->cabangDiizinkan();

        if ($diizinkan !== null && ! in_array((int) $rombel->cabang_id, $diizinkan, true)) {
            throw new KenaikanKelasException('Tidak berhak mengelola kenaikan kelas untuk cabang ini.');
        }
    }

    private function pastikanLingkupCabangViaKeputusan(KeputusanKenaikan $keputusan): void
    {
        $rombel = $keputusan->rombel;
        $this->pastikanLingkupCabang($rombel);
    }
}