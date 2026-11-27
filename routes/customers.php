<?php

use App\Http\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:access-billing'])
    ->prefix('customers')
    ->name('customers.')
    ->group(function () {
        Route::get('/bulk-prices', [
            \App\Http\Controllers\CustomerBulkPriceController::class, 'index',
        ])->name('bulk-prices.index');

        Route::patch('/bulk-prices', [
            \App\Http\Controllers\CustomerBulkPriceController::class, 'update',
        ])->name('bulk-prices.update');

        Route::get('/', [CustomerController::class, 'index'])->name('index');
        Route::post('/', [CustomerController::class, 'store'])->name('store');
        Route::put('/{customer}', [CustomerController::class, 'update'])->name('update');

        Route::delete('/{customer}', [\App\Http\Controllers\CustomerDeletionController::class, 'destroy'])
            ->name('destroy');
    });
