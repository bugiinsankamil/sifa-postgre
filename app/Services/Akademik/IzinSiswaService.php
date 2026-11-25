<?php

namespace App\Services\Akademik;

use App\Exceptions\Akademik\IzinSiswaException;
use App\Models\IzinSiswa;
use App\Models\Orang;
use App\Models\RombelSiswa;
use App\Models\Siswa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Izin/sakit siswa dengan rentang tanggal.
 * Alur: diajukan -> disetujui/ditolak; pembatalan dengan alasan wajib.
 */
class IzinSiswaService
{
    /**
     * Ajukan izin/sakit siswa.
     */
    public function ajukanIzin(
        Siswa $siswa,
        string $tanggalMulai,
        string $tanggalSelesai,
        string $jenis,
        ?string $alasan = null,
        Orang $diajukanOleh
    ): IzinSiswa {
        if (! in_array($jenis, ['sakit', 'izin'], true)) {
            throw new IzinSiswaException('Jenis izin harus sakit atau izin.');
        }

        if ($tanggalSelesai < $tanggalMulai) {
            throw new IzinSiswaException('Tanggal selesai tidak boleh sebelum tanggal mulai.');
        }

        return DB::transaction(function () use ($siswa, $tanggalMulai, $tanggalSelesai, $jenis, $alasan, $diajukanOleh) {
            $cabangId = RombelSiswa::query()
                ->where('siswa_id', $siswa->id)
                ->whereNull('tanggal_keluar')
                ->orderByDesc('tanggal_masuk')
                ->value('cabang_id');

            $izin = IzinSiswa::query()->create([
                'siswa_id' => $siswa->id,
                'cabang_id' => $cabangId,
                'tanggal_mulai' => $tanggalMulai,
                'tanggal_selesai' => $tanggalSelesai,
                'jenis' => $jenis,
                'alasan' => $alasan,
                'diajukan_oleh' => $diajukanOleh->id,
                'status' => 'diajukan',
            ]);

            return $izin->fresh();
        });
    }

    /** Setujui izin (hanya status diajukan). */
    public function setujuiIzin(IzinSiswa $izin, User $disetujuiOleh): IzinSiswa
    {
        return DB::transaction(function () use ($izin, $disetujuiOleh) {
            $terkunci = IzinSiswa::query()->lockForUpdate()->findOrFail($izin->id);

            if ($terkunci->status !== 'diajukan') {
                throw new IzinSiswaException('Izin siswa sudah diproses.');
            }

            $terkunci->update(['status' => 'disetujui']);

            return $terkunci->fresh();
        });
    }

    /** Tolak izin. Alasan penolakan wajib. */
    public function tolakIzin(IzinSiswa $izin, User $penolak, string $alasan): IzinSiswa
    {
        if (blank($alasan)) {
            throw new IzinSiswaException('Alasan penolakan wajib diisi.');
        }

        return DB::transaction(function () use ($izin, $penolak, $alasan) {
            $terkunci = IzinSiswa::query()->lockForUpdate()->findOrFail($izin->id);

            if ($terkunci->status !== 'diajukan') {
                throw new IzinSiswaException('Izin siswa sudah diproses.');
            }

            $terkunci->update(['status' => 'ditolak']);

            return $terkunci->fresh();
        });
    }

    /** Batalkan izin. Alasan wajib. Tidak bisa membatalkan yang sudah ditolak/dibatalkan. */
    public function batalkanIzin(IzinSiswa $izin, User $pembatal, string $alasan): IzinSiswa
    {
        if (blank($alasan)) {
            throw new IzinSiswaException('Alasan pembatalan wajib diisi.');
        }

        return DB::transaction(function () use ($izin, $pembatal, $alasan) {
            $terkunci = IzinSiswa::query()->lockForUpdate()->findOrFail($izin->id);

            if (in_array($terkunci->status, ['ditolak', 'dibatalkan'], true)) {
                throw new IzinSiswaException('Izin siswa sudah berstatus akhir ('.$terkunci->status.').');
            }

            $terkunci->update(['status' => 'dibatalkan']);

            return $terkunci->fresh();
        });
    }
}
