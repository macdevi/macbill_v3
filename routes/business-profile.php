<?php

use App\Http\Controllers\BusinessProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:manage-users'])
    ->prefix('business-profile')
    ->name('business-profile.')
    ->group(function () {
        Route::get('/', [BusinessProfileController::class, 'edit'])
            ->name('edit');

        Route::put('/', [BusinessProfileController::class, 'update'])
            ->middleware('throttle:20,1')
            ->name('update');
    });
