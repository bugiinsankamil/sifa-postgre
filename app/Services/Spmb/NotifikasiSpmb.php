<?php

namespace App\Services\Spmb;

use App\Models\SpmbBerkas;
use App\Models\SpmbPendaftar;
use App\Models\SpmbPendaftarWali;
use App\Services\Pesan\PengelolaPesan;
use Illuminate\Support\Facades\DB;

/**
 * Menyusun dan mengantrekan pesan SPMB untuk kontak utama (orang tua/wali) pendaftar.
 * Kunci unik per peristiwa mencegah pesan ganda bila peristiwanya terpicu ulang.
 */
class NotifikasiSpmb
{
    public function __construct(private readonly PengelolaPesan $pesan)
    {
    }

    public function terdaftar(SpmbPendaftar $p): void
    {
        $this->kirim($p, 'spmb_terdaftar', "spmb_terdaftar:{$p->id}");
    }

    public function terverifikasi(SpmbPendaftar $p): void
    {
        $this->kirim($p, 'spmb_terverifikasi', "spmb_terverifikasi:{$p->id}");
    }

    public function berkasDitolak(SpmbBerkas $berkas): void
    {
        $p = SpmbPendaftar::query()->withoutGlobalScopes()->find($berkas->pendaftar_id);
        if ($p === null) {
            return;
        }

        // Lampiran aktif berganti setiap berkas diunggah ulang, jadi penolakan berikutnya tetap menghasilkan pesan baru
        $lampiranId = (int) $berkas->lampiran()->value('id');
        $namaBerkas = DB::table('spmb_persyaratan_berkas')->where('id', $berkas->persyaratan_id)->value('nama');

        $this->kirim($p, 'spmb_berkas_ditolak', "spmb_berkas_ditolak:{$berkas->id}:{$lampiranId}", [
            'berkas' => (string) $namaBerkas,
            'alasan' => (string) $berkas->catatan,
        ]);
    }

    private function kirim(SpmbPendaftar $p, string $kode, string $kunci, array $tambahan = []): void
    {
        $wali = SpmbPendaftarWali::query()->where('pendaftar_id', $p->id)->where('is_kontak_utama', true)->first();
        if ($wali === null || blank($wali->telepon)) {
            return;
        }

        $this->pesan->antri($kode, $wali->telepon, [
            'nama_wali' => $wali->nama,
            'nama_anak' => $p->nama_lengkap,
            'sekolah' => (string) DB::table('sekolah')->where('id', $p->sekolah_id)->value('nama'),
            'nomor' => $p->nomor_pendaftaran,
            'url_status' => route('spmb.status'),
        ] + $tambahan, [
            'cabang_id' => $p->cabang_id,
            'kunci_unik' => $kunci,
            'konteks_type' => $p->getMorphClass(),
            'konteks_id' => $p->id,
        ]);
    }
}
