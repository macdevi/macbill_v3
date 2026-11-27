<?php

use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:access-billing'])
    ->prefix('invoices')->name('invoices.')->group(function () {
        Route::get('/', [InvoiceController::class, 'index'])->name('index');
        Route::get('/create', [InvoiceController::class, 'create'])->name('create');
        Route::post('/', [InvoiceController::class, 'store'])
            ->middleware('throttle:6,1')->name('store');
        Route::post('/{invoice}/void', [InvoiceController::class, 'void'])
            ->whereNumber('invoice')->middleware('throttle:10,1')->name('void');
        Route::get('/{invoice}/payment-history', [InvoiceController::class, 'paymentHistory'])
            ->whereNumber('invoice')->name('payment-history');
        Route::get('/{invoice}/notes', [InvoiceController::class, 'invoiceNotes'])
            ->whereNumber('invoice')->name('notes');
        Route::get('/{invoice}', [InvoiceController::class, 'show'])
            ->whereNumber('invoice')->name('show');
    });

Route::middleware(['auth', 'verified', 'can:access-billing'])->group(function () {
    $p = \App\Http\Controllers\PaymentController::class;
    $b = \App\Http\Controllers\BalanceController::class;

    Route::post('/payments', [$p, 'store'])->middleware('throttle:20,1')->name('payments.store');
    Route::get('/payments/{receipt}/nota', [$p, 'nota'])->whereNumber('receipt')->name('payments.nota');
    Route::post('/payments/{receipt}/void', [$p, 'void'])
        ->whereNumber('receipt')->middleware('throttle:10,1')->name('payments.void');

    Route::get('/balances', [$b, 'index'])->name('balances.index');
    Route::get('/balances/{customer}', [$b, 'show'])->whereNumber('customer')->name('balances.show');
    Route::post('/balances/{customer}/deposit', [$b, 'deposit'])
        ->whereNumber('customer')->middleware('throttle:20,1')->name('balances.deposit');
    Route::post('/balance-entries/{entry}/void', [$b, 'voidUse'])
        ->whereNumber('entry')->middleware('throttle:10,1')->name('balances.void-use');
});
