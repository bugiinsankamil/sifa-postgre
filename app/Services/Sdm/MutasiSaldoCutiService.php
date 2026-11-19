<?php

namespace App\Services\Sdm;

use App\Exceptions\Sdm\SdmException;
use App\Models\JenisCuti;
use App\Models\MutasiSaldoCuti;
use App\Models\Pegawai;
use App\Models\PengajuanCuti;
use App\Models\SaldoCuti;
use App\Models\User;
use App\Support\CabangKonteks;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Manajemen saldo dan mutasi cuti.
 * Seluruh operasi saldo dilakukan dalam transaksi induk yang sudah mengunci baris.
 */
class MutasiSaldoCutiService
{
    /**
     * Ambil atau buat saldo untuk tahun tertentu. Harus dipanggil dalam transaksi
     * yang sudah mengunci induk (mis. pegawai atau pengajuan) untuk menghindari race condition.
     */
    public function ambilAtauBuat(Pegawai $pegawai, JenisCuti $jenis, int $tahun): SaldoCuti
    {
        $saldo = SaldoCuti::query()
            ->where('pegawai_id', $pegawai->id)
            ->where('jenis_cuti_id', $jenis->id)
            ->where('tahun', $tahun)
            ->lockForUpdate()
            ->first();

        if ($saldo !== null) {
            return $saldo;
        }

        $kuota = (string) ($jenis->kuota_hari_tahunan ?? 0);
        $kuota = bcadd($kuota, '0', 1);
        $dibawa = '0.0';

        // Carry-over: seluruh sisa saldo tahun sebelumnya dibawa (belum ada batas)
        $saldoSebelumnya = SaldoCuti::query()
            ->where('pegawai_id', $pegawai->id)
            ->where('jenis_cuti_id', $jenis->id)
            ->where('tahun', $tahun - 1)
            ->first();

        if ($saldoSebelumnya !== null && bccomp($saldoSebelumnya->sisa(), '0', 1) > 0) {
            $dibawa = $saldoSebelumnya->sisa();
        }

        $saldo = SaldoCuti::create([
            'pegawai_id' => $pegawai->id,
            'jenis_cuti_id' => $jenis->id,
            'tahun' => $tahun,
            'kuota' => $kuota,
            'terpakai' => '0.0',
            'dibawa' => $dibawa,
        ]);

        $this->catatMutasi(
            pegawai: $pegawai,
            jenis: $jenis,
            tahun: $tahun,
            tipe: 'pemberian',
            jumlahHari: $kuota,
            saldoSebelum: '0.0',
            saldoSesudah: $kuota,
            keterangan: 'Pemberian kuota tahunan',
        );

        if (bccomp($dibawa, '0', 1) > 0) {
            $this->catatMutasi(
                pegawai: $pegawai,
                jenis: $jenis,
                tahun: $tahun,
                tipe: 'carry_over',
                jumlahHari: $dibawa,
                saldoSebelum: $kuota,
                saldoSesudah: bcadd($kuota, $dibawa, 1),
                keterangan: 'Carry-over dari tahun '.($tahun - 1),
            );
        }

        return $saldo;
    }

    /**
     * Potong saldo untuk pengajuan yang sudah dikunci.
     *
     * @param  array<int, string>  $pemakaianPerTahun
     */
    public function potong(PengajuanCuti $pengajuan, array $pemakaianPerTahun, ?User $pelaku = null): void
    {
        foreach ($pemakaianPerTahun as $tahun => $jumlahHari) {
            $saldo = $this->ambilAtauBuat($pengajuan->pegawai, $pengajuan->jenisCuti, $tahun);
            $sisa = $saldo->sisa();

            if (bccomp($jumlahHari, $sisa, 1) > 0) {
                throw new SdmException("Sisa cuti tahun {$tahun} tidak mencukupi. Sisa: {$sisa} hari, diajukan: {$jumlahHari} hari.");
            }

            $sebelum = $saldo->sisa();
            $saldo->increment('terpakai', $jumlahHari);
            $saldo->refresh();

            $this->catatMutasi(
                pegawai: $pengajuan->pegawai,
                jenis: $pengajuan->jenisCuti,
                tahun: $tahun,
                tipe: 'pemakaian',
                jumlahHari: $jumlahHari,
                saldoSebelum: $sebelum,
                saldoSesudah: $saldo->sisa(),
                pengajuan: $pengajuan,
                pelaku: $pelaku,
                keterangan: "Pemakaian cuti #{$pengajuan->id}",
            );
        }
    }

