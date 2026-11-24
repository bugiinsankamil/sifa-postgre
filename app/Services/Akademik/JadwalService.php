<?php

namespace App\Services\Akademik;

use App\Exceptions\Akademik\JadwalException;
use App\Models\JadwalPelajaran;
use App\Models\PenugasanMengajar;
use App\Models\Ruang;
use App\Models\Semester;
use Illuminate\Support\Facades\DB;

class JadwalService
{
    /** Buat jadwal pelajaran untuk penugasan dan jam tertentu. */
    public function buatJadwal(
        PenugasanMengajar $penugasan,
        JamPelajaran $jamPelajaran,
        ?Ruang $ruang = null,
        ?Semester $semester = null
    ): JadwalPelajaran {
        return DB::transaction(function () use ($penugasan, $jamPelajaran, $ruang, $semester) {
            // Cek bentrok sebelum membuat
            $bentrok = $this->cekBentrok($penugasan, $jamPelajaran);

            if (!empty($bentrok)) {
                $pesan = '';
                foreach ($bentrok as $b) {
                    $pesan .= $b['kunci'] . ': sudah ditempati. ';
                }
                throw new JadwalException($pesan . 'Gunakan edit untuk mengubah jadwal yang sudah ada.');
            }

            $jadwal = JadwalPelajaran::create([
                'semester_id' => $semester?->id,
                'penugasan_mengajar_id' => $penugasan->id,
                'jam_pelajaran_id' => $jamPelajaran->id,
                'ruang_id' => $ruang?->id,
                'status' => 'plan',
            ]);

            return $jadwal->fresh();
        });
    }

    /** Cek bentrok jadwal untuk penugasan dan jam tertentu. */
    public function cekBentrok(PenugasanMengajar $penugasan, JamPelajaran $jamPelajaran, ?JadwalPelajaran $kecuali = null): array
    {
        $query = JadwalPelajaran::where('penugasan_mengajar_id', $penugasan->id)
            ->where('jam_pelajaran_id', $jamPelajaran->id);

        if ($kecuali) {
            $query->where('id', '!=', $kecuali->id);
        }

        $jads = $query->get();

        $bentrok = [];

        // Cek bentrok berdasarkan ruang (jika ada)
        foreach ($jads as $j) {
            if ($j->ruang_id && $jamPelajaran->ruang_id) {
                // Jika sama-sama punya ruang, cek apakah ruangnya sama
                if ($j->ruang_id === $jamPelajaran->ruang_id) {
                    $bentrok[] = [
                        'kunci' => "Ruang {$j->ruang->nama} ({$j->jam_pelajaran->hari}, {$j->jam_pelajaran->urutan})",
                        'detail' => $j->toArray(),
                    ];
                }
            }
        }

        // Cek bentrok guru/rombel melalui penugasan_mengajar
        // (jika jam_pelajaran sama, pastikan penugasan berbeda)
        $byKey = $jads->groupBy(function ($j) {
            return [
                'hari' => $j->jam_pelajaran->hari,
                'urutan' => $j->jam_pelajaran->urutan,
            ];
        });

        foreach ($byKey as $key => $items) {
            if (count($items) > 1) {
                $bentrok[] = [
                    'kunci' => "Hari {$key['hari']}, Urutan {$key['urutan']}",
                    'detail' => $items->keyBy('id')->toArray(),
                ];
            }
        }

        return $bentrok;
    }

    /** Dapatkan jadwal aktif untuk semester tertentu. */
    public function dapatkanAktifBySemester(Semester $semester): \Illuminate\Support\Collection
    {
        return JadwalPelajaran::where('semester_id', $semester->id)
            ->where('status', 'aktif')
            ->with(['penugasanMengajar.pegawai', 'jamPelajaran', 'ruang'])
            ->get();
    }
}