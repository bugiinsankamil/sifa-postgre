<?php

namespace App\Services\Sdm;

use App\Exceptions\Sdm\SdmException;
use App\Models\AbsensiPegawai;
use App\Models\Pegawai;
use App\Models\PengajuanCuti;
use App\Models\PengajuanIzin;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Pengajuan dan persetujuan izin/sakit/dinas luar atomik dengan persetujuan bertingkat.
 * Alur: diajukan → disetujui_atasan → disetujui. Tanpa pemotongan saldo.
 */
class IzinService
{
    /**
     * Buat pengajuan izin (status diajukan). Jumlah hari dihitung dari shift + kalender libur.
     *
     * @param  string  $jenis  'izin'|'sakit'|'dinas_luar'
     * @param  array<string, string>  $sesiPerTanggal  Map tanggal => 'penuh'|'pagi'|'siang'.
     */
    public function ajukan(
        Pegawai $pegawai,
        string $jenis,
        string $tanggalMulai,
        string $tanggalSelesai,
        ?string $alasan = null,
        array $sesiPerTanggal = [],
        ?User $pengaju = null,
    ): PengajuanIzin {
        if (! in_array($jenis, ['izin', 'sakit', 'dinas_luar'], true)) {
            throw new SdmException('Jenis izin tidak dikenali: '.$jenis.'.');
        }

        if ($tanggalSelesai < $tanggalMulai) {
            throw new SdmException('Tanggal selesai tidak boleh sebelum tanggal mulai.');
        }

        if (\Carbon\Carbon::parse($tanggalMulai)->lt(today())) {
            throw new SdmException('Pengajuan izin untuk tanggal yang sudah lewat tidak diizinkan.');
        }

        return DB::transaction(function () use ($pegawai, $jenis, $tanggalMulai, $tanggalSelesai, $alasan, $sesiPerTanggal, $pengaju) {
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

            $pengajuan = PengajuanIzin::query()->create([
                'pegawai_id' => $pegawai->id,
                'cabang_id' => $penugasan->cabang_id,
                'jenis' => $jenis,
                'tanggal_mulai' => $tanggalMulai,
                'tanggal_selesai' => $tanggalSelesai,
                'jumlah_hari' => $hitung['jumlah_hari'],
                'jumlah_setengah_hari' => $hitung['jumlah_setengah_hari'],
                'alasan' => $alasan,
                'status' => 'diajukan',
            ]);

            foreach ($hitung['rincian'] as $baris) {
                $pengajuan->hari()->create($baris);
            }

            return $pengajuan->load('hari');
        });
    }


    /** Persetujuan tingkat 1: atasan langsung. diajukan → disetujui_atasan. */
    public function ubahPengajuan(PengajuanIzin $pengajuan, ?string $alasan): PengajuanIzin
    {
        return DB::transaction(function () use ($pengajuan, $alasan) {
            $terkunci = PengajuanIzin::query()->lockForUpdate()->findOrFail($pengajuan->id);

            if ($terkunci->status !== 'diajukan') {
                throw new SdmException('Pengajuan izin yang sudah diproses tidak dapat diubah.');
            }

            $terkunci->update(['alasan' => $alasan]);

            return $terkunci;
        });
    }

