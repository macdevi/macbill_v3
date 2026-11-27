<?php

use App\Http\Controllers\CustomerBulkPriceController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerDeletionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:access-billing'])
    ->prefix('customers')
    ->name('customers.')
    ->group(function () {
        Route::get('/bulk-prices', [
            CustomerBulkPriceController::class, 'index',
        ])->name('bulk-prices.index');

        Route::patch('/bulk-prices', [
            CustomerBulkPriceController::class, 'update',
        ])->name('bulk-prices.update');

        Route::get('/', [CustomerController::class, 'index'])->name('index');
        Route::post('/', [CustomerController::class, 'store'])->name('store');
        Route::put('/{customer}', [CustomerController::class, 'update'])->name('update');

        Route::delete('/{customer}', [CustomerDeletionController::class, 'destroy'])
            ->name('destroy');
    });
