<?php

namespace App\Services\Spmb;

use App\Exceptions\Spmb\UnggahUlangDitolak;
use App\Models\SpmbBerkas;
use App\Models\SpmbPendaftar;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Pendaftar mengganti berkas yang ditolak panitia. Hanya berkas berstatus tidak_valid yang bisa diganti, dan
 * hanya selama verifikasi pendaftar belum selesai (status draft atau terdaftar).
 *
 * Berkas lama tidak dihapus: catatan lampirannya di-soft-delete dan filenya tetap ada sebagai arsip.
 */
class PerbaikanBerkasSpmb
{
    /** @throws UnggahUlangDitolak */
    public function ganti(SpmbBerkas $berkas, UploadedFile $file): void
    {
        DB::transaction(function () use ($berkas, $file) {
            $berkas = SpmbBerkas::query()->lockForUpdate()->findOrFail($berkas->id);
            $pendaftar = SpmbPendaftar::query()->withoutGlobalScopes()->findOrFail($berkas->pendaftar_id);

            if ($berkas->status_verifikasi !== 'tidak_valid') {
                throw new UnggahUlangDitolak('Hanya berkas yang ditolak yang dapat diunggah ulang.');
            }
            if (! in_array($pendaftar->status, ['draft', 'terdaftar'], true)) {
                throw new UnggahUlangDitolak('Verifikasi berkas sudah selesai, unggah ulang tidak lagi dibuka.');
            }

            $nama = $file->getClientOriginalName();
            $mime = $file->getMimeType() ?: $file->getClientMimeType();
            $ukuran = $file->getSize();
            $checksum = hash_file('sha256', $file->getRealPath());
            $path = $file->store("spmb/{$pendaftar->periode_id}/{$pendaftar->id}", 'local');

            $berkas->lampiran()->get()->each->delete();

            $berkas->lampiran()->create([
                'jenis' => 'berkas_spmb',
                'disk' => 'local',
                'path' => $path,
                'nama_asli' => $nama,
                'mime' => $mime,
                'ukuran' => $ukuran,
                'checksum' => $checksum,
            ]);

            $berkas->update([
                'status_verifikasi' => 'menunggu',
                'catatan' => null,
                'diverifikasi_oleh' => null,
            ]);
        });
    }
}
