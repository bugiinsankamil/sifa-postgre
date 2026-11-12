<?php

namespace App\Services\Spmb;

use App\Exceptions\Spmb\TransisiTidakValid;
use App\Models\Rombel;
use App\Models\SpmbDaftarUlang;
use App\Models\SpmbHasil;
use App\Models\SpmbKontak;
use App\Models\SpmbNilaiSeleksi;
use App\Models\SpmbPendaftar;
use App\Models\SpmbTahapSeleksi;
use Illuminate\Support\Facades\DB;

/**
 * Alur setelah pendaftaran: verifikasi berkas, nilai seleksi, hasil, daftar ulang, mundur.
 * (Konversi menjadi siswa ada di KonversiPendaftar.)
 *
 * Status: terdaftar -> diverifikasi -> seleksi -> lulus | cadangan | tidak_lulus
 *         lulus -> daftar_ulang -> menjadi_siswa (lewat konversi);  mundur dari status mana pun sebelum menjadi_siswa.
 */
class AlurSeleksiSpmb
{
    private const STATUS_HASIL = ['lulus', 'cadangan', 'tidak_lulus'];

    /** @throws TransisiTidakValid */
    public function verifikasi(SpmbPendaftar $p): void
    {
        $this->pastikanStatus($p, ['terdaftar']);

        $belum = DB::table('spmb_persyaratan_berkas as r')
            ->where('r.periode_id', $p->periode_id)
            ->where('r.is_wajib', true)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('spmb_berkas as b')
                ->whereColumn('b.persyaratan_id', 'r.id')
                ->where('b.pendaftar_id', $p->id)
                ->where('b.status_verifikasi', 'valid'))
            ->orderBy('r.urutan')
            ->pluck('r.nama');

        if ($belum->isNotEmpty()) {
            throw new TransisiTidakValid('Berkas wajib belum valid: '.$belum->implode(', ').'.');
        }

