<?php

use App\Http\Controllers\WilayahController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:manage-users'])
    ->prefix('wilayah')
    ->name('wilayah.')
    ->group(function () {
        Route::get('/', [WilayahController::class, 'index'])->name('index');
        Route::post('/', [WilayahController::class, 'store'])->name('store');
        Route::put('/{wilayah}', [WilayahController::class, 'update'])->name('update');
        Route::delete('/{wilayah}', [WilayahController::class, 'destroy'])->name('destroy');
    });
