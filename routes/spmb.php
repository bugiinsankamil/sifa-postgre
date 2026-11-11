<?php

use App\Http\Controllers\Spmb\PendaftaranSpmbController;
use Illuminate\Support\Facades\Route;

// Formulir pendaftaran publik. Dimuat dari routes/web.php:  require __DIR__.'/spmb.php';
Route::prefix('spmb')->name('spmb.')->controller(PendaftaranSpmbController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/status', 'statusForm')->name('status');
    Route::post('/status', 'statusCek')->middleware('throttle:10,1')->name('status.cek');
    Route::get('/terdaftar', 'terdaftar')->name('terdaftar');
    Route::get('/{periode}', 'form')->whereNumber('periode')->name('form');
    Route::post('/{periode}', 'store')->whereNumber('periode')->middleware('throttle:10,1')->name('store');
});
