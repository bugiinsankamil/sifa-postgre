<?php

namespace App\Services\Sdm;

use App\Exceptions\Sdm\SdmException;
use App\Models\AbsensiPegawai;
use App\Models\JenisCuti;
use App\Models\Pegawai;
use App\Models\PengajuanCuti;
use App\Models\PengajuanIzin;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pengajuan dan persetujuan cuti atomik: rincian hari, saldo per tahun, persetujuan bertingkat.
 * Alur: diajukan → disetujui_atasan → disetujui. Pembatalan lewat OtorisasiPengajuan.
 */
class CutiService
{
    /**
     * Buat pengajuan cuti (status diajukan). Jumlah hari dihitung dari shift + kalender libur.
     *
     * @param  array<string, string>  $sesiPerTanggal  Map tanggal => 'penuh'|'pagi'|'siang'.
     */
    public function ajukan(
        Pegawai $pegawai,
        JenisCuti $jenis,
        string $tanggalMulai,
        string $tanggalSelesai,
        ?string $alasan = null,
        ?Pegawai $pengganti = null,
        array $sesiPerTanggal = [],
        ?User $pengaju = null,
    ): PengajuanCuti {
        if (! $jenis->is_aktif) {
            throw new SdmException('Jenis cuti tidak aktif.');
        }

        if ($tanggalSelesai < $tanggalMulai) {
            throw new SdmException('Tanggal selesai tidak boleh sebelum tanggal mulai.');
        }

        if (Carbon::parse($tanggalMulai)->lt(today())) {
            throw new SdmException('Pengajuan cuti untuk tanggal yang sudah lewat tidak diizinkan.');
        }

        return DB::transaction(function () use ($pegawai, $jenis, $tanggalMulai, $tanggalSelesai, $alasan, $pengganti, $sesiPerTanggal, $pengaju) {
            // Kunci pegawai agar pengajuan paralel pegawai yang sama berurutan
            Pegawai::query()->lockForUpdate()->findOrFail($pegawai->id);

            $penugasan = app(AbsensiService::class)->penugasanAktifPada($pegawai, $tanggalMulai, false);
            if ($penugasan === null) {
                throw new SdmException('Pegawai tidak memiliki penugasan aktif pada tanggal '.$tanggalMulai.'.');
            }

            if ($pengaju !== null) {
                app(OtorisasiPengajuan::class)->pastikanBolehMengajukan($pengaju, $pegawai, $penugasan->cabang_id);
            }

            $this->pastikanTanpaBenturan($pegawai, $tanggalMulai, $tanggalSelesai);

            $hitung = app(PerhitunganCutiIzin::class)->hitung($pegawai, $tanggalMulai, $tanggalSelesai, $penugasan->cabang, $sesiPerTanggal);
            if (bccomp($hitung['jumlah_hari'], '0', 1) <= 0) {
                throw new SdmException('Tidak ada hari kerja yang terhitung pada rentang tanggal tersebut.');
            }

            if ($jenis->kuota_hari_tahunan !== null) {
                $this->pastikanSaldoCukup($pegawai, $jenis, $hitung['per_tahun']);
            }

            $pengajuan = PengajuanCuti::query()->create([
                'pegawai_id' => $pegawai->id,
                'cabang_id' => $penugasan->cabang_id,
                'jenis_cuti_id' => $jenis->id,
                'tanggal_mulai' => $tanggalMulai,
                'tanggal_selesai' => $tanggalSelesai,
                'jumlah_hari' => $hitung['jumlah_hari'],
                'jumlah_setengah_hari' => $hitung['jumlah_setengah_hari'],
                'alasan' => $alasan,
                'pengganti_id' => $pengganti?->id,
                'status' => 'diajukan',
            ]);

            foreach ($hitung['rincian'] as $baris) {
                $pengajuan->hari()->create($baris);
            }

            return $pengajuan->load('hari');
        });
    }

    /** Persetujuan tingkat 1: atasan langsung (kepala unit kerja). diajukan → disetujui_atasan. */
    public function ubahPengajuan(PengajuanCuti $pengajuan, ?string $alasan, ?Pegawai $pengganti): PengajuanCuti
    {
        return DB::transaction(function () use ($pengajuan, $alasan, $pengganti) {
            $terkunci = PengajuanCuti::query()->lockForUpdate()->findOrFail($pengajuan->id);

            if ($terkunci->status !== 'diajukan') {
                throw new SdmException('Pengajuan cuti yang sudah diproses tidak dapat diubah.');
            }

            $terkunci->update(['alasan' => $alasan, 'pengganti_id' => $pengganti?->id]);

            return $terkunci;
        });
    }

