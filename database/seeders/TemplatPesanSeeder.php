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
