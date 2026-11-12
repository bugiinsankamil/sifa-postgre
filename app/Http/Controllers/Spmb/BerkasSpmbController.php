<?php

namespace App\Http\Controllers\Spmb;

use App\Http\Controllers\Controller;
use App\Models\Lampiran;
use App\Models\SpmbBerkas;
use App\Models\SpmbPendaftar;
use App\Support\CabangKonteks;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menampilkan berkas pendaftar kepada panitia. Berkas disimpan di disk privat, jadi hanya bisa dibuka
 * lewat rute ini: pengguna harus panitia SPMB dan pendaftarnya harus berada di cabang yang boleh ia akses.
 */
class BerkasSpmbController extends Controller
{
    public function tampil(Lampiran $lampiran): Response
    {
        abort_unless(app(CabangKonteks::class)->punyaPeran(config('spmb.peran_panitia', [])), 403);
        abort_unless($lampiran->lampiran_type === (new SpmbBerkas())->getMorphClass(), 404);

        $berkas = SpmbBerkas::query()->find($lampiran->lampiran_id);
        abort_if($berkas === null, 404);

        // Global scope cabang pada SpmbPendaftar membatasi ke cabang yang boleh diakses pengguna
        abort_unless(SpmbPendaftar::query()->whereKey($berkas->pendaftar_id)->exists(), 403);

        $disk = Storage::disk($lampiran->disk);
        abort_unless($disk->exists($lampiran->path), 404);

        return $disk->response($lampiran->path, $lampiran->nama_asli, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
