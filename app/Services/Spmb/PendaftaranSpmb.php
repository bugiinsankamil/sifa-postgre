<?php

namespace App\Services\Spmb;

use App\Events\Spmb\PendaftarTerdaftar;
use App\Exceptions\Spmb\PendaftaranDitutup;
use App\Exceptions\Spmb\PendaftarGanda;
use App\Models\SpmbBerkas;
use App\Models\SpmbKontak;
use App\Models\SpmbPendaftar;
use App\Models\SpmbPendaftarWali;
use App\Models\SpmbPeriode;
use App\Support\Telepon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan satu pendaftaran SPMB dalam satu transaksi: pendaftar, wali, berkas, dan tautan ke kontak (CRM).
 *
 * $data memakai bentuk yang sama dengan formulir:
 *   jalur_id, nama_lengkap, jenis_kelamin, tempat_lahir, tanggal_lahir, nik?, nisn?, agama_ref?, alamat?,
 *   asal_sekolah?, kontak_utama (ayah|ibu|wali),
 *   wali => [ayah|ibu|wali => [nama, jenis_kelamin?, nik?, telepon?, pekerjaan_ref?, penghasilan_ref?]]
 * $berkas: [persyaratan_id => UploadedFile]
 */
class PendaftaranSpmb
{
    private const HUBUNGAN = ['ayah', 'ibu', 'wali'];

    private const JENIS_KELAMIN_WALI = ['ayah' => 'L', 'ibu' => 'P'];

    public function terbuka(SpmbPeriode $periode): bool
    {
        return $periode->status === 'dibuka'
            && today()->between($periode->tanggal_buka, $periode->tanggal_tutup);
    }

    /**
     * @param  array<int|string, UploadedFile>  $berkas
     *
     * @throws PendaftaranDitutup
     * @throws PendaftarGanda
     */
    public function daftar(SpmbPeriode $periode, array $data, array $berkas = []): SpmbPendaftar
    {
        if (! $this->terbuka($periode)) {
            throw new PendaftaranDitutup('Pendaftaran pada periode ini sedang tidak dibuka.');
        }

        $baru = DB::transaction(function () use ($periode, $data, $berkas) {
            $this->tolakGanda($periode, $data);

            $kontak = $this->hubungkanKontak($periode, $data);

            $pendaftar = SpmbPendaftar::create([
                'periode_id' => $periode->id,
                'jalur_id' => $data['jalur_id'],
                'cabang_id' => $periode->cabang_id,
                'sekolah_id' => $periode->sekolah_id,
                'kontak_id' => $kontak->id,
                'tanggal_daftar' => today(),
                'nama_lengkap' => trim($data['nama_lengkap']),
                'jenis_kelamin' => $data['jenis_kelamin'],
                'tempat_lahir' => trim($data['tempat_lahir']),
                'tanggal_lahir' => $data['tanggal_lahir'],
                'nik' => $data['nik'] ?? null,
                'nisn' => $data['nisn'] ?? null,
                'agama_ref' => $data['agama_ref'] ?? null,
                'alamat' => $data['alamat'] ?? null,
                'asal_sekolah' => $data['asal_sekolah'] ?? null,
                'status' => 'terdaftar',
            ]);

            $this->simpanWali($pendaftar, $data);
            $this->simpanBerkas($periode, $pendaftar, $berkas);

            return $pendaftar->refresh();
        });

        // Setelah transaksi selesai: pesan konfirmasi ke orang tua dibuat dari peristiwa ini
        PendaftarTerdaftar::dispatch($baru);

        return $baru;
    }

    private function tolakGanda(SpmbPeriode $periode, array $data): void
    {
        $nik = preg_replace('/\D+/', '', (string) ($data['nik'] ?? ''));

        // Tanpa lingkup cabang: pemeriksaan harus mencakup semua data, siapa pun yang sedang masuk
        $dasar = SpmbPendaftar::query()->withoutGlobalScopes()->where('periode_id', $periode->id);

        if ($nik !== '' && (clone $dasar)->where('nik_hash', SpmbPendaftar::hashNik($nik))->exists()) {
            throw new PendaftarGanda('NIK ini sudah terdaftar pada periode ini.');
        }

        $sama = (clone $dasar)
            ->whereRaw('LOWER(nama_lengkap) = ?', [mb_strtolower(trim($data['nama_lengkap']))])
            ->whereDate('tanggal_lahir', $data['tanggal_lahir'])
            ->exists();

        if ($sama) {
            throw new PendaftarGanda('Calon siswa dengan nama dan tanggal lahir yang sama sudah terdaftar pada periode ini.');
        }
    }

