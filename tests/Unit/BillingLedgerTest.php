<?php

use App\Models\BillingAllocation;
use App\Models\BillingBalanceEntry;
use App\Models\BillingReceipt;
use App\Models\User;
use App\Services\BillingCorrections;
use App\Services\BillingLedger;
use App\Services\ReceiptText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\BillingLedgerTestCase;

uses(BillingLedgerTestCase::class);

beforeEach(function () {
    $this->ledgerUser = User::factory()->createQuietly();
    $this->ledgerCustomer = $this->ledgerCustomer();
});

test('pembayaran sebagian menghitung sisa dan status', function () {
    $invoice = $this->ledgerInvoice($this->ledgerCustomer);

    $this->ledgerPayment($this->ledgerCustomer, $this->ledgerUser, 100000);

    expect($invoice->fresh()->status)->toBe('partial')
        ->and($invoice->fresh()->paid_amount)->toBe(100000)
        ->and($invoice->fresh()->remaining)->toBe(50000)
        ->and(app(BillingLedger::class)->balance($this->ledgerCustomer->id))->toBe(0);
});

test('pembayaran penuh menghasilkan invoice lunas', function () {
    $invoice = $this->ledgerInvoice($this->ledgerCustomer);

    $this->ledgerPayment($this->ledgerCustomer, $this->ledgerUser, 150000);

    expect($invoice->fresh()->status)->toBe('paid')
        ->and($invoice->fresh()->remaining)->toBe(0)
        ->and(BillingReceipt::count())->toBe(1);
});

test('invoice prioritas dibayar lebih dahulu kemudian invoice tertua', function () {
    $older = $this->ledgerInvoice($this->ledgerCustomer, 100000, 1);
    $newer = $this->ledgerInvoice($this->ledgerCustomer, 100000);

    $receipt = $this->ledgerPayment(
        $this->ledgerCustomer,
        $this->ledgerUser,
        150000,
        ['priority_ids' => [$newer->id]]
    );

    expect($newer->fresh()->paid_amount)->toBe(100000)
        ->and($older->fresh()->paid_amount)->toBe(50000)
        ->and($receipt->allocations()->count())->toBe(2);
});

test('kelebihan pembayaran menjadi saldo dan masuk snapshot', function () {
    $invoice = $this->ledgerInvoice($this->ledgerCustomer);
    $receipt = $this->ledgerPayment(
        $this->ledgerCustomer, $this->ledgerUser, 200000
    );

    expect($invoice->fresh()->status)->toBe('paid')
        ->and(app(BillingLedger::class)->balance($this->ledgerCustomer->id))->toBe(50000)
        ->and($receipt->receipt_snapshot['credit_added'])->toBe(50000)
        ->and($receipt->receipt_snapshot['credit_balance'])->toBe(50000);
});

test('request key yang sama ditolak tanpa transaksi tambahan', function () {
    $invoice = $this->ledgerInvoice($this->ledgerCustomer);
    $key = (string) Str::uuid();

    $this->ledgerPayment(
        $this->ledgerCustomer, $this->ledgerUser, 50000,
        ['request_key' => $key]
    );

    expect(fn () => $this->ledgerPayment(
        $this->ledgerCustomer, $this->ledgerUser, 50000,
        ['request_key' => $key]
    ))->toThrow(ValidationException::class);

    expect(BillingReceipt::count())->toBe(1)
        ->and(BillingAllocation::count())->toBe(1)
        ->and($invoice->fresh()->paid_amount)->toBe(50000);
});

test('invoice pelanggan lain tidak boleh dipilih', function () {
    $own = $this->ledgerInvoice($this->ledgerCustomer);
    $otherCustomer = $this->ledgerCustomer();
    $other = $this->ledgerInvoice($otherCustomer);

    expect(fn () => $this->ledgerPayment(
        $this->ledgerCustomer, $this->ledgerUser, 50000,
        ['priority_ids' => [$other->id]]
    ))->toThrow(ValidationException::class);

    expect(BillingReceipt::count())->toBe(0)
        ->and($own->fresh()->paid_amount)->toBe(0)
        ->and($other->fresh()->paid_amount)->toBe(0);
});

test('pembatalan penerimaan menghitung ulang semua invoice terkait', function () {
    $older = $this->ledgerInvoice($this->ledgerCustomer, 100000, 1);
    $newer = $this->ledgerInvoice($this->ledgerCustomer, 100000);

    $receipt = $this->ledgerPayment(
        $this->ledgerCustomer, $this->ledgerUser, 150000
    );

    app(BillingCorrections::class)->voidReceipt(
        $receipt->id, $this->ledgerUser, 'Pembayaran contoh dibatalkan'
    );

    expect($receipt->fresh()->status)->toBe('void')
        ->and($older->fresh()->status)->toBe('unpaid')
        ->and($older->fresh()->paid_amount)->toBe(0)
        ->and($newer->fresh()->status)->toBe('unpaid')
        ->and($newer->fresh()->paid_amount)->toBe(0)
        ->and(BillingAllocation::where('status', 'active')->count())->toBe(0)
        ->and(BillingAllocation::count())->toBe(2);
});

