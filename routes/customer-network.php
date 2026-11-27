<?php

use App\Http\Controllers\CustomerIsolationController;
use App\Http\Controllers\CustomerNetworkController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth', 'verified', 'can:access-billing', 'throttle:10,1',
])->post('/customers/{customer}/network-check', [
    CustomerNetworkController::class, 'check',
])->whereNumber('customer')->name('customers.network.check');

Route::middleware([
    'auth', 'verified', 'can:access-billing', 'throttle:90,1',
])->post('/customer-network-status', [
    CustomerNetworkController::class, 'status',
])->name('customers.network.status');

Route::middleware([
    'auth', 'verified', 'can:access-billing', 'throttle:5,1',
])->post('/customers/{customer}/isolation', [
    CustomerIsolationController::class, 'store',
])->whereNumber('customer')->name('customers.isolation.store');
