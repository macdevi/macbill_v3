<?php

use App\Http\Controllers\PasangBaruController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:access-billing'])
    ->prefix('pasang-baru')
    ->name('pasang-baru.')
    ->group(function () {
        Route::get('/', [PasangBaruController::class, 'create'])->name('create');
        Route::post('/', [PasangBaruController::class, 'store'])->name('store');
        Route::post('/{customer}/aktivasi', [PasangBaruController::class, 'activate'])
            ->name('activate');
    });
