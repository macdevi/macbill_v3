<?php

use App\Http\Controllers\DataResetController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:manage-users'])
    ->delete('data-reset', [DataResetController::class, 'destroy'])
    ->middleware('throttle:5,1')
    ->name('data-reset.destroy');