test('pembatalan berantai harus mengembalikan pemakaian saldo dahulu', function () {
    $first = $this->ledgerInvoice($this->ledgerCustomer, 150000, 1);
    $receipt = $this->ledgerPayment(
        $this->ledgerCustomer, $this->ledgerUser, 200000
    );

    $second = $this->ledgerInvoice($this->ledgerCustomer, 100000);
    $ledger = app(BillingLedger::class);
    $corrections = app(BillingCorrections::class);

    expect($ledger->applyBalance(
        $this->ledgerCustomer->id, $this->ledgerUser
    ))->toBe(50000);

    expect($second->fresh()->paid_amount)->toBe(50000)
        ->and($ledger->balance($this->ledgerCustomer->id))->toBe(0);

    expect(fn () => $corrections->voidReceipt(
        $receipt->id, $this->ledgerUser, 'Uji penolakan saldo terpakai'
    ))->toThrow(ValidationException::class);

    expect($receipt->fresh()->status)->toBe('active')
        ->and($first->fresh()->paid_amount)->toBe(150000)
        ->and($second->fresh()->paid_amount)->toBe(50000);

    $use = BillingBalanceEntry::where('type', 'use')
        ->where('status', 'active')->firstOrFail();

    $corrections->voidBalanceUse(
        $use->id, $this->ledgerUser, 'Kembalikan pemakaian saldo contoh'
    );

    expect($second->fresh()->paid_amount)->toBe(0)
        ->and($ledger->balance($this->ledgerCustomer->id))->toBe(50000);

    $corrections->voidReceipt(
        $receipt->id, $this->ledgerUser, 'Batalkan penerimaan contoh'
    );

    expect($receipt->fresh()->status)->toBe('void')
        ->and($first->fresh()->paid_amount)->toBe(0)
        ->and($second->fresh()->paid_amount)->toBe(0)
        ->and($ledger->balance($this->ledgerCustomer->id))->toBe(0);
});

test('pembatalan penerimaan berulang ditolak', function () {
    $invoice = $this->ledgerInvoice($this->ledgerCustomer);
    $receipt = $this->ledgerPayment(
        $this->ledgerCustomer, $this->ledgerUser, 150000
    );
    $corrections = app(BillingCorrections::class);

    $corrections->voidReceipt(
        $receipt->id, $this->ledgerUser, 'Pembatalan pertama untuk pengujian'
    );

    expect(fn () => $corrections->voidReceipt(
        $receipt->id, $this->ledgerUser, 'Pembatalan kedua untuk pengujian'
    ))->toThrow(ValidationException::class);

    expect($invoice->fresh()->paid_amount)->toBe(0)
        ->and($receipt->fresh()->status)->toBe('void');
});

test('titip saldo tidak otomatis melunasi tunggakan', function () {
    $invoice = $this->ledgerInvoice($this->ledgerCustomer);

    $receipt = $this->ledgerPayment(
        $this->ledgerCustomer, $this->ledgerUser, 100000,
        [], 'deposit'
    );

    expect($receipt->kind)->toBe('deposit')
        ->and($invoice->fresh()->paid_amount)->toBe(0)
        ->and($invoice->fresh()->status)->toBe('unpaid')
        ->and(app(BillingLedger::class)->balance($this->ledgerCustomer->id))->toBe(100000)
        ->and(BillingAllocation::count())->toBe(0);
});

test('snapshot nota tidak berubah setelah pelunasan dan perubahan nama', function () {
    $invoice = $this->ledgerInvoice($this->ledgerCustomer);
    $first = $this->ledgerPayment(
        $this->ledgerCustomer, $this->ledgerUser, 100000
    );
    $snapshot = $first->receipt_snapshot;

    $this->ledgerPayment(
        $this->ledgerCustomer, $this->ledgerUser, 50000
    );

    DB::table('customers')->where('id', $this->ledgerCustomer->id)
        ->update(['name' => 'Nama sesudah pembayaran']);

    $nota = ReceiptText::build($first->fresh(), null);

    expect($invoice->fresh()->status)->toBe('paid')
        ->and($first->fresh()->receipt_snapshot)->toBe($snapshot)
        ->and($snapshot['outstanding'])->toBe(50000)
        ->and($nota['text'])->toContain('Pelanggan contoh pengujian')
        ->and($nota['text'])->toContain('Rp50.000')
        ->and($nota['text'])->not->toContain('Nama sesudah pembayaran');
});

test('nota penerimaan batal memiliki penanda dan snapshot tetap utuh', function () {
    $this->ledgerInvoice($this->ledgerCustomer);
    $receipt = $this->ledgerPayment(
        $this->ledgerCustomer, $this->ledgerUser, 150000
    );
    $snapshot = $receipt->receipt_snapshot;

    app(BillingCorrections::class)->voidReceipt(
        $receipt->id, $this->ledgerUser, 'Uji penanda pembatalan nota'
    );

    $nota = ReceiptText::build($receipt->fresh(), null);

    expect($nota['text'])->toStartWith('*** DIBATALKAN ***')
        ->and($receipt->fresh()->receipt_snapshot)->toBe($snapshot);
});