    /**
     * Setiap pendaftar punya kontak di CRM, sehingga corong SPMB mencakup semua pendaftar.
     * Memakai kontak yang sudah ada (nomor sama dan nama anak sama) bila ada; bila tidak, membuat yang baru.
     */
    private function hubungkanKontak(SpmbPeriode $periode, array $data): SpmbKontak
    {
        $utama = $data['wali'][$data['kontak_utama']];
        $telepon = Telepon::normal($utama['telepon']);
        $namaAnak = trim($data['nama_lengkap']);

        $kandidat = SpmbKontak::query()->withoutGlobalScopes()
            ->where('cabang_id', $periode->cabang_id)
            ->where('tahun_ajaran_id', $periode->tahun_ajaran_id)
            ->where('telepon', $telepon)
            ->get();

        $kontak = $kandidat->first(
            fn (SpmbKontak $k) => filled($k->nama_calon_siswa) && mb_strtolower($k->nama_calon_siswa) === mb_strtolower($namaAnak)
        );

        if ($kontak === null) {
            $tanpaNama = $kandidat->filter(fn (SpmbKontak $k) => blank($k->nama_calon_siswa));
            $kontak = $tanpaNama->count() === 1 ? $tanpaNama->first() : null;
        }

        if ($kontak !== null) {
            $kontak->update([
                'status' => 'mendaftar',
                'nama_calon_siswa' => $kontak->nama_calon_siswa ?: $namaAnak,
                'sekolah_id' => $kontak->sekolah_id ?: $periode->sekolah_id,
            ]);

            return $kontak;
        }

        return SpmbKontak::create([
            'cabang_id' => $periode->cabang_id,
            'sekolah_id' => $periode->sekolah_id,
            'tahun_ajaran_id' => $periode->tahun_ajaran_id,
            'nama_kontak' => trim($utama['nama']),
            'telepon' => $telepon,
            'nama_calon_siswa' => $namaAnak,
            'sumber_ref' => config('spmb.sumber_bawaan', 'website'),
            'status' => 'mendaftar',
        ]);
    }

    private function simpanWali(SpmbPendaftar $pendaftar, array $data): void
    {
        foreach (self::HUBUNGAN as $hubungan) {
            $w = $data['wali'][$hubungan] ?? [];
            if (blank($w['nama'] ?? null)) {
                continue;
            }

            $nik = preg_replace('/\D+/', '', (string) ($w['nik'] ?? ''));

            SpmbPendaftarWali::create([
                'pendaftar_id' => $pendaftar->id,
                'hubungan' => $hubungan,
                'nama' => trim($w['nama']),
                'jenis_kelamin' => self::JENIS_KELAMIN_WALI[$hubungan] ?? ($w['jenis_kelamin'] ?? null),
                'nik' => $nik !== '' ? $nik : null,
                'telepon' => Telepon::normal($w['telepon'] ?? null),
                'pekerjaan_ref' => $w['pekerjaan_ref'] ?? null,
                'penghasilan_ref' => $w['penghasilan_ref'] ?? null,
                'is_kontak_utama' => $data['kontak_utama'] === $hubungan,
            ]);
        }
    }

    /** @param array<int|string, mixed> $berkas */
    private function simpanBerkas(SpmbPeriode $periode, SpmbPendaftar $pendaftar, array $berkas): void
    {
        foreach ($berkas as $persyaratanId => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $sahPeriodeIni = DB::table('spmb_persyaratan_berkas')
                ->where('id', (int) $persyaratanId)->where('periode_id', $periode->id)->exists();
            if (! $sahPeriodeIni) {
                continue;
            }

            $nama = $file->getClientOriginalName();
            $mime = $file->getMimeType() ?: $file->getClientMimeType();
            $ukuran = $file->getSize();
            $checksum = hash_file('sha256', $file->getRealPath());
            $path = $file->store("spmb/{$periode->id}/{$pendaftar->id}", 'local');

            $entri = SpmbBerkas::create([
                'pendaftar_id' => $pendaftar->id,
                'persyaratan_id' => (int) $persyaratanId,
                'periode_id' => $periode->id,
                'status_verifikasi' => 'menunggu',
            ]);

            $entri->lampiran()->create([
                'jenis' => 'berkas_spmb',
                'disk' => 'local',
                'path' => $path,
                'nama_asli' => $nama,
                'mime' => $mime,
                'ukuran' => $ukuran,
                'checksum' => $checksum,
            ]);
        }
    }
}