        $p->update(['status' => 'diverifikasi']);
    }

    /** @throws TransisiTidakValid */
    public function catatNilai(
        SpmbPendaftar $p,
        int $tahapId,
        ?float $nilai,
        ?string $tanggal = null,
        ?string $catatan = null,
        ?int $pengujiId = null,
    ): SpmbNilaiSeleksi {
        $this->pastikanStatus($p, ['diverifikasi', 'seleksi', ...self::STATUS_HASIL]);

        $tahap = SpmbTahapSeleksi::query()->where('periode_id', $p->periode_id)->find($tahapId);
        if ($tahap === null) {
            throw new TransisiTidakValid('Tahap seleksi tidak termasuk dalam periode pendaftar ini.');
        }

        $baris = SpmbNilaiSeleksi::query()->updateOrCreate(
            ['pendaftar_id' => $p->id, 'tahap_id' => $tahap->id],
            [
                'periode_id' => $p->periode_id,
                'tanggal' => $tanggal ?? today()->toDateString(),
                'nilai' => $nilai,
                'catatan' => $catatan,
                'penguji_id' => $pengujiId,
            ]
        );

        if ($p->status === 'diverifikasi') {
            $p->update(['status' => 'seleksi']);
        }

        return $baris;
    }

    /** Rata-rata nilai tahap berbobot; tanpa bobot dipakai rata-rata biasa. Null bila belum ada nilai. */
    public function hitungSkor(SpmbPendaftar $p): ?float
    {
        $baris = DB::table('spmb_nilai_seleksi as n')
            ->join('spmb_tahap_seleksi as t', 't.id', '=', 'n.tahap_id')
            ->where('n.pendaftar_id', $p->id)
            ->whereNotNull('n.nilai')
            ->get(['n.nilai', 't.bobot']);

        if ($baris->isEmpty()) {
            return null;
        }

        $totalBobot = $baris->sum(fn ($r) => (float) $r->bobot);
        if ($totalBobot <= 0) {
            return round($baris->avg(fn ($r) => (float) $r->nilai), 2);
        }

        return round($baris->sum(fn ($r) => (float) $r->nilai * (float) $r->bobot) / $totalBobot, 2);
    }

    /** @throws TransisiTidakValid */
    public function tetapkanHasil(SpmbPendaftar $p, string $keputusan, int $penetapId, ?string $catatan = null): SpmbHasil
    {
        if (! in_array($keputusan, self::STATUS_HASIL, true)) {
            throw new TransisiTidakValid('Keputusan harus lulus, cadangan, atau tidak lulus.');
        }
        $this->pastikanStatus($p, ['diverifikasi', 'seleksi', ...self::STATUS_HASIL]);

        $hasil = SpmbHasil::query()->updateOrCreate(
            ['pendaftar_id' => $p->id],
            [
                'skor_total' => $this->hitungSkor($p),
                'keputusan' => $keputusan,
                'tanggal_keputusan' => today()->toDateString(),
                'ditetapkan_oleh' => $penetapId,
                'catatan' => $catatan,
            ]
        );

        $p->update(['status' => $keputusan]);

        return $hasil;
    }

    /** Memberi peringkat pada semua hasil di satu periode menurut skor (sama skor = sama peringkat). */
    public function hitungPeringkat(int $periodeId): int
    {
        return DB::update(<<<'SQL'
UPDATE spmb_hasil h
SET peringkat = r.posisi, updated_at = now()
FROM (
    SELECT h2.id, RANK() OVER (ORDER BY h2.skor_total DESC NULLS LAST) AS posisi
    FROM spmb_hasil h2
    JOIN spmb_pendaftar p ON p.id = h2.pendaftar_id
    WHERE p.periode_id = ?
) r
WHERE h.id = r.id
SQL, [$periodeId]);
    }

    /** @throws TransisiTidakValid */
    public function mulaiDaftarUlang(SpmbPendaftar $p): SpmbDaftarUlang
    {
        $this->pastikanStatus($p, ['lulus']);

        $daftarUlang = SpmbDaftarUlang::query()->firstOrCreate(['pendaftar_id' => $p->id], ['status' => 'menunggu']);
        $p->update(['status' => 'daftar_ulang']);

        return $daftarUlang;
    }

    /** @throws TransisiTidakValid */
    public function selesaikanDaftarUlang(SpmbPendaftar $p, ?int $rombelId = null, ?string $tanggal = null): SpmbDaftarUlang
    {
        $this->pastikanStatus($p, ['daftar_ulang']);

        if ($rombelId !== null) {
            $periode = $p->periode;
            $rombel = Rombel::query()->find($rombelId);
            if ($rombel === null
                || (int) $rombel->sekolah_id !== (int) $p->sekolah_id
                || (int) $rombel->tahun_ajaran_id !== (int) $periode->tahun_ajaran_id) {
                throw new TransisiTidakValid('Rombel harus milik sekolah dan tahun ajaran periode pendaftaran ini.');
            }
        }

        $daftarUlang = SpmbDaftarUlang::query()->firstOrCreate(['pendaftar_id' => $p->id], ['status' => 'menunggu']);
        $daftarUlang->update([
            'status' => 'selesai',
            'tanggal' => $tanggal ?? today()->toDateString(),
            'rombel_id' => $rombelId,
        ]);

        return $daftarUlang;
    }

    /** @throws TransisiTidakValid */
    public function mundur(SpmbPendaftar $p, ?string $alasan = null): void
    {
        if (in_array($p->status, ['menjadi_siswa', 'mundur'], true)) {
            throw new TransisiTidakValid('Pendaftar yang sudah menjadi siswa atau sudah mundur tidak dapat diubah.');
        }

        DB::transaction(function () use ($p, $alasan) {
            $p->update(['status' => 'mundur']);

            if ($p->kontak_id !== null) {
                SpmbKontak::query()->withoutGlobalScopes()->whereKey($p->kontak_id)->update([
                    'status' => 'tidak_lanjut',
                    'alasan_tidak_lanjut' => $alasan ?: 'Mengundurkan diri',
                ]);
            }
        });
    }

    /** @param string[] $boleh */
    private function pastikanStatus(SpmbPendaftar $p, array $boleh): void
    {
        if (! in_array($p->status, $boleh, true)) {
            throw new TransisiTidakValid("Status pendaftar saat ini ({$p->status}) tidak memungkinkan tindakan ini.");
        }
    }
}
