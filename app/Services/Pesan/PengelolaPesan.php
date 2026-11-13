<?php

namespace App\Services\Pesan;

use App\Models\AntreanPesan;
use App\Models\TemplatPesan;
use App\Pesan\JamTenang;
use App\Pesan\Pengirim;
use App\Support\Telepon;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Pintu masuk tunggal untuk pesan keluar: menyusun isi dari templat, menaruhnya di antrean_pesan, dan
 * menyediakan pengirim sesuai kanal. Pengiriman sebenarnya dilakukan perintah `pesan:kirim`.
 */
class PengelolaPesan
{
    /**
     * Mengantrekan pesan dari templat. Mengembalikan null bila pesan dinonaktifkan, templat tidak ada/nonaktif,
     * atau tujuan kosong; ini sengaja tidak melempar galat agar proses bisnis (pendaftaran, verifikasi, dst.)
     * tidak gagal hanya karena pesannya tidak bisa dibuat.
     *
     * @param  array<string, scalar|null>  $data  variabel templat
     * @param  array{kanal?: string, cabang_id?: int, kunci_unik?: string, konteks_type?: string, konteks_id?: int, dijadwalkan_pada?: CarbonInterface}  $opsi
     *
     * @throws InvalidArgumentException bila templat memakai variabel yang tidak diberikan
     */
    public function antri(string $kode, string $tujuan, array $data, array $opsi = []): ?AntreanPesan
    {
        if (! config('pesan.aktif', true)) {
            return null;
        }

        $kanal = $opsi['kanal'] ?? 'whatsapp';
        $templat = TemplatPesan::query()->where('kode', $kode)->where('kanal', $kanal)->where('is_aktif', true)->first();

        if ($templat === null) {
            Log::warning("Templat pesan {$kode} ({$kanal}) tidak ditemukan atau nonaktif; pesan dilewati.");

            return null;
        }

        return $this->simpan($kanal, $tujuan, $this->render($templat->isi, $data), $opsi + [], $templat);
    }

    /** Mengantrekan pesan tanpa templat (mis. untuk uji coba). */
    public function antriLangsung(string $kanal, string $tujuan, string $isi, array $opsi = []): ?AntreanPesan
    {
        if (! config('pesan.aktif', true)) {
            return null;
        }

        return $this->simpan($kanal, $tujuan, $isi, $opsi, null);
    }

    /** @throws InvalidArgumentException */
    public function render(string $isi, array $data): string
    {
        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', function (array $m) use ($data) {
            if (! array_key_exists($m[1], $data)) {
                throw new InvalidArgumentException("Variabel pesan tidak diberikan: {$m[1]}");
            }

            return (string) $data[$m[1]];
        }, $isi);
    }

    /** @throws RuntimeException bila kanal belum punya pengirim yang terdaftar */
    public function pengirim(string $kanal): Pengirim
    {
        $nama = config("pesan.pengirim.{$kanal}");
        $kelas = $nama !== null ? config("pesan.driver.{$nama}") : null;

        if ($kelas === null) {
            throw new RuntimeException("Belum ada pengirim untuk kanal {$kanal}. Periksa config/pesan.php.");
        }

        return app()->make($kelas);
    }

    private function simpan(string $kanal, string $tujuan, string $isi, array $opsi, ?TemplatPesan $templat): ?AntreanPesan
    {
        $tujuanBersih = $kanal === 'whatsapp' ? Telepon::normal($tujuan) : trim($tujuan);
        if (blank($tujuanBersih)) {
            return null;
        }

        $atribut = [
            'cabang_id' => $opsi['cabang_id'] ?? null,
            'kanal' => $kanal,
            'tujuan' => $tujuanBersih,
            'templat_id' => $templat?->id,
            'judul' => $templat?->judul,
            'isi' => $isi,
            'status' => 'antri',
            'dijadwalkan_pada' => JamTenang::sesuaikan($opsi['dijadwalkan_pada'] ?? now()),
            'konteks_type' => $opsi['konteks_type'] ?? null,
            'konteks_id' => $opsi['konteks_id'] ?? null,
        ];

        // Kunci unik mencegah pesan yang sama diantrekan dua kali (mis. proses diulang)
        if (filled($opsi['kunci_unik'] ?? null)) {
            return AntreanPesan::query()->withoutGlobalScopes()
                ->firstOrCreate(['kunci_unik' => $opsi['kunci_unik']], $atribut);
        }

        return AntreanPesan::query()->create($atribut);
    }
}
