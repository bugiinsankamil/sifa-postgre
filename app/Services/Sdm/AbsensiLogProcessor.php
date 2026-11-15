<?php

namespace App\Services\Sdm;

use App\Exceptions\Sdm\SdmException;
use App\Models\AbsensiLogMentah;
use App\Models\AbsensiPegawai;
use App\Models\Pegawai;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Impor log mentah dari mesin absensi/GPS/QR dan olah menjadi absensi harian.
 * Deduplikasi per perangkat (id_unik_perangkat) mencegah double-catat dari satu mesin.
 */
class AbsensiLogProcessor
{
    /**
     * Impor satu log mentah dari perangkat/manual.
     */
    public function imporLog(Pegawai $pegawai, ?int $perangkatId, string $waktu, string $tipe, string $metode, ?float $latitude = null, ?float $longitude = null, ?string $idUnikPerangkat = null, ?array $payload = null): AbsensiLogMentah
    {
        $tanggal = substr($waktu, 0, 10);
        $penugasan = app(AbsensiService::class)->penugasanAktifPada($pegawai, $tanggal);
        if ($penugasan === null) {
            throw new SdmException('Pegawai tidak memiliki penugasan aktif pada tanggal '.$tanggal.'.');
        }

        $cabangId = $penugasan->cabang_id;

        return DB::transaction(function () use ($pegawai, $perangkatId, $cabangId, $waktu, $tipe, $metode, $latitude, $longitude, $idUnikPerangkat, $payload) {
            // Deduplikasi per perangkat
            if ($perangkatId !== null && $idUnikPerangkat !== null) {
                $ada = AbsensiLogMentah::query()
                    ->where('perangkat_id', $perangkatId)
                    ->where('id_unik_perangkat', $idUnikPerangkat)
                    ->exists();
                if ($ada) {
                    throw new SdmException('Log ini sudah pernah direkam.');
                }
            }

            return AbsensiLogMentah::create([
                'cabang_id' => $cabangId,
                'perangkat_id' => $perangkatId,
                'pegawai_id' => $pegawai->id,
                'pin' => $payload['pin'] ?? null,
                'waktu' => $waktu,
                'metode' => $metode,
                'tipe' => $tipe,
                'id_unik_perangkat' => $idUnikPerangkat,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'payload' => $payload,
            ]);
        });
    }

    /**
     * Proses satu log mentah menjadi absensi harian.
     * Log tidak boleh menimpa status izin/sakit/cuti/dinas dari approval cuti/izin.
     */
    public function prosesLogMentah(AbsensiLogMentah $log): ?AbsensiPegawai
    {
        if ($log->diproses_pada !== null) {
            return null;
        }

        $tanggal = Carbon::parse($log->waktu)->toDateString();
        $pegawai = $log->pegawai;
        if ($pegawai === null) {
            throw new SdmException('Log mentah tidak terkait pegawai.');
        }

        $absensiSvc = app(AbsensiService::class);
        $penugasan = $absensiSvc->penugasanAktifPada($pegawai, $tanggal);
        if ($penugasan === null) {
            throw new SdmException('Pegawai tidak memiliki penugasan aktif pada tanggal '.$tanggal.'.');
        }

        $shift = $absensiSvc->shiftPada($pegawai, $tanggal);

        return DB::transaction(function () use ($log, $pegawai, $penugasan, $shift, $tanggal) {
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

            // Log mesin tidak boleh menimpa status hasil persetujuan
            if (in_array($absensi->status, ['izin', 'sakit', 'cuti', 'dinas'], true)) {
                $log->update(['diproses_pada' => now()]);
                return $absensi;
            }

            $waktu = Carbon::parse($log->waktu);
            $jam = $waktu->format('H:i:s');

            if ($log->tipe === 'masuk') {
                $absensi->jam_masuk = $jam;
                $absensi->metode = $log->metode;
                $absensi->latitude = $log->latitude;
                $absensi->longitude = $log->longitude;

                if ($shift !== null) {
                    $jamMulai = Carbon::parse($tanggal.' '.$shift->jam_masuk);
                    $batasToleransi = $jamMulai->copy()->addMinutes($shift->toleransi_menit);

                    if ($waktu->gt($batasToleransi)) {
                        $absensi->status = 'terlambat';
                        $absensi->terlambat_menit = $batasToleransi->diffInMinutes($waktu);
                    } else {
                        $absensi->status = 'hadir';
                        $absensi->terlambat_menit = 0;
                    }
                } else {
                    $absensi->status = 'hadir';
                }
            } elseif ($log->tipe === 'pulang') {
                $absensi->jam_pulang = $jam;

                if ($shift !== null && $absensi->status === 'hadir') {
                    $jamSelesai = Carbon::parse($tanggal.' '.$shift->jam_pulang);
                    if ($waktu->lt($jamSelesai)) {
                        $absensi->pulang_cepat_menit = $waktu->diffInMinutes($jamSelesai);
                    } else {
                        $absensi->pulang_cepat_menit = 0;
                    }
                }
            }

            $absensi->save();
            $log->update(['diproses_pada' => now()]);

            return $absensi;
        });
    }
}
