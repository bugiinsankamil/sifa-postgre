<?php

namespace App\Services\Akademik;

use App\Exceptions\Akademik\JurnalMengajarException;
use App\Models\JurnalMengajar;
use App\Models\JamPelajaran;
use App\Models\Pegawai;
use App\Models\PenugasanMengajar;
use App\Models\User;
use App\Support\CabangKonteks;
use Illuminate\Support\Facades\DB;

/**
 * Jurnal kegiatan mengajar per penugasan.
 * jam_pelajaran_id NULL = harian (TK/SD); diisi = per jam (SMP).
 */
class JurnalMengajarService
{
    /**
     * Buat jurnal mengajar. Idemen: tidak ada duplikat untuk penugasan + tanggal + jam yang sama.
     */
    public function buatJurnal(
        PenugasanMengajar $penugasan,
        string $tanggal,
        ?JamPelajaran $jamPelajaran = null,
        ?Pegawai $pengajar = null,
        string $materi = null,
        ?string $kegiatan = null,
        ?string $catatan = null,
        string $status = 'hadir',
        ?User $pengguna = null
    ): JurnalMengajar {
        if ($penugasan->status !== 'aktif') {
            throw new JurnalMengajarException('Penugasan mengajar tidak aktif.');
        }

        if (! in_array($status, ['hadir', 'diganti', 'kosong'], true)) {
            throw new JurnalMengajarException('Status jurnal tidak valid.');
        }

        return DB::transaction(function () use ($penugasan, $tanggal, $jamPelajaran, $pengajar, $materi, $kegiatan, $catatan, $status, $pengguna) {
            $existing = JurnalMengajar::query()
                ->where('penugasan_mengajar_id', $penugasan->id)
                ->where('tanggal', $tanggal)
                ->when($jamPelajaran, fn ($q) => $q->where('jam_pelajaran_id', $jamPelajaran->id))
                ->when(! $jamPelajaran, fn ($q) => $q->whereNull('jam_pelajaran_id'))
                ->first();

            if ($existing) {
                throw new JurnalMengajarException('Jurnal untuk penugasan, tanggal, dan jam pelajaran ini sudah dicatat.');
            }

            $jurnal = JurnalMengajar::query()->create([
                'penugasan_mengajar_id' => $penugasan->id,
                'cabang_id' => $penugasan->cabang_id,
                'tanggal' => $tanggal,
                'jam_pelajaran_id' => $jamPelajaran?->id,
                'pegawai_id' => $pengajar?->id ?? $penugasan->pegawai_id,
                'materi' => $materi,
                'kegiatan' => $kegiatan,
                'catatan' => $catatan,
                'status' => $status,
            ]);

            return $jurnal->fresh();
        });
    }

    /** Perbarui isi jurnal (materi/kegiatan/catatan/status). */
    public function perbaruiJurnal(JurnalMengajar $jurnal, array $data, User $pengguna): JurnalMengajar
    {
        $this->pastikanDapatMengelolaJurnal($pengguna);

        if ($jurnal->status === 'kosong') {
            throw new JurnalMengajarException('Jurnal yang ditandai kosong tidak dapat diubah.');
        }

        $jurnal->update(array_filter($data, fn ($key) => in_array($key, ['materi', 'kegiatan', 'catatan', 'status'], true), ARRAY_FILTER_USE_KEY));

        return $jurnal->fresh();
    }

    /** Tandai jurnal sebagai kosong (tidak ada kegiatan belajar mengajar). */
    public function tandaiKosong(JurnalMengajar $jurnal, User $pengguna): JurnalMengajar
    {
        $this->pastikanDapatMengelolaJurnal($pengguna);

        $jurnal->update(['status' => 'kosong']);

        return $jurnal->fresh();
    }

    /** Pastikan user berhak mengelola jurnal mengajar. */
    private function pastikanDapatMengelolaJurnal(User $user): void
    {
        if (! app(CabangKonteks::class)->punyaPeran(['sysadmin', 'kepala_cabang', 'kepala_sekolah', 'wakil_kepala_sekolah', 'pj_program'])) {
            throw new JurnalMengajarException('Anda tidak berhak mengelola jurnal mengajar.');
        }
    }
}
