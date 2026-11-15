<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Templat awal pesan SPMB. Aman dijalankan berulang: templat yang sudah ada (dan mungkin sudah Anda ubah)
 * tidak ditimpa. Ubah isinya lewat panel admin: Pesan > Templat pesan.
 */
class TemplatPesanSeeder extends Seeder
{
    public function run(): void
    {
        $umum = ['nama_wali', 'nama_anak', 'sekolah', 'nomor', 'url_status'];
        $waktu = ['created_at' => now(), 'updated_at' => now()];

        $baris = [
            [
                'kode' => 'spmb_terdaftar',
                'isi' => "Assalamu'alaikum {{nama_wali}}.\nPendaftaran {{nama_anak}} di {{sekolah}} sudah kami terima.\nNomor pendaftaran: {{nomor}}\nPantau status dan kelengkapan berkas di: {{url_status}}\nJazakumullahu khairan.",
                'variabel' => $umum,
            ],
            [
                'kode' => 'spmb_berkas_ditolak',
                'isi' => "Assalamu'alaikum {{nama_wali}}.\nBerkas \"{{berkas}}\" pendaftaran {{nama_anak}} ({{nomor}}) perlu diperbaiki.\nAlasan: {{alasan}}\nSilakan unggah berkas perbaikan di: {{url_status}}\nJazakumullahu khairan.",
                'variabel' => [...$umum, 'berkas', 'alasan'],
            ],
            [
                'kode' => 'spmb_terverifikasi',
                'isi' => "Assalamu'alaikum {{nama_wali}}.\nBerkas pendaftaran {{nama_anak}} ({{nomor}}) di {{sekolah}} sudah kami verifikasi. Informasi jadwal seleksi akan kami sampaikan.\nStatus terbaru: {{url_status}}",
                'variabel' => $umum,
            ],
            [
                'kode' => 'spmb_hasil_lulus',
                'isi' => "Assalamu'alaikum {{nama_wali}}.\nAlhamdulillah, {{nama_anak}} ({{nomor}}) dinyatakan LULUS seleksi penerimaan murid baru di {{sekolah}}.\nSilakan melakukan daftar ulang sesuai jadwal dan ketentuan yang disampaikan sekolah. Informasi daftar ulang dan status pendaftaran dapat dilihat di: {{url_status}}\nJazakumullahu khairan.",
                'variabel' => $umum,
            ],
            [
                'kode' => 'spmb_hasil_cadangan',
                'isi' => "Assalamu'alaikum {{nama_wali}}.\nBerdasarkan hasil seleksi, {{nama_anak}} ({{nomor}}) berstatus CADANGAN di {{sekolah}}.\nKami akan menghubungi Anda apabila tersedia kesempatan daftar ulang. Pantau informasi terbaru di: {{url_status}}\nJazakumullahu khairan.",
                'variabel' => $umum,
            ],
            [
                'kode' => 'spmb_hasil_tidak_lulus',
                'isi' => "Assalamu'alaikum {{nama_wali}}.\nTerima kasih atas partisipasi {{nama_anak}} ({{nomor}}) dalam seleksi penerimaan murid baru di {{sekolah}}. Dengan berat hati kami sampaikan bahwa pada periode ini ananda belum dinyatakan lulus.\nSemoga ananda senantiasa diberi kemudahan dan keberhasilan dalam melanjutkan pendidikan. Informasi status dapat dilihat di: {{url_status}}\nJazakumullahu khairan.",
                'variabel' => $umum,
            ],
        ];

        foreach ($baris as $b) {
            DB::table('templat_pesan')->insertOrIgnore([
                'kode' => $b['kode'],
                'kanal' => 'whatsapp',
                'judul' => null,
                'isi' => $b['isi'],
                'variabel' => json_encode($b['variabel']),
                'is_aktif' => true,
            ] + $waktu);
        }
    }
}
