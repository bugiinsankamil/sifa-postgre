<?php

namespace App\Services\Sdm;

use App\Exceptions\Sdm\SdmException;
use App\Models\Pegawai;
use App\Models\PegawaiShift;
use App\Models\ShiftKerja;
use Illuminate\Support\Facades\DB;

/**
 * Penugasan shift pegawai. Satu shift berjalan per pegawai (partial unique index);
 * penetapan shift baru otomatis menutup shift yang sedang berjalan.
 */
class ShiftKerjaService
{
    public function tetapkan(Pegawai $pegawai, ShiftKerja $shift, string $tanggalMulai): PegawaiShift
    {
        return DB::transaction(function () use ($pegawai, $shift, $tanggalMulai) {
            // Tutup shift yang sedang berjalan sehari sebelum shift baru mulai
            PegawaiShift::query()
                ->where('pegawai_id', $pegawai->id)
                ->whereNull('tanggal_akhir')
                ->update(['tanggal_akhir' => date('Y-m-d', strtotime($tanggalMulai.' -1 day'))]);

            return PegawaiShift::create([
                'pegawai_id' => $pegawai->id,
                'shift_id' => $shift->id,
                'tanggal_mulai' => $tanggalMulai,
            ]);
        });
    }

    public function akhiri(PegawaiShift $penugasan, string $tanggalAkhir): PegawaiShift
    {
        if ($penugasan->tanggal_akhir !== null) {
            throw new SdmException('Penugasan shift ini sudah berakhir.');
        }

        if ($tanggalAkhir < $penugasan->tanggal_mulai->toDateString()) {
            throw new SdmException('Tanggal akhir shift tidak boleh sebelum tanggal mulai.');
        }

        $penugasan->update(['tanggal_akhir' => $tanggalAkhir]);

        return $penugasan;
    }

    /** Shift yang berlaku untuk pegawai pada tanggal tertentu. */
    public function shiftPada(Pegawai $pegawai, string $tanggal): ?ShiftKerja
    {
        $penugasan = PegawaiShift::query()
            ->where('pegawai_id', $pegawai->id)
            ->where('tanggal_mulai', '<=', $tanggal)
            ->where(fn ($q) => $q->whereNull('tanggal_akhir')->orWhere('tanggal_akhir', '>=', $tanggal))
            ->orderByDesc('tanggal_mulai')
            ->first();

        return $penugasan?->shift;
    }
}
