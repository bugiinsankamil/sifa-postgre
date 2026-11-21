<?php

namespace App\Services\Keuangan;

use App\Exceptions\Keuangan\TagihanException;
use App\Models\Cabang;
use App\Models\DiskonBeasiswa;
use App\Models\EntitasAkuntansi;
use App\Models\JenisTagihan;
use App\Models\Sekolah;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Models\TarifTagihan;
use App\Models\TahunAjaran;
use App\Services\PenomoranDokumen;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class TagihanService
{
    /**
     * Hitung nominal, diskon, dan total dari tarif serta daftar diskon yang diterapkan.
     *
     * @return array{jenis_tagihan: JenisTagihan, tarif: TarifTagihan, nominal: string, diskon: string, total: string}
     */
    public function hitung(int $jenisTagihanId, int $tarifTagihanId, array $diskonIds = []): array
    {
        $jenis = JenisTagihan::findOrFail($jenisTagihanId);
        $tarif = TarifTagihan::findOrFail($tarifTagihanId);

        $nominal = (string) $tarif->nominal;
        $diskon = '0';
        foreach ($diskonIds as $diskonId) {
            $d = DiskonBeasiswa::findOrFail($diskonId);
            $diskon = bcadd($diskon, (string) $d->nilai, 2);
        }

        if (bccomp($diskon, $nominal, 2) > 0) {
            $diskon = $nominal;
        }

        return [
            'jenis_tagihan' => $jenis,
            'tarif' => $tarif,
            'nominal' => $nominal,
            'diskon' => $diskon,
            'total' => bcsub($nominal, $diskon, 2),
        ];
    }

    /**
     * Buat tagihan individual. Mencegah duplikat tagihan untuk siswa/pendaftar +
     * jenis tagihan + tahun ajaran + periode label yang belum batal.
     */
    public function buat(array $data): Tagihan
    {
        return DB::transaction(function () use ($data) {
            $duplikat = Tagihan::query()
                ->where('siswa_id', $data['siswa_id'] ?? null)
                ->where('pendaftar_id', $data['pendaftar_id'] ?? null)
                ->where('jenis_tagihan_id', $data['jenis_tagihan_id'])
                ->where('tahun_ajaran_id', $data['tahun_ajaran_id'])
                ->where('periode_label', $data['periode_label'] ?? null)
                ->where('status', '!=', 'batal')
                ->first();

            if ($duplikat) {
                $label = $duplikat->periode_label ?? $duplikat->jatuh_tempo;
                throw new TagihanException("Tagihan {$duplikat->nomor} untuk periode {$label} sudah ada.");
            }

            $hitung = $this->hitung($data['jenis_tagihan_id'], $data['tarif_tagihan_id'], $data['diskon_ids'] ?? []);

            $tagihan = Tagihan::create([
                'cabang_id' => $data['cabang_id'],
                'entitas_id' => $data['entitas_id'],
                'siswa_id' => $data['siswa_id'] ?? null,
                'pendaftar_id' => $data['pendaftar_id'] ?? null,
                'tahun_ajaran_id' => $data['tahun_ajaran_id'],
                'jenis_tagihan_id' => $data['jenis_tagihan_id'],
                'nomor' => app(PenomoranDokumen::class)->berikutnya(
                    'tagihan',
                    $data['cabang_id'],
                    Carbon::parse($data['tanggal_terbit'])->year,
                    'TAG/{CABANG}/{TAHUN}/{SEQ:5}'
                ),
                'periode_label' => $data['periode_label'] ?? null,
                'tanggal_terbit' => $data['tanggal_terbit'],
                'jatuh_tempo' => $data['jatuh_tempo'],
                'nominal' => $hitung['nominal'],
                'diskon' => $hitung['diskon'],
                'total' => $hitung['total'],
                'terbayar' => 0,
                'status' => 'belum',
            ]);

            return $tagihan->fresh();
        });
    }

    /**
     * Buat tagihan massal untuk banyak siswa pada satu jenis/tarif/tahun ajaran.
     * Setiap siswa dicek duplikatnya secara individual.
     *
     * @return array<int, Tagihan>
     */
    public function buatMassal(array $data): array
    {
        $hasil = [];
        foreach ($data['siswa_ids'] as $siswaId) {
            Siswa::findOrFail($siswaId);

            $hasil[] = $this->buat(array_merge($data, [
                'siswa_id' => $siswaId,
                'pendaftar_id' => null,
                'cabang_id' => $data['cabang_id'],
                'entitas_id' => $data['entitas_id'],
            ]));
        }

        return $hasil;
    }

    public function ubahStatus(Tagihan $tagihan, string $status): Tagihan
    {
        if (!in_array($status, ['belum', 'sebagian', 'lunas', 'batal'], true)) {
            throw new TagihanException("Status tagihan tidak valid.");
        }

        $tagihan->update(['status' => $status]);

        return $tagihan->fresh();
    }

    /**
     * Buat tagihan daftar ulang untuk siswa baru hasil konversi SPMB.
     * Idemen: tidak membuat duplikat bila tagihan daftar ulang untuk siswa +
     * tahun ajaran ini sudah ada. Mengembalikan null bila jenis/tarif belum
     * dikonfigurasi (tagihan dapat dibuat nanti lewat panel).
     */
    public function buatDaftarUlang(
        Siswa $siswa,
        EntitasAkuntansi $entitas,
        Cabang $cabang,
        Sekolah $sekolah,
        TahunAjaran $tahunAjaran
    ): ?Tagihan {
        $jenis = JenisTagihan::where('kode', 'daftar_ulang')->where('is_aktif', true)->first();
        if (!$jenis) {
            return null;
        }

        $sudah = Tagihan::query()
            ->where('siswa_id', $siswa->id)
            ->where('jenis_tagihan_id', $jenis->id)
            ->where('tahun_ajaran_id', $tahunAjaran->id)
            ->where('status', '!=', 'batal')
            ->first();
        if ($sudah) {
            return $sudah;
        }

        $tarif = TarifTagihan::where('jenis_tagihan_id', $jenis->id)
            ->where('sekolah_id', $sekolah->id)
            ->where('tahun_ajaran_id', $tahunAjaran->id)
            ->whereNull('tingkat_id')
            ->first();
        if (!$tarif) {
            return null;
        }

        return $this->buat([
            'cabang_id' => $cabang->id,
            'entitas_id' => $entitas->id,
            'siswa_id' => $siswa->id,
            'pendaftar_id' => null,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'jenis_tagihan_id' => $jenis->id,
            'tarif_tagihan_id' => $tarif->id,
            'tanggal_terbit' => Carbon::today()->toDateString(),
            'jatuh_tempo' => Carbon::today()->addDays(7)->toDateString(),
        ]);
    }
}