    /**
     * Kembalikan saldo saat pembatalan berdasarkan rincian hari pengajuan.
     */
    public function kembalikan(PengajuanCuti $pengajuan, ?User $pelaku = null): void
    {
        $perTahun = [];
        foreach ($pengajuan->hari as $hari) {
            if ($hari->status !== 'terhitung') {
                continue;
            }
            $tahun = $hari->tahun;
            $perTahun[$tahun] = bcadd($perTahun[$tahun] ?? '0.0', (string) $hari->nilai, 1);
        }

        foreach ($perTahun as $tahun => $jumlahHari) {
            $saldo = SaldoCuti::query()
                ->where('pegawai_id', $pengajuan->pegawai_id)
                ->where('jenis_cuti_id', $pengajuan->jenis_cuti_id)
                ->where('tahun', $tahun)
                ->lockForUpdate()
                ->first();

            if ($saldo === null) {
                continue;
            }

            $sebelum = $saldo->sisa();
            $saldo->decrement('terpakai', $jumlahHari);
            $saldo->refresh();

            $this->catatMutasi(
                pegawai: $pengajuan->pegawai,
                jenis: $pengajuan->jenisCuti,
                tahun: $tahun,
                tipe: 'pengembalian',
                jumlahHari: $jumlahHari,
                saldoSebelum: $sebelum,
                saldoSesudah: $saldo->sisa(),
                pengajuan: $pengajuan,
                pelaku: $pelaku,
                keterangan: "Pengembalian cuti #{$pengajuan->id} karena pembatalan",
            );
        }
    }

    /**
     * Sesuaikan sisa saldo secara manual tanpa mengubah histori.
     */
    public function sesuaikan(SaldoCuti $saldo, string $saldoBaru, string $alasan, ?User $pelaku = null): SaldoCuti
    {
        $this->pastikanBolehMenyesuaikan($saldo);
        $alasan = trim($alasan);
        if ($alasan === '') {
            throw new SdmException('Alasan penyesuaian saldo wajib diisi.');
        }

        $saldoBaru = bcadd($saldoBaru, '0', 1);
        if (bccomp($saldoBaru, '0', 1) < 0) {
            throw new SdmException('Sisa saldo tidak boleh negatif.');
        }

        return DB::transaction(function () use ($saldo, $saldoBaru, $alasan, $pelaku) {
            $saldo = SaldoCuti::query()->lockForUpdate()->findOrFail($saldo->id);
            $sebelum = $saldo->sisa();
            if (bccomp($saldoBaru, $sebelum, 1) === 0) {
                throw new SdmException('Sisa saldo sudah sesuai nilai yang dimasukkan.');
            }

            $saldo->kuota = bcadd($saldoBaru, (string) $saldo->terpakai, 1);
            $saldo->dibawa = '0.0';
            $saldo->save();
            $this->catatMutasi(
                pegawai: $saldo->pegawai,
                jenis: $saldo->jenisCuti,
                tahun: $saldo->tahun,
                tipe: 'penyesuaian',
                jumlahHari: bcsub($saldoBaru, $sebelum, 1),
                saldoSebelum: $sebelum,
                saldoSesudah: $saldoBaru,
                pelaku: $pelaku,
                keterangan: $alasan,
            );

            return $saldo->refresh();
        });
    }

    private function pastikanBolehMenyesuaikan(SaldoCuti $saldo): void
    {
        $cabangIds = app(CabangKonteks::class)->cabangDiizinkan();
        if ($cabangIds === null) {
            return;
        }

        $terdaftar = $saldo->pegawai->penugasan()->whereIn('cabang_id', $cabangIds)->exists();
        if (! $terdaftar) {
            throw new SdmException('Anda tidak berhak menyesuaikan saldo pegawai cabang lain.');
        }
    }

    /**
     * Catat mutasi saldo append-only.
     */
    private function catatMutasi(
        Pegawai $pegawai,
        JenisCuti $jenis,
        int $tahun,
        string $tipe,
        string $jumlahHari,
        string $saldoSebelum,
        string $saldoSesudah,
        ?PengajuanCuti $pengajuan = null,
        ?User $pelaku = null,
        ?string $keterangan = null,
    ): MutasiSaldoCuti {
        return MutasiSaldoCuti::create([
            'pegawai_id' => $pegawai->id,
            'jenis_cuti_id' => $jenis->id,
            'tahun' => $tahun,
            'tipe' => $tipe,
            'jumlah_hari' => $jumlahHari,
            'saldo_sebelum' => $saldoSebelum,
            'saldo_sesudah' => $saldoSesudah,
            'pengajuan_cuti_id' => $pengajuan?->id,
            'dibuat_oleh' => $pelaku?->id,
            'keterangan' => $keterangan,
            'kode_unik' => $this->kodeUnik($pegawai->id, $jenis->id, $tahun, $tipe, $pengajuan?->id),
        ]);
    }

    /**
     * Kode deterministik: operasi yang sama untuk sumber yang sama menghasilkan kode yang sama,
     * sehingga unique index menjadi pengaman terakhir terhadap mutasi ganda.
     */
    /** Pastikan mutasi unik per operasi sumber dan per penyesuaian manual. */
    private function kodeUnik(int $pegawaiId, int $jenisCutiId, int $tahun, string $tipe, ?int $pengajuanId): string
    {
        $sumber = $tipe === 'penyesuaian' ? Str::orderedUuid()->toString() : $pengajuanId ?? '-';

        return hash('sha256', implode('|', [$pegawaiId, $jenisCutiId, $tahun, $tipe, $sumber]));
    }
}
