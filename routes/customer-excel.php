<?php

use App\Http\Controllers\CustomerExcelController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:access-billing'])
    ->prefix('customer-excel')
    ->name('customer-excel.')
    ->group(function () {
        Route::get('/template', [CustomerExcelController::class, 'template'])
            ->middleware('throttle:10,1')->name('template');
        Route::get('/export', [CustomerExcelController::class, 'confirm'])->name('confirm');
        Route::post('/export', [CustomerExcelController::class, 'export'])
            ->middleware('throttle:5,1')->name('export');
    });
