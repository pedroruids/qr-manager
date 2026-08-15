<?php

use App\Http\Controllers\Api\QrCodeController;
use App\Http\Controllers\DescarregarQrCodeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| A API é autenticada por token e limitada por token — não por IP. Duas
| ferramentas do mesmo cliente atrás do mesmo IP não se estorvam uma à outra, e
| um token que se descontrola não leva os outros com ele.
*/

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());

    Route::get('qr-codes', [QrCodeController::class, 'index'])->name('api.qr-codes.index');
    Route::post('qr-codes', [QrCodeController::class, 'store'])->name('api.qr-codes.store');
    Route::get('qr-codes/{qrCode}', [QrCodeController::class, 'show'])->name('api.qr-codes.show');
    Route::patch('qr-codes/{qrCode}', [QrCodeController::class, 'update'])->name('api.qr-codes.update');

    // O mesmo controlador que serve o download da interface: o formato, os
    // limites de tamanho e o nome do ficheiro são os mesmos, e não há razão
    // para os escrever duas vezes.
    Route::get('qr-codes/{qrCode}/ficheiro/{formato}', DescarregarQrCodeController::class)
        ->name('api.qr-codes.ficheiro');
});
