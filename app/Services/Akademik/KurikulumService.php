<?php

namespace App\Services\Akademik;

use App\Exceptions\Akademik\KurikulumException;
use App\Models\Kurikulum;
use App\Models\KurikulumSekolah;
use App\Models\MataPelajaran;
use App\Models\Sekolah;
use App\Models\StrukturKurikulum;
use App\Models\TahunAjaran;
use App\Models\Tingkat;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class KurikulumService
{
    /** Terapkan kurikulum ke sekolah untuk tahun ajaran (opsional per tingkat). Idemen. */
    public function terapkan(
        Kurikulum $kurikulum,
        Sekolah $sekolah,
        TahunAjaran $tahunAjaran,
        ?Tingkat $tingkat = null,
        User $pengguna
    ): KurikulumSekolah {
        return DB::transaction(function () use ($kurikulum, $sekolah, $tahunAjaran, $tingkat, $pengguna) {
            // Validasi: kurikulum harus aktif
            if (!$kurikulum->is_aktif) {
                throw new KurikulumException('Kurikulum '.$kurikulum->kode.' tidak aktif.');
            }

            // Validasi: tingkat harus milik jenjang yang sama dengan sekolah (jika ditentukan)
            if ($tingkat && $tingkat->jenjang_id !== $sekolah->jenjang_id) {
                throw new KurikulumException(
                    'Tingkat '.$tingkat->kode.' tidak berlaku untuk jenjang '.$sekolah->jenjang->kode.
                    ' di sekolah '.$sekolah->nama.'.'
                );
            }

            // Idemen: jika sudah ada penerapan yang sama, kembalikan yang ada (tidak duplikat).
            $existing = KurikulumSekolah::query()
                ->where('kurikulum_id', $kurikulum->id)
                ->where('sekolah_id', $sekolah->id)
                ->where('tahun_ajaran_id', $tahunAjaran->id)
                ->where(function ($q) use ($tingkat) {
                    if ($tingkat) {
                        $q->where('tingkat_id', $tingkat->id);
                    } else {
                        $q->whereNull('tingkat_id');
                    }
                })
                ->first();

            if ($existing) {
                return $existing->fresh();
            }

            // Jika penerapan default (tanpa tingkat), pastikan belum ada default lain
            // untuk sekolah + tahun ajaran ini (diindaksir unik parsial di database).
            if (!$tingkat) {
                $sudahDefault = KurikulumSekolah::query()
                    ->where('sekolah_id', $sekolah->id)
                    ->where('tahun_ajaran_id', $tahunAjaran->id)
                    ->whereNull('tingkat_id')
                    ->first();
                if ($sudahDefault) {
                    throw new KurikulumException('sudah diterapkan ke sekolah');
                }
            }

            $penerapan = KurikulumSekolah::create([
                'kurikulum_id' => $kurikulum->id,
                'sekolah_id' => $sekolah->id,
                'cabang_id' => $sekolah->cabang_id,
                'tahun_ajaran_id' => $tahunAjaran->id,
                'tingkat_id' => $tingkat?->id,
                'is_aktif' => true,
            ]);

            return $penerapan->fresh();
        });
    }

    /** Nonaktifkan penerapan kurikulum (tidak menghapus histori). */
    public function nonaktifkan(KurikulumSekolah $penerapan, User $pengguna): KurikulumSekolah
    {
        $penerapan->update(['is_aktif' => false]);

        return $penerapan->fresh();
    }

    /** Salin struktur kurikulum dari sumber ke tujuan. Tidak menimpa baris yang sudah ada di tujuan. */
    public function salinStruktur(Kurikulum $sumber, Kurikulum $tujuan): int
    {
        if ($sumber->id === $tujuan->id) {
            throw new KurikulumException('Kurikulum sumber dan tujuan harus berbeda.');
        }

        $baris = StrukturKurikulum::where('kurikulum_id', $sumber->id)->get();

        $disalin = 0;
        foreach ($baris as $struktur) {
            $ada = StrukturKurikulum::query()
                ->where('kurikulum_id', $tujuan->id)
                ->where('tingkat_id', $struktur->tingkat_id)
                ->where('mata_pelajaran_id', $struktur->mata_pelajaran_id)
                ->exists();

            if (!$ada) {
                StrukturKurikulum::create([
                    'kurikulum_id' => $tujuan->id,
                    'tingkat_id' => $struktur->tingkat_id,
                    'mata_pelajaran_id' => $struktur->mata_pelajaran_id,
                    'jam_per_minggu' => $struktur->jam_per_minggu,
                    'is_wajib' => $struktur->is_wajib,
                    'kkm' => $struktur->kkm,
                ]);
                $disalin++;
            }
        }

        return $disalin;
    }

    /** Validasi mata pelajaran cocok dengan jenjang tingkat. */
    public function validasiJenjang(Tingkat $tingkat, MataPelajaran $mataPelajaran): void
    {
        if ($mataPelajaran->jenjang_id && $mataPelajaran->jenjang_id !== $tingkat->jenjang_id) {
            throw new KurikulumException(
                'Mata pelajaran '.$mataPelajaran->kode.' (jenjang '.$mataPelajaran->jenjang->kode.') '.
                'tidak cocok dengan tingkat '.$tingkat->kode.' (jenjang '.$tingkat->jenjang->kode.').'
            );
        }
    }
}
