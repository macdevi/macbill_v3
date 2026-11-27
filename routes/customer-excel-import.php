<?php

use App\Http\Controllers\CustomerExcelImportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:access-billing'])
    ->prefix('customer-excel-import')
    ->name('customer-excel-import.')
    ->group(function () {
        Route::get('/', [CustomerExcelImportController::class, 'index'])->name('index');
        Route::post('/preview', [CustomerExcelImportController::class, 'preview'])
            ->middleware('throttle:6,1')->name('preview');
        Route::get('/{batch}', [CustomerExcelImportController::class, 'show'])
            ->whereUuid('batch')->name('show');
        Route::post('/{batch}', [CustomerExcelImportController::class, 'commit'])
            ->whereUuid('batch')->middleware('throttle:10,1')->name('commit');
        Route::delete('/{batch}', [CustomerExcelImportController::class, 'discard'])
            ->whereUuid('batch')->name('discard');
    });
