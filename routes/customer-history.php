<?php

use App\Http\Controllers\CustomerHistoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:access-billing'])
    ->get('/customers/{customer}/history', [
        CustomerHistoryController::class, 'index',
    ])
    ->whereNumber('customer')
    ->name('customers.history');
