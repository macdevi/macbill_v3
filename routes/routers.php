<?php

use App\Http\Controllers\NetworkRouterController;
use App\Http\Controllers\RouterConnectionTestController;
use App\Http\Controllers\RouterMonitorController;
use App\Http\Controllers\RouterProfilesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:manage-system'])
    ->prefix('routers')
    ->name('routers.')
    ->group(function () {

        Route::post('/{router}/test', RouterConnectionTestController::class)
            ->middleware('throttle:6,1')
            ->name('test');
        Route::get('/{router}/ppp-profiles', RouterProfilesController::class)
            ->middleware('throttle:6,1')
            ->name('profiles');
        Route::get('/', [NetworkRouterController::class, 'index'])->name('index');
        Route::post('/', [NetworkRouterController::class, 'store'])->name('store');
        Route::put('/{router}', [NetworkRouterController::class, 'update'])->name('update');
        Route::delete('/{router}', [NetworkRouterController::class, 'destroy'])->name('destroy');
    });

// Monitoring baca-saja untuk akun billing yang diizinkan.
Route::get('/monitoring/router-status', RouterMonitorController::class)
    ->middleware(['auth', 'verified', 'can:access-billing', 'throttle:120,1'])
    ->name('routers.monitor');
