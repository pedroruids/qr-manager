<?php

use App\Http\Controllers\DescarregarQrCodeController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('codigos/{qrCode}', 'pages::codigos.detalhe')->name('codigos.detalhe');

    Route::get('codigos/{qrCode}/descarregar/{formato}', DescarregarQrCodeController::class)
        ->name('codigos.descarregar');
});

require __DIR__.'/settings.php';
