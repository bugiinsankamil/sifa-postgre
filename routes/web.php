<?php

require __DIR__ . '/spmb.php';
require __DIR__ . '/spmb_admin.php';

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
