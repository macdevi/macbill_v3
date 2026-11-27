<?php

use App\Http\Controllers\CustomerPppoeImportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:access-billing'])
    ->prefix('customer-pppoe-import')
    ->name('customer-pppoe.')
    ->group(function () {
        Route::get('/', [CustomerPppoeImportController::class, 'index'])->name('index');
        Route::post('/preview', [CustomerPppoeImportController::class, 'preview'])
            ->middleware('throttle:6,1')->name('preview');
        Route::get('/{batch}', [CustomerPppoeImportController::class, 'show'])
            ->whereUuid('batch')->name('show');
        Route::post('/{batch}', [CustomerPppoeImportController::class, 'commit'])
            ->whereUuid('batch')->middleware('throttle:10,1')->name('commit');
        Route::delete('/{batch}', [CustomerPppoeImportController::class, 'discard'])
            ->whereUuid('batch')->name('discard');
    });
