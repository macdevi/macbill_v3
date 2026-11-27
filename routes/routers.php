<?php

use App\Http\Controllers\NetworkRouterController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:manage-system'])
    ->prefix('routers')
    ->name('routers.')
    ->group(function () {

        Route::post('/{router}/test', \App\Http\Controllers\RouterConnectionTestController::class)
            ->middleware('throttle:6,1')
            ->name('test');
        Route::get('/{router}/ppp-profiles', \App\Http\Controllers\RouterProfilesController::class)
            ->middleware('throttle:6,1')
            ->name('profiles');
        Route::get('/', [NetworkRouterController::class, 'index'])->name('index');
        Route::post('/', [NetworkRouterController::class, 'store'])->name('store');
        Route::put('/{router}', [NetworkRouterController::class, 'update'])->name('update');
        Route::delete('/{router}', [NetworkRouterController::class, 'destroy'])->name('destroy');
    });

// Monitoring baca-saja untuk akun billing yang diizinkan.
Route::get('/monitoring/router-status', \App\Http\Controllers\RouterMonitorController::class)
    ->middleware(['auth', 'verified', 'can:access-billing', 'throttle:120,1'])
    ->name('routers.monitor');
