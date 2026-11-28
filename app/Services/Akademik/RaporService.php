<?php

namespace App\Services\Akademik;

use App\Exceptions\Akademik\RaporException;
use App\Models\NilaiAkhir;
use App\Models\PenugasanMengajar;
use App\Models\PresensiSiswa;
use App\Models\Rapor;
use App\Models\RaporButir;
use App\Models\RaporTemplat;
use App\Models\RaporTemplatBagian;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use App\Support\CabangKonteks;
use Illuminate\Support\Facades\DB;

class RaporService
{
    /** Buat rapor draft untuk satu siswa per semester. Idemen. */
    public function buatDraft(
        Siswa $siswa,
        Semester $semester,
        ?RaporTemplat $templat = null,
        User $dibuatOleh
    ): Rapor {
        $this->pastikanLingkupCabang($siswa);

        return DB::transaction(function () use ($siswa, $semester, $templat, $dibuatOleh) {
            $existing = Rapor::query()
                ->where('siswa_id', $siswa->id)
                ->where('semester_id', $semester->id)
                ->first();

            if ($existing) {
                return $existing->fresh();
            }

            $templat = $templat ?? $this->pilihTemplat($siswa, $semester);

            if (! $templat) {
                throw new RaporException('Tidak ada template rapor yang cocok untuk siswa dan semester ini.');
            }

            $rapor = Rapor::create([
                'siswa_id' => $siswa->id,
                'rombel_id' => $siswa->rombelSiswaAktif->first()?->rombel_id ?? null,
                'semester_id' => $semester->id,
                'cabang_id' => $siswa->rombelSiswaAktif->first()?->cabang_id,
                'templat_id' => $templat->id,
                'status' => 'draft',
                'ditandatangani_oleh' => null,
                'tanggal_terbit' => null,
                'berkas_path' => null,
                'data_tambahan' => null,
            ]);

            return $rapor->fresh();
        });
    }

    /** Terbitkan rapor. Setelah terbit, butir tidak dapat diubah ulang. */
    public function terbitkan(Rapor $rapor, User $ditandatanganiOleh): Rapor
    {
        $this->pastikanLingkupCabangViaRapor($rapor);

        return DB::transaction(function () use ($rapor, $ditandatanganiOleh) {
            if ($rapor->status === 'terbit') {
                throw new RaporException('Rapor sudah diterbitkan. Tidak dapat diubah ulang.');
            }

            if ($rapor->status === 'diverifikasi' && ! $rapor->butir()->exists()) {
                throw new RaporException('Rapor belum memiliki butir. Isi butir terlebih dahulu sebelum terbit.');
            }

            $rapor->update([
                'status' => 'terbit',
                'ditandatangani_oleh' => $ditandatanganiOleh->id,
                'tanggal_terbit' => now()->toDateString(),
            ]);

            return $rapor->fresh();
        });
    }

    /** Verifikasi rapor (hanya dari draft ke diverifikasi). */
    public function verifikasi(Rapor $rapor, User $memverifikasi): Rapor
    {
        $this->pastikanLingkupCabangViaRapor($rapor);

        return DB::transaction(function () use ($rapor, $memverifikasi) {
            if ($rapor->status !== 'draft') {
                throw new RaporException('Hanya rapor draft yang dapat diverifikasi.');
            }

            $rapor->update(['status' => 'diverifikasi']);

            return $rapor->fresh();
        });
    }

    /* ------------------------------------------------------------------ */
    /* Private helpers                                                    */
    /* ------------------------------------------------------------------ */

    private function pilihTemplat(Siswa $siswa, Semester $semester): ?RaporTemplat
    {
        // Templat paling spesifik menang: sekolah > kurikulum > jenjang > default (semua null)
        $templat = RaporTemplat::query()
            ->where('is_aktif', true)
            ->where(function ($q) use ($siswa, $semester) {
                $q->where('sekolah_id', $siswa->rombel?->sekolah_id)
                    ->orWhereNull('sekolah_id');
            })
            ->where(function ($q) use ($semester) {
                $q->where('tahun_ajaran_id', $semester->tahun_ajaran_id)
                    ->orWhereNull('tahun_ajaran_id');
            })
            ->orderByDesc('sekolah_id')
            ->orderByDesc('kurikulum_id')
            ->orderByDesc('jenjang_id')
            ->orderByDesc('tahun_ajaran_id')
            ->first();

        if (! $templat) {
            $templat = RaporTemplat::firstOrCreate(
                ['kode' => 'rapor_tk_default'],
                [
                    'sekolah_id' => null,
                    'jenjang_id' => null,
                    'kurikulum_id' => null,
                    'tahun_ajaran_id' => null,
                    'cabang_id' => null,
                    'nama' => 'Rapor TK Default',
                    'jenis_penilaian' => 'deskriptif',
                    'pengaturan' => [],
                    'is_aktif' => true,
                ]
            );
        }

        return $templat;
    }

    private function pastikanLingkupCabang(Siswa $siswa): void
    {
        $konteks = app(CabangKonteks::class);
        $diizinkan = $konteks->cabangDiizinkan();
        $cabangId = $this->cabangIdSiswa($siswa);

        if ($diizinkan !== null && $cabangId !== null && ! in_array((int) $cabangId, $diizinkan, true)) {
            throw new RaporException('Tidak berhak mengelola rapor untuk cabang ini.');
        }
    }

    private function cabangIdSiswa(Siswa $siswa): ?int
    {
        return $siswa->rombelSiswaAktif->first()?->cabang_id;
    }

    private function pastikanLingkupCabangViaRapor(Rapor $rapor): void
    {
        $siswa = $rapor->siswa;
        $this->pastikanLingkupCabang($siswa);
    }
}
