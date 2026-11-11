<?php

namespace App\Console\Commands;

use App\Exceptions\Spmb\KonversiTidakBisa;
use App\Models\SpmbPendaftar;
use App\Services\Spmb\KonversiPendaftar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class KonversiSpmb extends Command
{
    protected $signature = 'spmb:konversi
        {periode : ID periode SPMB}
        {--nomor= : Hanya satu nomor pendaftaran}
        {--rombel= : ID rombel tujuan (bila kosong dipakai rombel pada daftar ulang)}
        {--dry-run : Tampilkan yang akan dikonversi tanpa mengubah data}';

    protected $description = 'Mengonversi pendaftar SPMB yang lulus dan sudah daftar ulang menjadi siswa';

    public function handle(KonversiPendaftar $konversi): int
    {
        $query = SpmbPendaftar::query()->withoutGlobalScopes()->where('periode_id', $this->argument('periode'));

        if ($nomor = $this->option('nomor')) {
            // Dengan --nomor, syarat diperiksa oleh konversi sendiri agar alasan penolakannya jelas
            $query->where('nomor_pendaftaran', $nomor);
        } else {
            $query->whereNull('siswa_id')
                ->whereExists(fn ($s) => $s->select(DB::raw(1))->from('spmb_hasil')
                    ->whereColumn('spmb_hasil.pendaftar_id', 'spmb_pendaftar.id')->where('spmb_hasil.keputusan', 'lulus'))
                ->whereExists(fn ($s) => $s->select(DB::raw(1))->from('spmb_daftar_ulang')
                    ->whereColumn('spmb_daftar_ulang.pendaftar_id', 'spmb_pendaftar.id')->where('spmb_daftar_ulang.status', 'selesai'));
        }

        $daftar = $query->orderBy('id')->get();

        if ($daftar->isEmpty()) {
            $this->warn('Tidak ada pendaftar yang memenuhi syarat konversi.');

            return self::SUCCESS;
        }

        $rombel = $this->option('rombel') !== null ? (int) $this->option('rombel') : null;
        $berhasil = 0;
        $gagal = 0;

        foreach ($daftar as $pendaftar) {
            if ($this->option('dry-run')) {
                $this->line("[uji coba] {$pendaftar->nomor_pendaftaran}  {$pendaftar->nama_lengkap}");

                continue;
            }

            try {
                $siswa = $konversi->konversi($pendaftar, $rombel);
                $this->info("OK    {$pendaftar->nomor_pendaftaran}  {$pendaftar->nama_lengkap}  =>  NIS {$siswa->nis_yayasan}");
                $berhasil++;
            } catch (KonversiTidakBisa $e) {
                $this->error("GAGAL {$pendaftar->nomor_pendaftaran}  {$e->getMessage()}");
                $gagal++;
            }
        }

        if (! $this->option('dry-run')) {
            $this->line("Selesai: {$berhasil} berhasil, {$gagal} gagal.");
        }

        return $gagal > 0 ? self::FAILURE : self::SUCCESS;
    }
}