    public function setujuiAtasan(PengajuanCuti $pengajuan, User $penyetuju): PengajuanCuti
    {
        return DB::transaction(function () use ($pengajuan, $penyetuju) {
            $terkunci = PengajuanCuti::query()->lockForUpdate()->findOrFail($pengajuan->id);
            if ($terkunci->status !== 'diajukan') {
                throw new SdmException('Pengajuan cuti sudah diproses.');
            }

            app(OtorisasiPengajuan::class)->pastikanAtasan($terkunci, $penyetuju);

            $terkunci->update([
                'status' => 'disetujui_atasan',
                'disetujui_atasan_oleh' => $penyetuju->id,
                'disetujui_atasan_pada' => now(),
            ]);

            return $terkunci;
        });
    }

    /**
     * Persetujuan tingkat 2 (final): kepala cabang/HR. disetujui_atasan → disetujui.
     * Langsung dari diajukan hanya bila pemohon tidak punya atasan lain (tanpa kepala unit, atau pemohon sendiri kepalanya).
     * Memotong saldo per tahun, mencatat mutasi, dan menandai absensi (menimpa, sumber tercatat).
     */
    public function setujuiFinal(PengajuanCuti $pengajuan, User $penyetuju): PengajuanCuti
    {
        return DB::transaction(function () use ($pengajuan, $penyetuju) {
            $terkunci = PengajuanCuti::query()->lockForUpdate()->findOrFail($pengajuan->id);
            if (! in_array($terkunci->status, ['diajukan', 'disetujui_atasan'], true)) {
                throw new SdmException('Pengajuan cuti sudah diproses.');
            }

            $otorisasi = app(OtorisasiPengajuan::class);
            $otorisasi->pastikanPersetujuanFinal($terkunci, $penyetuju);

            if ($terkunci->status === 'diajukan') {
                $atasan = $otorisasi->atasanLangsung($terkunci->pegawai, $terkunci->tanggal_mulai->toDateString());
                if ($atasan !== null && $atasan->id !== $terkunci->pegawai_id) {
                    throw new SdmException('Pengajuan cuti harus disetujui atasan langsung terlebih dahulu.');
                }
            }

            $terkunci->load('jenisCuti', 'pegawai', 'hari');

            if ($terkunci->jenisCuti->kuota_hari_tahunan !== null) {
                app(MutasiSaldoCutiService::class)->potong($terkunci, $this->pemakaianPerTahun($terkunci), $penyetuju);
            }

            $terkunci->update([
                'status' => 'disetujui',
                'disetujui_oleh' => $penyetuju->id,
                'disetujui_pada' => now(),
            ]);

            $this->tandaiAbsensiCuti($terkunci);

            return $terkunci;
        });
    }

    /** Tolak oleh persetujuan final (kepala cabang/HR). Alasan penolakan wajib. */
    public function tolak(PengajuanCuti $pengajuan, User $penyetuju, ?string $alasanPenolakan = null): PengajuanCuti
    {
        if (blank($alasanPenolakan)) {
            throw new SdmException('Alasan penolakan wajib diisi.');
        }

        return DB::transaction(function () use ($pengajuan, $penyetuju, $alasanPenolakan) {
            $terkunci = PengajuanCuti::query()->lockForUpdate()->findOrFail($pengajuan->id);
            if (! in_array($terkunci->status, ['diajukan', 'disetujui_atasan'], true)) {
                throw new SdmException('Pengajuan cuti sudah diproses.');
            }

            app(OtorisasiPengajuan::class)->pastikanPenolakan($terkunci, $penyetuju);

            $terkunci->update([
                'status' => 'ditolak',
                'disetujui_oleh' => $penyetuju->id,
                'disetujui_pada' => now(),
                'alasan_penolakan' => $alasanPenolakan,
            ]);

            return $terkunci;
        });
    }

    /**
     * Batalkan: pemohon (status diajukan) atau kepala cabang (semua status aktif). Alasan wajib.
     * Bila sudah disetujui: saldo dikembalikan (mutasi pengembalian) dan absensi cuti milik pengajuan dihapus.
     */
    public function batalkan(PengajuanCuti $pengajuan, User $pembatal, ?string $alasan = null): PengajuanCuti
    {
        if (blank($alasan)) {
            throw new SdmException('Alasan pembatalan wajib diisi.');
        }

        return DB::transaction(function () use ($pengajuan, $pembatal, $alasan) {
            $terkunci = PengajuanCuti::query()->lockForUpdate()->findOrFail($pengajuan->id);
            if (in_array($terkunci->status, ['ditolak', 'dibatalkan'], true)) {
                throw new SdmException('Pengajuan cuti sudah berstatus akhir ('.$terkunci->status.').');
            }

            app(OtorisasiPengajuan::class)->pastikanPembatalan($terkunci, $pembatal);

            if ($terkunci->status === 'disetujui') {
                $terkunci->load('jenisCuti', 'pegawai', 'hari');

                if ($terkunci->jenisCuti->kuota_hari_tahunan !== null) {
                    app(MutasiSaldoCutiService::class)->kembalikan($terkunci, $pembatal);
                }

                AbsensiPegawai::query()
                    ->withoutGlobalScopes()
                    ->where('pengajuan_cuti_id', $terkunci->id)
                    ->delete();
            }

            $terkunci->update([
                'status' => 'dibatalkan',
                'dibatalkan_oleh' => $pembatal->id,
                'dibatalkan_pada' => now(),
                'alasan_pembatalan' => $alasan,
            ]);

            return $terkunci;
        });
    }

