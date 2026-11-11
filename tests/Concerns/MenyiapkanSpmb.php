<?php

namespace Tests\Concerns;

use App\Models\SpmbPeriode;
use Database\Seeders\JenjangSeeder;
use Database\Seeders\ReferensiSeeder;
use Database\Seeders\TingkatSeeder;
use Illuminate\Support\Facades\DB;

trait MenyiapkanSpmb
{
    protected function seedDasar(): void
    {
        $this->seed(JenjangSeeder::class);
        $this->seed(TingkatSeeder::class);
        $this->seed(ReferensiSeeder::class);
    }

    /**
     * Satu cabang, satu SD, satu periode terbuka dengan satu jalur dan tiga persyaratan berkas
     * (dua wajib, satu opsional).
     *
     * @return array{cabang:int, sekolah:int, tahun:int, periode:SpmbPeriode, jalur:int, kk:int, akta:int, foto:int}
     */
    protected function periodeTerbuka(array $ubah = []): array
    {
        $waktu = ['created_at' => now(), 'updated_at' => now()];

        $cabang = DB::table('cabang')->insertGetId(['kode' => 'FTN-01', 'nama' => 'Fathona Palembang'] + $waktu);
        $sekolah = DB::table('sekolah')->insertGetId([
            'cabang_id' => $cabang,
            'jenjang_id' => DB::table('jenjang')->where('kode', 'SD')->value('id'),
            'nama' => 'SDIT Fathona Palembang',
        ] + $waktu);
        $tahun = DB::table('tahun_ajaran')->insertGetId([
            'kode' => '2026/2027', 'nama' => 'Tahun Ajaran 2026/2027',
            'tanggal_mulai' => '2026-07-13', 'tanggal_selesai' => '2027-06-26',
        ] + $waktu);

        $periode = SpmbPeriode::create($ubah + [
            'cabang_id' => $cabang, 'sekolah_id' => $sekolah, 'tahun_ajaran_id' => $tahun,
            'nama' => 'Gelombang 1', 'gelombang' => 1, 'status' => 'dibuka',
            'tanggal_buka' => today()->subDays(3)->toDateString(),
            'tanggal_tutup' => today()->addDays(30)->toDateString(),
            'kuota' => 30,
        ]);

        $jalur = DB::table('spmb_jalur')->insertGetId(['periode_id' => $periode->id, 'nama' => 'Reguler', 'is_aktif' => true] + $waktu);
        $syarat = fn (string $nama, bool $wajib, int $urutan) => DB::table('spmb_persyaratan_berkas')->insertGetId(
            ['periode_id' => $periode->id, 'nama' => $nama, 'is_wajib' => $wajib, 'urutan' => $urutan] + $waktu
        );

        return [
            'cabang' => $cabang, 'sekolah' => $sekolah, 'tahun' => $tahun, 'periode' => $periode, 'jalur' => $jalur,
            'kk' => $syarat('Kartu Keluarga', true, 1),
            'akta' => $syarat('Akta Kelahiran', true, 2),
            'foto' => $syarat('Pas Foto', false, 3),
        ];
    }

    /** Data pendaftaran (tanpa berkas) dalam bentuk yang diterima PendaftaranSpmb dan formulir. */
    protected function dataPendaftaran(array $s, array $ubah = []): array
    {
        return array_replace_recursive([
            'jalur_id' => $s['jalur'],
            'nama_lengkap' => 'Aisyah Putri',
            'jenis_kelamin' => 'P',
            'tempat_lahir' => 'Palembang',
            'tanggal_lahir' => '2020-03-15',
            'nik' => '1671012345678901',
            'asal_sekolah' => 'TK Melati',
            'alamat' => 'Jl. Contoh No. 1, Palembang',
            'kontak_utama' => 'ibu',
            'wali' => [
                'ayah' => ['nama' => 'Budi Santoso', 'nik' => '', 'telepon' => '0812-1111-2222', 'pekerjaan_ref' => 'karyawan_swasta', 'penghasilan_ref' => '2jt_5jt'],
                'ibu' => ['nama' => 'Siti Aminah', 'nik' => '', 'telepon' => '0813-3333-4444'],
                'wali' => ['nama' => ''],
            ],
        ], $ubah);
    }
}
