<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Isi awal tabel referensi. Daftar ini titik awal yang wajar; sesuaikan dengan kebutuhan
 * (misalnya mengikuti kode Dapodik bila nanti perlu sinkronisasi). Menjalankan ulang seeder
 * tidak menimpa kolom is_aktif yang sudah diubah pengguna.
 */
class ReferensiSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'agama' => [
                'islam' => 'Islam', 'kristen' => 'Kristen', 'katolik' => 'Katolik', 'hindu' => 'Hindu',
                'buddha' => 'Buddha', 'konghucu' => 'Konghucu', 'lainnya' => 'Lainnya',
            ],
            'status_nikah' => [
                'belum_menikah' => 'Belum menikah', 'menikah' => 'Menikah',
                'cerai_hidup' => 'Cerai hidup', 'cerai_mati' => 'Cerai mati',
            ],
            'pendidikan' => [
                'tidak_sekolah' => 'Tidak sekolah', 'sd' => 'SD/sederajat', 'smp' => 'SMP/sederajat',
                'sma' => 'SMA/sederajat', 'd1' => 'D1', 'd2' => 'D2', 'd3' => 'D3',
                's1' => 'S1/D4', 's2' => 'S2', 's3' => 'S3',
            ],
            'pekerjaan' => [
                'pns' => 'PNS', 'tni_polri' => 'TNI/Polri', 'karyawan_swasta' => 'Karyawan swasta',
                'wiraswasta' => 'Wiraswasta', 'pedagang' => 'Pedagang', 'petani_nelayan' => 'Petani/nelayan',
                'buruh' => 'Buruh', 'guru_dosen' => 'Guru/dosen', 'tenaga_kesehatan' => 'Tenaga kesehatan',
                'profesional' => 'Profesional lainnya', 'ibu_rumah_tangga' => 'Ibu rumah tangga',
                'tidak_bekerja' => 'Tidak bekerja', 'lainnya' => 'Lainnya',
            ],
            'penghasilan' => [
                'lt_500rb' => 'Kurang dari Rp500.000', '500rb_1jt' => 'Rp500.000 - Rp999.999',
                '1jt_2jt' => 'Rp1.000.000 - Rp1.999.999', '2jt_5jt' => 'Rp2.000.000 - Rp4.999.999',
                '5jt_20jt' => 'Rp5.000.000 - Rp20.000.000', 'gt_20jt' => 'Lebih dari Rp20.000.000',
            ],
            'status_kepegawaian' => [
                'tetap' => 'Pegawai tetap', 'kontrak' => 'Kontrak', 'honorer' => 'Honorer', 'magang' => 'Magang',
            ],
            'hubungan_keluarga' => [
                'ayah' => 'Ayah', 'ibu' => 'Ibu', 'suami' => 'Suami', 'istri' => 'Istri', 'anak' => 'Anak',
                'kakak' => 'Kakak', 'adik' => 'Adik', 'kakek_nenek' => 'Kakek/nenek', 'paman_bibi' => 'Paman/bibi',
                'wali' => 'Wali', 'lainnya' => 'Lainnya',
            ],
        ];

        $sekarang = now();
        $baris = [];

        foreach ($data as $kategori => $daftar) {
            $urutan = 0;
            foreach ($daftar as $kode => $label) {
                $baris[] = [
                    'kategori' => $kategori,
                    'kode' => $kode,
                    'label' => $label,
                    'urutan' => ++$urutan,
                    'is_aktif' => true,
                    'meta' => null,
                    'created_at' => $sekarang,
                    'updated_at' => $sekarang,
                ];
            }
        }

        DB::table('referensi')->upsert($baris, ['kategori', 'kode'], ['label', 'urutan', 'updated_at']);
    }
}
