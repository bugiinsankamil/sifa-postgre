<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EksporPendaftarSpmb extends Command
{
    protected $signature = 'spmb:ekspor {periode : ID periode SPMB} {--path= : Lokasi berkas CSV}';

    protected $description = 'Mengekspor daftar pendaftar SPMB ke CSV (tanpa NIK) untuk dibuka di Excel';

    public function handle(): int
    {
        $baris = DB::table('spmb_pendaftar as p')
            ->join('spmb_jalur as j', 'j.id', '=', 'p.jalur_id')
            ->leftJoin('spmb_pendaftar_wali as w', fn ($join) => $join->on('w.pendaftar_id', '=', 'p.id')->where('w.is_kontak_utama', true))
            ->leftJoin('spmb_hasil as h', 'h.pendaftar_id', '=', 'p.id')
            ->where('p.periode_id', $this->argument('periode'))
            ->orderBy('p.nomor_pendaftaran')
            ->get([
                'p.nomor_pendaftaran', 'p.tanggal_daftar', 'p.nama_lengkap', 'p.jenis_kelamin', 'p.tanggal_lahir',
                'p.asal_sekolah', 'j.nama as jalur', 'p.status', 'w.nama as kontak_utama', 'w.telepon', 'h.keputusan as hasil',
            ]);

        $path = $this->option('path') ?: storage_path('app/spmb-periode-'.$this->argument('periode').'-'.date('Ymd-His').'.csv');
        $f = fopen($path, 'w');
        fwrite($f, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
        fputcsv($f, ['Nomor', 'Tanggal daftar', 'Nama', 'JK', 'Tanggal lahir', 'Asal sekolah', 'Jalur', 'Status', 'Kontak utama', 'Telepon', 'Hasil']);

        foreach ($baris as $b) {
            fputcsv($f, [$b->nomor_pendaftaran, $b->tanggal_daftar, $b->nama_lengkap, $b->jenis_kelamin, $b->tanggal_lahir,
                $b->asal_sekolah, $b->jalur, $b->status, $b->kontak_utama, $b->telepon, $b->hasil]);
        }
        fclose($f);

        $this->info(count($baris)." pendaftar diekspor ke {$path}");
        $this->warn('Berkas ini berisi data pribadi. Simpan dengan aman dan hapus setelah dipakai.');

        return self::SUCCESS;
    }
}
