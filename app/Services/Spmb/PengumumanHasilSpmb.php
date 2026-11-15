<?php

namespace App\Services\Spmb;

use App\Exceptions\Spmb\TransisiTidakValid;
use App\Models\SpmbPendaftar;
use App\Models\SpmbPeriode;
use App\Support\CabangKonteks;
use Illuminate\Support\Facades\DB;

/**
 * Menayangkan hasil satu periode dan mengantrekan pemberitahuan kepada orang tua.
 * Pengumuman bersifat permanen; pengulangan atau eksekusi bersamaan ditolak.
 */
class PengumumanHasilSpmb
{
    private const STATUS_DIUMUMKAN = ['lulus', 'cadangan', 'tidak_lulus', 'daftar_ulang'];

    public function __construct(private readonly NotifikasiSpmb $notifikasi)
    {
    }

    /** @throws TransisiTidakValid */
    public function umumkan(SpmbPeriode $periode, int $userId): int
    {
        $konteks = app(CabangKonteks::class);
        if (! $konteks->punyaPeran(['sysadmin', 'yayasan', 'direktur', 'kepala_cabang'])) {
            throw new TransisiTidakValid('Anda tidak memiliki kewenangan untuk mengumumkan hasil seleksi.');
        }

        return DB::transaction(function () use ($periode): int {
            // Global scope tetap berlaku agar pengguna cabang lain tidak dapat menemukan atau mengumumkan periode ini.
            $terkunci = SpmbPeriode::query()->lockForUpdate()->find($periode->id);
            if ($terkunci === null) {
                throw new TransisiTidakValid('Periode SPMB tidak ditemukan atau berada di luar cabang Anda.');
            }
            if ($terkunci->hasil_diumumkan_pada !== null) {
                throw new TransisiTidakValid('Hasil seleksi periode ini sudah diumumkan.');
            }

            $pendaftar = $terkunci->pendaftar()
                ->with('hasil')
                ->whereHas('hasil')
                ->whereIn('status', self::STATUS_DIUMUMKAN)
                ->orderBy('id')
                ->get();
            if ($pendaftar->isEmpty()) {
                throw new TransisiTidakValid('Belum ada hasil seleksi yang dapat diumumkan pada periode ini.');
            }

            $terkunci->update(['hasil_diumumkan_pada' => now()]);

            $jumlah = 0;
            foreach ($pendaftar as $p) {
                if ($this->notifikasi->hasil($p)) {
                    $jumlah++;
                }
            }

            return $jumlah;
        });
    }

    /** @return array{lulus: int, cadangan: int, tidak_lulus: int} */
    public static function ringkasan(SpmbPeriode $periode): array
    {
        $jumlah = SpmbPendaftar::query()
            ->where('periode_id', $periode->id)
            ->whereHas('hasil')
            ->whereIn('status', self::STATUS_DIUMUMKAN)
            ->join('spmb_hasil', 'spmb_hasil.pendaftar_id', '=', 'spmb_pendaftar.id')
            ->selectRaw("COUNT(*) FILTER (WHERE spmb_hasil.keputusan = 'lulus') AS lulus")
            ->selectRaw("COUNT(*) FILTER (WHERE spmb_hasil.keputusan = 'cadangan') AS cadangan")
            ->selectRaw("COUNT(*) FILTER (WHERE spmb_hasil.keputusan = 'tidak_lulus') AS tidak_lulus")
            ->first();

        return [
            'lulus' => (int) ($jumlah?->lulus ?? 0),
            'cadangan' => (int) ($jumlah?->cadangan ?? 0),
            'tidak_lulus' => (int) ($jumlah?->tidak_lulus ?? 0),
        ];
    }
}
