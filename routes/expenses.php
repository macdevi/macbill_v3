<?php

use App\Http\Controllers\ExpenseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:access-billing'])
    ->prefix('expenses')->name('expenses.')->group(function () {
        Route::get('/', [ExpenseController::class, 'index'])->name('index');
        Route::post('/', [ExpenseController::class, 'store'])
            ->middleware('throttle:20,1')->name('store');
        Route::post('/{expense}/void', [ExpenseController::class, 'void'])
            ->whereNumber('expense')->middleware('throttle:10,1')->name('void');
    });
