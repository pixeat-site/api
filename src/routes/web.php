<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DownloadController;

Route::get('/', function () {
    return view('welcome');
});

// Rotas de download otimizadas com suporte a resumable downloads
Route::get('/downloads/{filename}', [DownloadController::class, 'downloadApk'])
    ->where('filename', 'pixeat-v\d+\.\d+\.\d+\.apk')
    ->name('download.apk');

// Informações sobre downloads
Route::get('/downloads', [DownloadController::class, 'info'])->name('downloads.info');