    /** Cek benturan dengan pengajuan cuti dan izin yang masih berlaku. */
    private function pastikanTanpaBenturan(Pegawai $pegawai, string $tanggalMulai, string $tanggalSelesai, ?int $excludePengajuanCutiId = null): void
    {
        $tumpukCuti = PengajuanCuti::query()
            ->where('pegawai_id', $pegawai->id)
            ->whereIn('status', ['diajukan', 'disetujui_atasan', 'disetujui'])
            ->where('tanggal_mulai', '<=', $tanggalSelesai)
            ->where('tanggal_selesai', '>=', $tanggalMulai)
            ->when($excludePengajuanCutiId, fn ($q) => $q->where('id', '!=', $excludePengajuanCutiId))
            ->exists();

        if ($tumpukCuti) {
            throw new SdmException('Sudah ada pengajuan cuti pada rentang tanggal tersebut.');
        }

        $tumpukIzin = PengajuanIzin::query()
            ->where('pegawai_id', $pegawai->id)
            ->whereIn('status', ['diajukan', 'disetujui_atasan', 'disetujui'])
            ->where('tanggal_mulai', '<=', $tanggalSelesai)
            ->where('tanggal_selesai', '>=', $tanggalMulai)
            ->exists();

        if ($tumpukIzin) {
            throw new SdmException('Sudah ada pengajuan izin pada rentang tanggal tersebut.');
        }
    }

    /** Validasi saldo mencukupi untuk setiap tahun (tanpa mengunci saldo, hanya dicek). */
    private function pastikanSaldoCukup(Pegawai $pegawai, JenisCuti $jenis, array $pemakaianPerTahun): void
    {
        foreach ($pemakaianPerTahun as $tahun => $jumlahHari) {
            $saldo = app(MutasiSaldoCutiService::class)->ambilAtauBuat($pegawai, $jenis, $tahun);
            $sisa = $saldo->sisa();

            if (bccomp($jumlahHari, $sisa, 1) > 0) {
                throw new SdmException("Sisa cuti tahun {$tahun} tidak mencukupi. Sisa: {$sisa} hari, diajukan: {$jumlahHari} hari.");
            }
        }
    }

    /** Hitung pemakaian per tahun dari rincian hari (hanya status terhitung). */
    private function pemakaianPerTahun(PengajuanCuti $pengajuan): array
    {
        $per = [];
        foreach ($pengajuan->hari as $h) {
            if ($h->status === 'terhitung') {
                $per[$h->tahun] = bcadd($per[$h->tahun] ?? '0.0', (string) $h->nilai, 1);
            }
        }

        return $per;
    }

    /** Tandai absensi pegawai untuk setiap hari terhitung (menimpa absensi lama, sumber pengajuan tercatat). */
    private function tandaiAbsensiCuti(PengajuanCuti $pengajuan): void
    {
        $absensiSvc = app(AbsensiService::class);

        foreach ($pengajuan->hari as $h) {
            if ($h->status !== 'terhitung') {
                continue;
            }

            $tanggal = $h->tanggal->toDateString();
            $penugasan = $absensiSvc->penugasanAktifPada($pengajuan->pegawai, $tanggal, false);
            if ($penugasan === null) {
                continue;
            }

            // catatManual sudah menangani kunci absensi_pegawai dan validasi bahwa absensi cuti/izin tidak diubah manual
            $absensi = $absensiSvc->catatManual($pengajuan->pegawai, $tanggal, [
                'status' => 'cuti',
                'keterangan' => 'Cuti '.$pengajuan->jenisCuti->nama.' (#'.$pengajuan->id.')',
                'pengajuan_cuti_id' => $pengajuan->id,
            ], $pengajuan->disetujui_oleh);

            $h->update(['absensi_pegawai_id' => $absensi->id]);
        }
    }
}

