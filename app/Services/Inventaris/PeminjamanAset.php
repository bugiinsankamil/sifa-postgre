<?php

namespace App\Services\Inventaris;

use App\Models\AsetUnit;
use App\Models\Peminjaman;
use App\Models\PeminjamanBaris;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Mengelola peminjaman aset tetap (barang yang dipinjam dan harus dikembalikan).
 */
class PeminjamanAset
{
    /**
     * Membuat peminjaman baru dengan status 'diajukan'.
     *
     * @param array $data Data peminjaman (cabang_id, peminjam_id, keperluan, tanggal_pinjam, tanggal_rencana_kembali, dll)
     * @param array<int, array{aset_unit_id: int, kondisi_keluar: string, catatan?: string}> $baris Baris aset yang dipinjam
     * @return Peminjaman
     */
    public function ajukan(array $data, array $baris): Peminjaman
    {
        if (empty($baris)) {
            throw new RuntimeException('Peminjaman harus memiliki minimal satu aset.');
        }

        return DB::transaction(function () use ($data, $baris) {
            $peminjaman = Peminjaman::create(array_merge($data, [
                'status' => 'diajukan',
            ]));

            foreach ($baris as $b) {
                PeminjamanBaris::create([
                    'peminjaman_id' => $peminjaman->id,
                    'aset_unit_id' => $b['aset_unit_id'],
                    'kondisi_keluar' => $b['kondisi_keluar'],
                    'catatan' => $b['catatan'] ?? null,
                ]);
            }

            return $peminjaman->fresh(['baris']);
        });
    }

    /**
     * Menyetujui peminjaman. Aset unit ditandai sebagai 'dipinjam'.
     */
    public function setujui(Peminjaman $peminjaman): Peminjaman
    {
        if ($peminjaman->status !== 'diajukan') {
            throw new RuntimeException('Hanya peminjaman dengan status diajukan yang dapat disetujui.');
        }

        return DB::transaction(function () use ($peminjaman) {
            // Validasi semua aset masih tersedia
            foreach ($peminjaman->baris as $baris) {
                $aset = AsetUnit::find($baris->aset_unit_id);
                if (!$aset || $aset->status !== 'aktif') {
                    throw new RuntimeException("Aset {$aset->kode_aset} tidak tersedia untuk dipinjam (status: {$aset->status}).");
                }
            }

            // Tandai semua aset sebagai dipinjam
            foreach ($peminjaman->baris as $baris) {
                AsetUnit::where('id', $baris->aset_unit_id)->update(['status' => 'dipinjam']);
            }

            $peminjaman->update(['status' => 'dipinjam']);

            return $peminjaman->fresh();
        });
    }

    /**
     * Menolak peminjaman.
     */
    public function tolak(Peminjaman $peminjaman, string $alasan): Peminjaman
    {
        if (!in_array($peminjaman->status, ['diajukan', 'disetujui'])) {
            throw new RuntimeException('Peminjaman tidak dapat ditolak pada status ini.');
        }

        $peminjaman->update([

    /**
     * Mengembalikan aset yang dipinjam. Aset unit dikembalikan ke status 'aktif'.
     *
     * @param array<int, array{aset_unit_id: int, kondisi_kembali: string, catatan?: string}> $pengembalian Data kondisi aset saat kembali
     */
    public function kembalikan(Peminjaman $peminjaman, array $pengembalian): Peminjaman
    {
        if ($peminjaman->status !== 'dipinjam') {
            throw new RuntimeException('Hanya peminjaman dengan status dipinjam yang dapat dikembalikan.');
        }

        return DB::transaction(function () use ($peminjaman, $pengembalian) {
            // Update kondisi kembali per baris
            foreach ($pengembalian as $kembali) {
                PeminjamanBaris::query()
                    ->where('peminjaman_id', $peminjaman->id)
                    ->where('aset_unit_id', $kembali['aset_unit_id'])
                    ->update([
                        'kondisi_kembali' => $kembali['kondisi_kembali'],
                        'catatan' => $kembali['catatan'] ?? null,
                    ]);

                // Kembalikan status aset ke aktif
                AsetUnit::where('id', $kembali['aset_unit_id'])->update([
                    'status' => 'aktif',
                    'kondisi' => $kembali['kondisi_kembali'],
                ]);
            }

            // Cek apakah terlambat
            $status = $peminjaman->tanggal_rencana_kembali->isPast() ? 'terlambat' : 'kembali';

            $peminjaman->update([
                'status' => $status,
                'tanggal_kembali' => today(),
            ]);

            return $peminjaman->fresh(['baris']);
        });
    }

    /**
     * Menandai aset yang dipinjam sebagai hilang.
     */
    public function tandaiHilang(Peminjaman $peminjaman, int $asetUnitId, string $catatan): Peminjaman
    {
        if ($peminjaman->status !== 'dipinjam') {
            throw new RuntimeException('Hanya peminjaman dengan status dipinjam yang dapat ditandai hilang.');
        }

        return DB::transaction(function () use ($peminjaman, $asetUnitId, $catatan) {
            PeminjamanBaris::query()
                ->where('peminjaman_id', $peminjaman->id)
                ->where('aset_unit_id', $asetUnitId)
                ->update([
                    'kondisi_kembali' => 'hilang',
                    'catatan' => $catatan,
                ]);

            AsetUnit::where('id', $asetUnitId)->update([
                'status' => 'dihapus',
                'kondisi' => 'rusak_berat',
            ]);

            // Cek apakah semua baris sudah dikembalikan atau hilang
            $belumKembali = $peminjaman->baris()
                ->whereNull('kondisi_kembali')
                ->count();

            if ($belumKembali === 0) {
                $peminjaman->update([
                    'status' => 'hilang',
                    'tanggal_kembali' => today(),
                ]);
            }

            return $peminjaman->fresh(['baris']);
        });
    }
}

            'status' => 'ditolak',
            'catatan' => ($peminjaman->catatan ?? '') . "\nDitolak: {$alasan}",
        ]);

        return $peminjaman;
    }
