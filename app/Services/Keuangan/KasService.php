<?php

namespace App\Services\Keuangan;

use App\Events\TransaksiKasDicatat;
use App\Models\Jurnal;
use App\Models\PeriodeAkuntansi;
use App\Models\RekeningKas;
use App\Models\TransaksiKas;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class KasService
{
    public function __construct(private JurnalService $jurnal) {}

    /** @return array{transaksi: TransaksiKas, jurnal: Jurnal|null} */
    public function masuk(array $data, User $penerima): array
    {
        return DB::transaction(function () use ($data, $penerima) {
            $this->validasiPeriode($data['entitas_id'], $data['tanggal']);
            $rekening = $this->ambilRekening($data['rekening_id'], $data['entitas_id']);

            $transaksi = TransaksiKas::create([
                'entitas_id' => $data['entitas_id'],
                'cabang_id' => $data['cabang_id'] ?? null,
                'rekening_id' => $rekening->id,
                'tanggal' => $data['tanggal'],
                'tipe' => 'masuk',
                'nominal' => $data['nominal'],
                'akun_lawan_id' => $data['akun_lawan_id'] ?? null,
                'keterangan' => $data['keterangan'] ?? null,
                'sumber_type' => $data['sumber_type'] ?? null,
                'sumber_id' => $data['sumber_id'] ?? null,
                'status' => 'posted',
            ]);

            $jurnal = $this->jurnalTransaksi($transaksi, $rekening, $data['akun_lawan_id'] ?? null, $penerima->id, 'masuk');
            $transaksi->update(['jurnal_id' => $jurnal?->id]);

            event(new TransaksiKasDicatat($transaksi->fresh()));

            return ['transaksi' => $transaksi->fresh(), 'jurnal' => $jurnal];
        });
    }

    /** @return array{transaksi: TransaksiKas, jurnal: Jurnal|null} */
    public function keluar(array $data, User $penerima): array
    {
        return DB::transaction(function () use ($data, $penerima) {
            $this->validasiPeriode($data['entitas_id'], $data['tanggal']);
            $rekening = $this->ambilRekening($data['rekening_id'], $data['entitas_id']);

            $transaksi = TransaksiKas::create([
                'entitas_id' => $data['entitas_id'],
                'cabang_id' => $data['cabang_id'] ?? null,
                'rekening_id' => $rekening->id,
                'tanggal' => $data['tanggal'],
                'tipe' => 'keluar',
                'nominal' => $data['nominal'],
                'akun_lawan_id' => $data['akun_lawan_id'] ?? null,
                'keterangan' => $data['keterangan'] ?? null,
                'sumber_type' => $data['sumber_type'] ?? null,
                'sumber_id' => $data['sumber_id'] ?? null,
                'status' => 'posted',
            ]);

            $jurnal = $this->jurnalTransaksi($transaksi, $rekening, $data['akun_lawan_id'] ?? null, $penerima->id, 'keluar');
            $transaksi->update(['jurnal_id' => $jurnal?->id]);

            event(new TransaksiKasDicatat($transaksi->fresh()));

            return ['transaksi' => $transaksi->fresh(), 'jurnal' => $jurnal];
        });
    }

    /** @return array{keluar: array{transaksi, jurnal}, masuk: array{transaksi, jurnal}} */
    public function transfer(array $data, User $penerima): array
    {
        return DB::transaction(function () use ($data, $penerima) {
            $this->validasiPeriode($data['entitas_id'], $data['tanggal']);
            $dari = $this->ambilRekening($data['rekening_id'], $data['entitas_id']);
            $ke = $this->ambilRekening($data['rekening_tujuan_id'], $data['entitas_id']);

            $nominal = $data['nominal'];

            $keluar = TransaksiKas::create([
                'entitas_id' => $data['entitas_id'],
                'cabang_id' => $data['cabang_id'] ?? null,
                'rekening_id' => $dari->id,
                'tanggal' => $data['tanggal'],
                'tipe' => 'transfer',
                'nominal' => $nominal,
                'rekening_tujuan_id' => $ke->id,
                'keterangan' => 'Transfer ke '.$ke->nama.': '.($data['keterangan'] ?? ''),
                'status' => 'posted',
            ]);

            $masuk = TransaksiKas::create([
                'entitas_id' => $data['entitas_id'],
                'cabang_id' => $data['cabang_id'] ?? null,
                'rekening_id' => $ke->id,
                'tanggal' => $data['tanggal'],
                'tipe' => 'transfer',
                'nominal' => $nominal,
                'rekening_tujuan_id' => $dari->id,
                'keterangan' => 'Transfer dari '.$dari->nama.': '.($data['keterangan'] ?? ''),
                'status' => 'posted',
            ]);

            // Satu jurnal seimbang untuk kedua kaki transfer: debit kas tujuan, kredit kas asal
            $jurnal = $this->jurnalTransfer($keluar, $dari, $ke, $penerima->id);
            $keluar->update(['jurnal_id' => $jurnal->id]);
            $masuk->update(['jurnal_id' => $jurnal->id]);

            event(new TransaksiKasDicatat($keluar->fresh()));
            event(new TransaksiKasDicatat($masuk->fresh()));

            return [
                'keluar' => ['transaksi' => $keluar->fresh(), 'jurnal' => $jurnal],
                'masuk' => ['transaksi' => $masuk->fresh(), 'jurnal' => $jurnal],
            ];
        });
    }

    public function batalkan(TransaksiKas $transaksi, int $userId, string $alasan): TransaksiKas
    {
        return DB::transaction(function () use ($transaksi, $userId, $alasan) {
            $terkunci = TransaksiKas::query()->lockForUpdate()->findOrFail($transaksi->id);

            if ($terkunci->status !== 'posted') {
                throw new RuntimeException('Hanya transaksi yang sudah dicatat yang dapat dibatalkan.');
            }

            if ($terkunci->jurnal_id) {
                $jurnal = Jurnal::query()->lockForUpdate()->findOrFail($terkunci->jurnal_id);
                if ($jurnal->status !== 'draft') {
                    app(JurnalService::class)->void($jurnal, $userId, $alasan);
                }
            }

            $terkunci->update(['status' => 'batal']);

            return $terkunci->fresh();
        });
    }

    public function saldo(RekeningKas $rekening): string
    {
        return $rekening->saldoAkhir();
    }

    private function validasiPeriode(int $entitasId, string $tanggal): void
    {
        $periode = PeriodeAkuntansi::query()
            ->where('entitas_id', $entitasId)
            ->where('tahun', (int) date('Y', strtotime($tanggal)))
            ->where('bulan', (int) date('n', strtotime($tanggal)))
            ->first();

        if ($periode && $periode->status !== 'terbuka') {
            throw new RuntimeException('Periode akuntansi '.$periode->tahun.'-'.$periode->bulan.' sudah ditutup.');
        }
    }

    private function ambilRekening(int $id, int $entitasId): RekeningKas
    {
        $rekening = RekeningKas::query()->where('id', $id)->where('entitas_id', $entitasId)->where('is_aktif', true)->first();
        if (!$rekening) {
            throw new RuntimeException('Rekening kas tidak ditemukan atau tidak aktif.');
        }
        return $rekening;
    }

    private function jurnalTransaksi(TransaksiKas $t, RekeningKas $r, ?int $akunLawan, int $userId, string $tipe): ?Jurnal
    {
        $akunKas = $r->akun_id;

        if ($tipe === 'masuk') {
            $baris = [
                ['akun_id' => $akunKas, 'debit' => $t->nominal, 'kredit' => 0],
                ['akun_id' => $akunLawan, 'debit' => 0, 'kredit' => $t->nominal],
            ];
        } elseif ($tipe === 'keluar') {
            $baris = [
                ['akun_id' => $akunLawan, 'debit' => $t->nominal, 'kredit' => 0],
                ['akun_id' => $akunKas, 'debit' => 0, 'kredit' => $t->nominal],
            ];
        } else {
            $baris = [
                ['akun_id' => $akunKas, 'debit' => 0, 'kredit' => $t->nominal],
            ];
        }

        if (!$akunLawan && $tipe !== 'transfer') {
            throw new RuntimeException('Akun lawan wajib diisi untuk transaksi '.$tipe);
        }

        $jurnal = app(JurnalService::class)->buatDraft([
            'entitas_id' => $t->entitas_id,
            'cabang_id' => $t->cabang_id,
            'tanggal' => $t->tanggal,
            'keterangan' => 'Transaksi kas '.$tipe.' ('.$t->id.')',
            'sumber_type' => TransaksiKas::class,
            'sumber_id' => $t->id,
            'dibuat_oleh' => $userId,
        ], $baris);

        return app(JurnalService::class)->posting($jurnal, $userId);
    }

    private function jurnalTransfer(TransaksiKas $t, RekeningKas $dari, RekeningKas $ke, int $userId): Jurnal
    {
        $jurnal = app(JurnalService::class)->buatDraft([
            'entitas_id' => $t->entitas_id,
            'cabang_id' => $t->cabang_id,
            'tanggal' => $t->tanggal,
            'keterangan' => 'Transfer kas '.$dari->nama.' ke '.$ke->nama.' ('.$t->id.')',
            'sumber_type' => TransaksiKas::class,
            'sumber_id' => $t->id,
            'dibuat_oleh' => $userId,
        ], [
            ['akun_id' => $ke->akun_id, 'debit' => $t->nominal, 'kredit' => 0],
            ['akun_id' => $dari->akun_id, 'debit' => 0, 'kredit' => $t->nominal],
        ]);

        return app(JurnalService::class)->posting($jurnal, $userId);
    }
}