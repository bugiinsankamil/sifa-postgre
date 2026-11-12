<?php

use App\Http\Controllers\Spmb\BerkasSpmbController;
use Illuminate\Support\Facades\Route;

// Dimuat dari routes/web.php:  require __DIR__.'/spmb_admin.php';
// Tanpa middleware auth: pengguna yang belum login tidak memegang peran apa pun sehingga ditolak (403).
Route::get('/berkas-spmb/{lampiran}', [BerkasSpmbController::class, 'tampil'])
    ->whereNumber('lampiran')
    ->name('spmb.admin.berkas');
