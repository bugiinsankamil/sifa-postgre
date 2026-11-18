<?php

namespace App\Services\Sdm;

use App\Exceptions\Sdm\SdmException;
use App\Models\AbsensiLogMentah;
use App\Models\AbsensiPegawai;
use App\Models\Pegawai;
use App\Models\ShiftKerja;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Impor log mentah dari mesin absensi/GPS/QR dan olah menjadi absensi harian.
 * Deduplikasi per perangkat (id_unik_perangkat) dijaga service dan unique index database.
 * Hasil harian dihitung dari jam masuk paling awal dan jam pulang paling akhir,
 * sehingga urutan kedatangan log tidak memengaruhi hasil akhir.
 */
class AbsensiLogProcessor
{
    /** Batas percobaan ulang log yang gagal diproses. */
    public const MAKS_PERCOBAAN = 5;

    /** Batas batch bawaan bila pemanggil tidak menentukan. */
    public const BATCH_BAWAAN = 500;

    /** Jumlah log gagal pada pemanggilan prosesSemuaLogMentah terakhir. */
    public int $gagal = 0;

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

        try {
            return DB::transaction(function () use ($pegawai, $perangkatId, $cabangId, $waktu, $tipe, $metode, $latitude, $longitude, $idUnikPerangkat, $payload) {
                // Deduplikasi per perangkat
                if ($perangkatId !== null && $idUnikPerangkat !== null) {
                    $ada = AbsensiLogMentah::query()
                        ->withoutGlobalScopes()
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
        } catch (UniqueConstraintViolationException) {
            // Impor bersamaan: unique index database menang. Ditangkap di luar transaksi.
            throw new SdmException('Log ini sudah pernah direkam.');
        }
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

        if ((int) $log->cabang_id !== (int) $penugasan->cabang_id) {
            throw new SdmException('Cabang log tidak sesuai dengan penugasan pegawai pada tanggal '.$tanggal.'.');
        }

        $shift = $absensiSvc->shiftPada($pegawai, $tanggal);

        return DB::transaction(function () use ($log, $pegawai, $penugasan, $shift, $tanggal) {
            $terkunci = AbsensiLogMentah::query()->withoutGlobalScopes()->lockForUpdate()->find($log->id);
            if ($terkunci === null || $terkunci->diproses_pada !== null) {
                return null;
            }

            $absensi = AbsensiPegawai::query()
                ->withoutGlobalScopes()
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
                $terkunci->update(['diproses_pada' => now(), 'galat' => null]);

                return $absensi;
            }

            $jam = Carbon::parse($terkunci->waktu)->format('H:i:s');

            if ($terkunci->tipe === 'masuk') {
                // Jam masuk paling awal menang, berapa pun urutan kedatangan log
                if ($absensi->jam_masuk === null || $jam < $absensi->jam_masuk) {
                    $absensi->jam_masuk = $jam;
                    $absensi->metode = $terkunci->metode;
                    $absensi->latitude = $terkunci->latitude;
                    $absensi->longitude = $terkunci->longitude;
                }
            } elseif ($terkunci->tipe === 'pulang') {
                // Jam pulang paling akhir menang
                if ($absensi->jam_pulang === null || $jam > $absensi->jam_pulang) {
                    $absensi->jam_pulang = $jam;
                }
            }

            $this->hitungUlang($absensi, $shift, $tanggal);

            $absensi->save();
            $terkunci->update(['diproses_pada' => now(), 'galat' => null]);

            return $absensi;
        });
    }

    /**
     * Hitung status, terlambat, dan pulang cepat dari jam masuk/pulang tersimpan.
     */
    private function hitungUlang(AbsensiPegawai $absensi, ?ShiftKerja $shift, string $tanggal): void
    {
        // Baru ada log pulang: tunggu log masuk; perhitungan dilakukan saat log masuk tiba
        if ($absensi->jam_masuk === null) {
            return;
        }

        if ($shift === null) {
            $absensi->status = 'hadir';
            $absensi->terlambat_menit = 0;
            $absensi->pulang_cepat_menit = 0;

            return;
        }

        $masuk = Carbon::parse($tanggal.' '.$absensi->jam_masuk);
        $batasToleransi = Carbon::parse($tanggal.' '.$shift->jam_masuk)->addMinutes((int) $shift->toleransi_menit);

        if ($masuk->gt($batasToleransi)) {
            $absensi->status = 'terlambat';
            $absensi->terlambat_menit = (int) $batasToleransi->diffInMinutes($masuk, true);
        } else {
            $absensi->status = 'hadir';
            $absensi->terlambat_menit = 0;
        }

        if ($absensi->jam_pulang !== null) {
            $jamSelesai = Carbon::parse($tanggal.' '.$shift->jam_pulang);
            $pulang = Carbon::parse($tanggal.' '.$absensi->jam_pulang);
            $absensi->pulang_cepat_menit = $pulang->lt($jamSelesai) ? (int) $pulang->diffInMinutes($jamSelesai, true) : 0;
        }
    }

    /**
     * Proses log mentah yang belum diproses dan belum melewati batas percobaan.
     * Log yang gagal dicatat (galat, percobaan, gagal_pada) dan tidak memblokir log lain.
     * Tanpa $limit dipakai BATCH_BAWAAN agar satu putaran scheduler tidak tanpa batas.
     *
     * @return int jumlah log berhasil; jumlah gagal tersedia di $this->gagal
     */
    public function prosesSemuaLogMentah(?int $limit = null): int
    {
        $limit = ($limit !== null && $limit > 0) ? $limit : self::BATCH_BAWAAN;
        $this->gagal = 0;

        $logs = AbsensiLogMentah::query()
            ->whereNull('diproses_pada')
            ->where('percobaan', '<', self::MAKS_PERCOBAAN)
            ->orderBy('waktu')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $berhasil = 0;

        foreach ($logs as $log) {
            try {
                $this->prosesLogMentah($log);
                $berhasil++;
            } catch (\Throwable $e) {
                $this->gagal++;
                $this->catatGagal($log, $e);
            }
        }

        return $berhasil;
    }

    /**
     * Setel ulang log gagal agar diproses lagi (dipakai operator setelah data diperbaiki).
     */
    public function ulangiLogGagal(AbsensiLogMentah $log): void
    {
        if ($log->diproses_pada !== null) {
            throw new SdmException('Log sudah berhasil diproses.');
        }

        $log->update(['percobaan' => 0, 'galat' => null, 'gagal_pada' => null]);
    }

    private function catatGagal(AbsensiLogMentah $log, \Throwable $e): void
    {
        Log::warning('Gagal memproses absensi log mentah #'.$log->id.': '.$e->getMessage());

        AbsensiLogMentah::query()->withoutGlobalScopes()->whereKey($log->id)->update([
            'galat' => mb_substr($e->getMessage(), 0, 1000),
            'percobaan' => DB::raw('percobaan + 1'),
            'gagal_pada' => now(),
        ]);
    }
}
