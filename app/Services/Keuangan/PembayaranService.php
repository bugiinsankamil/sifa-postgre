<?php

namespace App\Services\Keuangan;

use App\Events\PembayaranBerhasil;
use App\Exceptions\Keuangan\PembayaranException;
use App\Models\Pembayaran;
use App\Models\PembayaranAlokasi;
use App\Models\PemetaanAkun;
use App\Models\Tagihan;
use App\Models\TransaksiKas;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PembayaranService
{
    public function __construct(private KasService $kas) {}

    /**
     * Buat pembayaran baru dengan status menunggu (belum membuat jurnal).
     */
    public function buat(array $data): Pembayaran
    {
        return Pembayaran::create([
            'cabang_id' => $data['cabang_id'],
            'entitas_id' => $data['entitas_id'],
            'siswa_id' => $data['siswa_id'] ?? null,
            'pendaftar_id' => $data['pendaftar_id'] ?? null,
            'nomor' => app(\App\Services\PenomoranDokumen::class)->berikutnya(
                'pembayaran',
                $data['cabang_id'],
                (int) date('Y', strtotime($data['tanggal'])),
                'PMB/{CABANG}/{TAHUN}/{SEQ:5}'
            ),
            'tanggal' => $data['tanggal'],
            'metode' => $data['metode'],
            'nominal' => $data['nominal'],
            'rekening_id' => $data['rekening_id'],
            'referensi_eksternal' => $data['referensi_eksternal'] ?? null,
            'status' => 'menunggu',
            'diterima_oleh' => $data['diterima_oleh'] ?? null,
        ]);
    }

    /**
     * Konfirmasi pembayaran menjadi berhasil: buat transaksi kas masuk + jurnal,
     * alokasi otomatis ke tagihan, dan perbarui status tagihan.
     * Event PembayaranBerhasil dipatch setelah transaksi berhasil di-commit.
     */
    public function konfirmasi(Pembayaran $pembayaran, User $penerima): Pembayaran
    {
        return DB::transaction(function () use ($pembayaran, $penerima) {
            $terkunci = Pembayaran::query()->lockForUpdate()->findOrFail($pembayaran->id);

            if ($terkunci->status !== 'menunggu') {
                throw new PembayaranException(
                    "Pembayaran {$terkunci->nomor} sudah diproses (status {$terkunci->status})."
                );
            }

            $akunLawan = $this->resolveAkunLawan($terkunci->entitas_id);

            $result = $this->kas->masuk([
                'entitas_id' => $terkunci->entitas_id,
                'cabang_id' => $terkunci->cabang_id,
                'rekening_id' => $terkunci->rekening_id,
                'tanggal' => $terkunci->tanggal->toDateString(),
                'nominal' => $terkunci->nominal,
                'akun_lawan_id' => $akunLawan,
                'keterangan' => 'Pembayaran '.$terkunci->nomor.' ('.$terkunci->metode.')',
                'sumber_type' => Pembayaran::class,
                'sumber_id' => $terkunci->id,
            ], $penerima);

            $terkunci->update([
                'status' => 'berhasil',
                'diterima_oleh' => $penerima->id,
                'transaksi_kas_id' => $result['transaksi']->id,
            ]);

            $this->alokasiOtomatis($terkunci);
            $this->perbaruiStatusTagihan($terkunci);

            DB::afterCommit(function () use ($terkunci) {
                event(new PembayaranBerhasil($terkunci->fresh(['alokasi'])));
            });

            return $terkunci->fresh(['alokasi']);
        });
    }

    /**
     * Batalkan pembayaran. Bila sudah berhasil dan punya transaksi kas, buat
     * jurnal pembalik (void) melalui KasService sebelum menandai batal.
     */
    public function batalkan(Pembayaran $pembayaran, User $user, string $alasan): Pembayaran
    {
        return DB::transaction(function () use ($pembayaran, $user, $alasan) {
            $terkunci = Pembayaran::query()->lockForUpdate()->findOrFail($pembayaran->id);

            if ($terkunci->status === 'batal') {
                throw new PembayaranException("Pembayaran {$terkunci->nomor} sudah dibatalkan.");
            }
            if ($terkunci->status === 'gagal') {
                throw new PembayaranException("Pembayaran {$terkunci->nomor} gagal, tidak dapat dibatalkan.");
            }

            if ($terkunci->status === 'berhasil' && $terkunci->transaksi_kas_id) {
                $transaksi = TransaksiKas::query()->lockForUpdate()->findOrFail($terkunci->transaksi_kas_id);
                if ($transaksi->status === 'posted') {
                    app(KasService::class)->batalkan($transaksi, $user->id, $alasan);
                }
            }

            $terkunci->update(['status' => 'batal']);

            return $terkunci->fresh();
        });
    }

    /**
     * Alokasi manual nominal pembayaran ke daftar tagihan.
     *
     * @param array<int, array{tagihan_id: int, nominal: string}> $baris
     */
    public function alokasi(Pembayaran $pembayaran, array $baris): void
    {
        DB::transaction(function () use ($pembayaran, $baris) {
            $terkunci = Pembayaran::query()->lockForUpdate()->findOrFail($pembayaran->id);

            if ($terkunci->status !== 'berhasil') {
                throw new PembayaranException("Hanya pembayaran berhasil yang dapat dialokasi.");
            }

            $total = '0';
            foreach ($baris as $b) {
                $total = bcadd($total, (string) $b['nominal'], 2);
            }
            if (bccomp($total, (string) $terkunci->nominal, 2) > 0) {
                throw new PembayaranException("Total alokasi melebihi nominal pembayaran.");
            }

            PembayaranAlokasi::where('pembayaran_id', $terkunci->id)->delete();

            foreach ($baris as $b) {
                $tagihan = Tagihan::query()->lockForUpdate()->findOrFail($b['tagihan_id']);

                $samaSiswa = $tagihan->siswa_id !== null && $tagihan->siswa_id === $terkunci->siswa_id;
                $samaPendaftar = $tagihan->pendaftar_id !== null && $tagihan->pendaftar_id === $terkunci->pendaftar_id;
                if (!$samaSiswa && !$samaPendaftar) {
                    throw new PembayaranException("Tagihan {$tagihan->nomor} bukan milik siswa/pendaftar pembayaran ini.");
                }

                PembayaranAlokasi::create([
                    'pembayaran_id' => $terkunci->id,
                    'tagihan_id' => $tagihan->id,
                    'nominal' => $b['nominal'],
                ]);
            }

            $this->perbaruiStatusTagihan($terkunci);
        });
    }

    /**
     * Alokasi otomatis ke tagihan yang belum lunas, diurutkan jatuh tempo terlama
     * terlebih dahulu. Sisa yang tidak teralokasi menjadi kelebihan bayar.
     */
    public function alokasiOtomatis(Pembayaran $pembayaran): void
    {
        $siswaId = $pembayaran->siswa_id;
        $pendaftarId = $pembayaran->pendaftar_id;
        $sisa = (string) $pembayaran->nominal;

        $tagihan = Tagihan::query()
            ->when($siswaId, fn ($q) => $q->where('siswa_id', $siswaId))
            ->when($pendaftarId, fn ($q) => $q->where('pendaftar_id', $pendaftarId))
            ->where('status', '!=', 'batal')
            ->orderBy('jatuh_tempo', 'asc')
            ->orderBy('tanggal_terbit', 'asc')
            ->get();

        foreach ($tagihan as $t) {
            if (bccomp($sisa, '0', 2) <= 0) {
                break;
            }

            $tagihanSisa = bcsub((string) $t->total, (string) $t->terbayar, 2);
            if (bccomp($tagihanSisa, '0', 2) <= 0) {
                continue;
            }

            $alloc = bccomp($sisa, $tagihanSisa, 2) < 0 ? $sisa : $tagihanSisa;
            PembayaranAlokasi::create([
                'pembayaran_id' => $pembayaran->id,
                'tagihan_id' => $t->id,
                'nominal' => $alloc,
            ]);
            $sisa = bcsub($sisa, $alloc, 2);
        }

        $this->perbaruiStatusTagihan($pembayaran);
    }

    /**
     * Hitung ulang terbayar dan status setiap tagihan yang terkait pembayaran ini
     * berdasarkan jumlah alokasi yang tersimpan.
     */
    private function perbaruiStatusTagihan(Pembayaran $pembayaran): void
    {
        $siswaId = $pembayaran->siswa_id;
        $pendaftarId = $pembayaran->pendaftar_id;

        Tagihan::query()
            ->when($siswaId, fn ($q) => $q->where('siswa_id', $siswaId))
            ->when($pendaftarId, fn ($q) => $q->where('pendaftar_id', $pendaftarId))
            ->where('status', '!=', 'batal')
            ->get()
            ->each(function ($t) {
                $terbayar = bcadd('0', (string) $t->alokasi()->sum('nominal'), 2);
                $t->update([
                    'terbayar' => $terbayar,
                    'status' => $t->isLunas()
                        ? 'lunas'
                        : (bccomp($terbayar, '0', 2) > 0 ? 'sebagian' : 'belum'),
                ]);
            });
    }

    private function resolveAkunLawan(int $entitasId): int
    {
        $pemetaan = PemetaanAkun::query()
            ->where('entitas_id', $entitasId)
            ->where('kunci', 'piutang_siswa')
            ->first();
        if ($pemetaan) {
            return $pemetaan->akun_id;
        }

        $pemetaan = PemetaanAkun::query()
            ->where('entitas_id', $entitasId)
            ->where('kunci', 'pendapatan_spp')
            ->first();
        if ($pemetaan) {
            return $pemetaan->akun_id;
        }

        throw new PembayaranException(
            'Akun lawan pembayaran siswa belum dipetakan. Atur pemetaan akun (piutang_siswa atau pendapatan_spp) terlebih dahulu.'
        );
    }
}
