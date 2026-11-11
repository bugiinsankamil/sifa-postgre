<?php

namespace App\Services\Spmb;

use App\Exceptions\Spmb\KonversiTidakBisa;
use App\Models\Orang;
use App\Models\Rombel;
use App\Models\RombelSiswa;
use App\Models\Siswa;
use App\Models\SiswaSekolah;
use App\Models\SiswaWali;
use App\Models\SpmbKontak;
use App\Models\SpmbPendaftar;
use App\Models\SpmbPendaftarWali;
use App\Models\SpmbPeriode;
use App\Services\PenomoranDokumen;
use App\Support\Telepon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Mengubah pendaftar yang lulus dan sudah daftar ulang menjadi siswa, dalam satu transaksi:
 * orang (anak dan wali), siswa, siswa_sekolah, siswa_wali, dan (bila rombel diketahui) rombel_siswa.
 *
 * Syarat: spmb_hasil.keputusan = lulus, spmb_daftar_ulang.status = selesai, belum pernah dikonversi.
 * Orang yang NIK-nya sudah ada dipakai ulang, sehingga wali kakak-adik tidak terduplikasi dan anak yang
 * naik jenjang (TK ke SD) tetap memakai satu baris siswa dengan NIS yayasan yang sama.
 */
class KonversiPendaftar
{
    public function __construct(private readonly PenomoranDokumen $penomoran)
    {
    }

    /** @throws KonversiTidakBisa */
    public function konversi(SpmbPendaftar $pendaftar, ?int $rombelId = null, ?Carbon $tanggalMasuk = null): Siswa
    {
        return DB::transaction(function () use ($pendaftar, $rombelId, $tanggalMasuk) {
            // Dikunci agar dua klik atau dua proses tidak mengonversi pendaftar yang sama bersamaan
            $p = SpmbPendaftar::query()->lockForUpdate()->findOrFail($pendaftar->id);

            if ($p->siswa_id !== null) {
                throw new KonversiTidakBisa("Pendaftar {$p->nomor_pendaftaran} sudah dikonversi menjadi siswa.");
            }

            $hasil = DB::table('spmb_hasil')->where('pendaftar_id', $p->id)->first();
            if ($hasil === null || $hasil->keputusan !== 'lulus') {
                throw new KonversiTidakBisa("Pendaftar {$p->nomor_pendaftaran} belum dinyatakan lulus.");
            }

            $daftarUlang = DB::table('spmb_daftar_ulang')->where('pendaftar_id', $p->id)->first();
            if ($daftarUlang === null || $daftarUlang->status !== 'selesai') {
                throw new KonversiTidakBisa("Pendaftar {$p->nomor_pendaftaran} belum menyelesaikan daftar ulang.");
            }

            $wali = SpmbPendaftarWali::query()->where('pendaftar_id', $p->id)->orderBy('id')->get();
            if ($wali->isEmpty()) {
                throw new KonversiTidakBisa("Pendaftar {$p->nomor_pendaftaran} belum memiliki data orang tua/wali.");
            }

            $periode = SpmbPeriode::query()->withoutGlobalScopes()->findOrFail($p->periode_id);
            $tanggalMasuk ??= $daftarUlang->tanggal ? Carbon::parse($daftarUlang->tanggal) : today();
            $rombel = $this->rombelTujuan($rombelId ?? $daftarUlang->rombel_id, $p, $periode);

            [$siswa, $jenisMasuk] = $this->siapkanSiswa($p, $tanggalMasuk);

            SiswaSekolah::create([
                'siswa_id' => $siswa->id,
                'sekolah_id' => $p->sekolah_id,
                'cabang_id' => $p->cabang_id,
                'tanggal_masuk' => $tanggalMasuk,
                'jenis_masuk' => $jenisMasuk,
                'asal_sekolah' => $jenisMasuk === 'baru' ? $p->asal_sekolah : null,
            ]);

            $this->simpanWali($siswa, $wali);

            if ($rombel !== null) {
                $this->tempatkanDiRombel($siswa, $rombel, $tanggalMasuk);
                DB::table('spmb_daftar_ulang')->where('pendaftar_id', $p->id)->update(['rombel_id' => $rombel->id, 'updated_at' => now()]);
            }

            $p->forceFill(['siswa_id' => $siswa->id, 'status' => 'menjadi_siswa'])->save();

            if ($p->kontak_id !== null) {
                SpmbKontak::query()->withoutGlobalScopes()->whereKey($p->kontak_id)->update(['status' => 'mendaftar']);
            }

            return $siswa->refresh();
        });
    }

    private function rombelTujuan(?int $rombelId, SpmbPendaftar $p, SpmbPeriode $periode): ?Rombel
    {
        if ($rombelId === null) {
            return null;
        }

        $rombel = Rombel::query()->withoutGlobalScopes()->find($rombelId);
        if ($rombel === null) {
            throw new KonversiTidakBisa("Rombel {$rombelId} tidak ditemukan.");
        }
        if ((int) $rombel->sekolah_id !== (int) $p->sekolah_id || (int) $rombel->tahun_ajaran_id !== (int) $periode->tahun_ajaran_id) {
            throw new KonversiTidakBisa("Rombel {$rombel->nama} bukan rombel sekolah dan tahun ajaran periode SPMB ini.");
        }

        return $rombel;
    }