    public function setujuiAtasan(PengajuanIzin $pengajuan, User $penyetuju): PengajuanIzin
    {
        return DB::transaction(function () use ($pengajuan, $penyetuju) {
            $terkunci = PengajuanIzin::query()->lockForUpdate()->findOrFail($pengajuan->id);
            if ($terkunci->status !== 'diajukan') {
                throw new SdmException('Pengajuan izin sudah diproses.');
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
     * Langsung dari diajukan hanya bila pemohon tidak punya atasan lain.
     * Menandai absensi izin/sakit/dinas (menimpa, sumber tercatat).
     */
    public function setujuiFinal(PengajuanIzin $pengajuan, User $penyetuju): PengajuanIzin
    {
        return DB::transaction(function () use ($pengajuan, $penyetuju) {
            $terkunci = PengajuanIzin::query()->lockForUpdate()->findOrFail($pengajuan->id);
            if (! in_array($terkunci->status, ['diajukan', 'disetujui_atasan'], true)) {
                throw new SdmException('Pengajuan izin sudah diproses.');
            }

            $otorisasi = app(OtorisasiPengajuan::class);
            $otorisasi->pastikanPersetujuanFinal($terkunci, $penyetuju);

            if ($terkunci->status === 'diajukan') {
                $atasan = $otorisasi->atasanLangsung($terkunci->pegawai, $terkunci->tanggal_mulai->toDateString());
                if ($atasan !== null && $atasan->id !== $terkunci->pegawai_id) {
                    throw new SdmException('Pengajuan izin harus disetujui atasan langsung terlebih dahulu.');
                }
            }

            $terkunci->load('pegawai', 'hari');

            $terkunci->update([
                'status' => 'disetujui',
                'disetujui_oleh' => $penyetuju->id,
                'disetujui_pada' => now(),
            ]);

            $this->tandaiAbsensiIzin($terkunci);

            return $terkunci;
        });
    }

    /** Tolak oleh persetujuan final (kepala cabang/HR). Alasan penolakan wajib. */
    public function tolak(PengajuanIzin $pengajuan, User $penyetuju, ?string $alasanPenolakan = null): PengajuanIzin
    {
        if (blank($alasanPenolakan)) {
            throw new SdmException('Alasan penolakan wajib diisi.');
        }

        return DB::transaction(function () use ($pengajuan, $penyetuju, $alasanPenolakan) {
            $terkunci = PengajuanIzin::query()->lockForUpdate()->findOrFail($pengajuan->id);
            if (! in_array($terkunci->status, ['diajukan', 'disetujui_atasan'], true)) {
                throw new SdmException('Pengajuan izin sudah diproses.');
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
     * Batalkan: pemohon (status diajukan) atau kepala cabang (semua aktif). Alasan wajib.
     * Bila sudah disetujui, absensi izin milik pengajuan dihapus.
     */
    public function batalkan(PengajuanIzin $pengajuan, User $pembatal, ?string $alasan = null): PengajuanIzin
    {
        if (blank($alasan)) {
            throw new SdmException('Alasan pembatalan wajib diisi.');
        }

        return DB::transaction(function () use ($pengajuan, $pembatal, $alasan) {
            $terkunci = PengajuanIzin::query()->lockForUpdate()->findOrFail($pengajuan->id);
            if (in_array($terkunci->status, ['ditolak', 'dibatalkan'], true)) {
                throw new SdmException('Pengajuan izin sudah berstatus akhir ('.$terkunci->status.').');
            }

            app(OtorisasiPengajuan::class)->pastikanPembatalan($terkunci, $pembatal);

            if ($terkunci->status === 'disetujui') {
                AbsensiPegawai::query()
                    ->withoutGlobalScopes()
                    ->where('pengajuan_izin_id', $terkunci->id)
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
    private function pastikanTanpaBenturan(Pegawai $pegawai, string $tanggalMulai, string $tanggalSelesai): void
    {
        $tumpukCuti = PengajuanCuti::query()
            ->where('pegawai_id', $pegawai->id)
            ->whereIn('status', ['diajukan', 'disetujui_atasan', 'disetujui'])
            ->where('tanggal_mulai', '<=', $tanggalSelesai)
            ->where('tanggal_selesai', '>=', $tanggalMulai)
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

    /** Tandai absensi pegawai untuk setiap hari terhitung (menimpa absensi lama, sumber pengajuan tercatat). */
    private function tandaiAbsensiIzin(PengajuanIzin $pengajuan): void
    {
        $statusAbsensi = match ($pengajuan->jenis) {
            'sakit' => 'sakit',
            'dinas_luar' => 'dinas',
            default => 'izin',
        };

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

            $absensi = $absensiSvc->catatManual($pengajuan->pegawai, $tanggal, [
                'status' => $statusAbsensi,
                'keterangan' => ucfirst($pengajuan->jenis).' (#'.$pengajuan->id.')',
                'pengajuan_izin_id' => $pengajuan->id,
            ], $pengajuan->disetujui_oleh);

            $h->update(['absensi_pegawai_id' => $absensi->id]);
        }
    }
}

