<?php

namespace App\Services\Sdm;

use App\Exceptions\Sdm\SdmException;
use App\Models\AbsensiPegawai;
use App\Models\Pegawai;
use App\Models\PegawaiShift;
use App\Models\PenugasanPegawai;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pencatatan manual absensi pegawai dan pencarian konteks (penugasan, shift).
 */
class AbsensiService
{
    /**
     * Catat absensi manual (hadir/izin/sakit/cuti/dinas/alpa) tanpa log mesin.
     * Dipakai oleh staf SDM atau oleh approval cuti/izin untuk menandai absensi.
     *
     * @param array $data ['jam_masuk', 'jam_pulang', 'status', 'keterangan', 'latitude', 'longitude', 'foto_path']
     */
    public function catatManual(Pegawai $pegawai, string $tanggal, array $data, ?int $diajukanOleh = null): AbsensiPegawai
    {
        $penugasan = $this->penugasanAktifPada($pegawai, $tanggal);
        if ($penugasan === null) {
            throw new SdmException('Pegawai tidak memiliki penugasan aktif pada tanggal '.$tanggal.'.');
        }

        $shift = $this->shiftPada($pegawai, $tanggal);

        return DB::transaction(function () use ($pegawai, $penugasan, $shift, $tanggal, $data, $diajukanOleh) {
            $absensi = AbsensiPegawai::query()
                ->lockForUpdate()
                ->firstOrNew([
                    'pegawai_id' => $pegawai->id,
                    'tanggal' => $tanggal,
                ]);

            if (! $absensi->exists) {
                $absensi->fill([
                    'cabang_id' => $penugasan->cabang_id,
                    'shift_id' => $shift?->id,
                    'status' => 'alpa',
                    'metode' => 'manual',
                ]);
            }

            $absensi->fill([
                'status' => $data['status'],
                'metode' => 'manual',
                'jam_masuk' => $data['jam_masuk'] ?? $absensi->jam_masuk,
                'jam_pulang' => $data['jam_pulang'] ?? $absensi->jam_pulang,
                'keterangan' => $data['keterangan'] ?? $absensi->keterangan,
                'latitude' => $data['latitude'] ?? $absensi->latitude,
                'longitude' => $data['longitude'] ?? $absensi->longitude,
                'foto_path' => $data['foto_path'] ?? $absensi->foto_path,
                'diajukan_oleh' => $diajukanOleh ?? $absensi->diajukan_oleh,
                'pengajuan_cuti_id' => $data['pengajuan_cuti_id'] ?? $absensi->pengajuan_cuti_id,
                'pengajuan_izin_id' => $data['pengajuan_izin_id'] ?? $absensi->pengajuan_izin_id,
            ]);

            // Hitung terlambat/pulang cepat jika shift dan jam tersedia
            if ($shift !== null && $absensi->jam_masuk !== null) {
                $jamMulai = Carbon::parse($tanggal.' '.$shift->jam_masuk);
                $batasToleransi = $jamMulai->copy()->addMinutes($shift->toleransi_menit);
                $waktuMasuk = Carbon::parse($tanggal.' '.$absensi->jam_masuk);

                $absensi->terlambat_menit = $waktuMasuk->gt($batasToleransi)
                    ? (int) abs($batasToleransi->diffInMinutes($waktuMasuk))
                    : 0;
            }

            if ($shift !== null && $absensi->jam_pulang !== null) {
                $jamSelesai = Carbon::parse($tanggal.' '.$shift->jam_pulang);
                $waktuPulang = Carbon::parse($tanggal.' '.$absensi->jam_pulang);

                $absensi->pulang_cepat_menit = $waktuPulang->lt($jamSelesai)
                    ? (int) abs($jamSelesai->diffInMinutes($waktuPulang))
                    : 0;
            }

            $absensi->save();

            return $absensi;
        });
    }

    /**
     * Shift pegawai pada tanggal tertentu.
     */
    public function shiftPada(Pegawai $pegawai, string $tanggal): ?\App\Models\ShiftKerja
    {
        $penugasan = PegawaiShift::query()
            ->where('pegawai_id', $pegawai->id)
            ->where('tanggal_mulai', '<=', $tanggal)
            ->where(fn ($q) => $q->whereNull('tanggal_akhir')->orWhere('tanggal_akhir', '>=', $tanggal))
            ->orderByDesc('tanggal_mulai')
            ->first();

        return $penugasan?->shift;
    }

    /**
     * Penugasan aktif pada tanggal tertentu (penentuan cabang).
     */
    public function penugasanAktifPada(Pegawai $pegawai, string $tanggal): ?PenugasanPegawai
    {
        return PenugasanPegawai::query()
            ->where('pegawai_id', $pegawai->id)
            ->where('tmt', '<=', $tanggal)
            ->where(fn ($q) => $q->whereNull('tanggal_akhir')->orWhere('tanggal_akhir', '>=', $tanggal))
            ->orderByDesc('tmt')
            ->first();
    }
}
