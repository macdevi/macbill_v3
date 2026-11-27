<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardCustomerConnectionController;
use App\Http\Controllers\DashboardCustomerDetailController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('dashboard/customer-connections/{router}', [
        DashboardCustomerConnectionController::class,
        '__invoke',
    ])->middleware(['can:access-billing', 'throttle:90,1'])
        ->whereNumber('router')
        ->name('dashboard.customer-status');

    Route::get('dashboard/customer-details', [
        DashboardCustomerDetailController::class,
        '__invoke',
    ])->middleware(['can:access-billing', 'throttle:90,1'])
        ->name('dashboard.customer-details');
});

require __DIR__.'/settings.php';

require __DIR__.'/routers.php';

require __DIR__.'/packages.php';

require __DIR__.'/billing-settings.php';

require __DIR__.'/customers.php';
require __DIR__.'/pasang-baru.php';

require __DIR__.'/customer-pppoe-import.php';

require __DIR__.'/customer-excel.php';

require __DIR__.'/customer-excel-import.php';

require __DIR__.'/customer-history.php';

require __DIR__.'/customer-network.php';

require __DIR__.'/invoices.php';

require __DIR__.'/expenses.php';

require __DIR__.'/business-profile.php';
require __DIR__.'/users.php';
require __DIR__.'/wilayah.php';
