<?php

require __DIR__ . '/spmb.php';

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
