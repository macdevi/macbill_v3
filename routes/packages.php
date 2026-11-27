<?php

use App\Http\Controllers\InternetPackageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:manage-system'])
    ->prefix('packages')
    ->name('packages.')
    ->group(function () {
        Route::post('/{package}/routers/{router}/preview', [\App\Http\Controllers\PackageSyncController::class, 'preview'])
            ->middleware('throttle:12,1')->name('preview');
        Route::post('/{package}/routers/{router}/push', [\App\Http\Controllers\PackageSyncController::class, 'push'])
            ->middleware('throttle:6,1')->name('push');
        Route::get('/router-profiles/{router}/details', \App\Http\Controllers\PackageProfileDetailsController::class)
            ->middleware('throttle:60,1')->name('profile-details');
        Route::get('/', [InternetPackageController::class, 'index'])->name('index');
        Route::post('/', [InternetPackageController::class, 'store'])->name('store');
        Route::put('/{package}', [InternetPackageController::class, 'update'])->name('update');
        Route::delete('/{package}', [InternetPackageController::class, 'destroy'])->name('destroy');
    });
