<?php

use App\Http\Controllers\DataResetController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:manage-users'])
    ->prefix('data-reset')
    ->name('data-reset.')
    ->group(function () {
        Route::get('/', [DataResetController::class, 'index'])
            ->name('index');

        Route::delete('/', [DataResetController::class, 'destroy'])
            ->middleware('throttle:5,1')
            ->name('destroy');
    });
