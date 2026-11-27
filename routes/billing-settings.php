<?php

use App\Http\Controllers\BillingSettingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:manage-users'])
    ->prefix('billing-settings')
    ->name('billing-settings.')
    ->group(function () {
        Route::get('/', [BillingSettingController::class, 'index'])->name('index');
        Route::put('/', [BillingSettingController::class, 'update'])
            ->middleware('throttle:20,1')
            ->name('update');
    });