    /** @return array{0: Siswa, 1: string} siswa dan jenis_masuk */
    private function siapkanSiswa(SpmbPendaftar $p, Carbon $tanggalMasuk): array
    {
        $orang = $p->nik !== null ? Orang::cariNik($p->nik)->first() : null;
        $siswa = $orang !== null ? Siswa::query()->where('orang_id', $orang->id)->first() : null;

        if ($p->nisn !== null) {
            $dipakaiLain = Siswa::query()->withTrashed()->where('nisn', $p->nisn)
                ->when($siswa !== null, fn ($q) => $q->where('id', '!=', $siswa->id))->exists();
            if ($dipakaiLain) {
                throw new KonversiTidakBisa("NISN {$p->nisn} sudah dipakai siswa lain. Periksa data pendaftar {$p->nomor_pendaftaran}.");
            }
        }

        if ($siswa !== null) {
            $masihAktif = SiswaSekolah::query()->withoutGlobalScopes()
                ->where('siswa_id', $siswa->id)->whereNull('tanggal_keluar')->exists();
            if ($masihAktif) {
                throw new KonversiTidakBisa(
                    "{$p->nama_lengkap} masih tercatat aktif di satuan pendidikan lain. Isi tanggal keluarnya lebih dulu, lalu ulangi konversi."
                );
            }

            $siswa->update(['status' => 'aktif']);

            return [$siswa, 'naik_jenjang'];
        }

        if ($orang === null) {
            $orang = Orang::create([
                'nama_lengkap' => $p->nama_lengkap,
                'jenis_kelamin' => $p->jenis_kelamin,
                'tempat_lahir' => $p->tempat_lahir,
                'tanggal_lahir' => $p->tanggal_lahir,
                'agama_ref' => $p->agama_ref,
                'nik' => $p->nik,
            ]);

            if (filled($p->alamat)) {
                DB::table('orang_alamat')->insert([
                    'orang_id' => $orang->id,
                    'jenis' => 'domisili',
                    'alamat' => $p->alamat,
                    'wilayah_id' => $p->wilayah_id,
                    'is_utama' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $siswa = Siswa::create([
            'orang_id' => $orang->id,
            'nis_yayasan' => $this->penomoran->berikutnya('nis_yayasan', null, $tanggalMasuk->year, '{TAHUN}{SEQ:4}'),
            'nisn' => $p->nisn,
            'tanggal_masuk_yayasan' => $tanggalMasuk,
            'status' => 'aktif',
        ]);

        return [$siswa, 'baru'];
    }

    /** @param \Illuminate\Support\Collection<int, SpmbPendaftarWali> $daftarWali */
    private function simpanWali(Siswa $siswa, $daftarWali): void
    {
        foreach ($daftarWali as $w) {
            $jenisKelamin = $w->jenis_kelamin ?? match ($w->hubungan) {
                'ayah' => 'L',
                'ibu' => 'P',
                default => null,
            };
            if ($jenisKelamin === null) {
                throw new KonversiTidakBisa("Jenis kelamin wali {$w->nama} belum diisi.");
            }

            $orang = $w->nik !== null ? Orang::cariNik($w->nik)->first() : null;

            if ($orang === null) {
                $orang = Orang::create([
                    'nama_lengkap' => $w->nama,
                    'jenis_kelamin' => $jenisKelamin,
                    'telepon' => Telepon::normal($w->telepon),
                    'nik' => $w->nik,
                ]);
            } elseif (blank($orang->telepon) && filled($w->telepon)) {
                $orang->update(['telepon' => Telepon::normal($w->telepon)]);
            }

            SiswaWali::query()->firstOrCreate(
                ['siswa_id' => $siswa->id, 'orang_id' => $orang->id],
                [
                    'hubungan' => $w->hubungan,
                    'pekerjaan_ref' => $w->pekerjaan_ref,
                    'penghasilan_ref' => $w->penghasilan_ref,
                    'is_kontak_utama' => $w->is_kontak_utama,
                    'is_penanggung_biaya' => false,
                    'is_serumah' => false,
                ]
            );
        }
    }

    private function tempatkanDiRombel(Siswa $siswa, Rombel $rombel, Carbon $tanggalMasuk): void
    {
        $sudah = RombelSiswa::query()->withoutGlobalScopes()
            ->where('tahun_ajaran_id', $rombel->tahun_ajaran_id)->where('siswa_id', $siswa->id)->exists();
        if ($sudah) {
            throw new KonversiTidakBisa("Siswa (ID {$siswa->id}) sudah berada di sebuah rombel pada tahun ajaran ini.");
        }

        RombelSiswa::create([
            'rombel_id' => $rombel->id,
            'siswa_id' => $siswa->id,
            'cabang_id' => $rombel->cabang_id,
            'tahun_ajaran_id' => $rombel->tahun_ajaran_id,
            'tanggal_masuk' => $tanggalMasuk,
            'status' => 'aktif',
        ]);
    }
}
