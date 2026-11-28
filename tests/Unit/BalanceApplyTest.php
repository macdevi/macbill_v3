<?php

use App\Models\User;
use App\Services\BillingLedger;
use App\Services\InvoicePeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\BillingLedgerTestCase;

uses(BillingLedgerTestCase::class);

function balDepositPayload(int $amount): array
{
    return [
        'request_key' => (string) Str::uuid(),
        'amount' => $amount,
        'paid_on' => InvoicePeriod::today()->format('Y-m-d'),
        'method' => 'cash',
        'confirmed' => '1',
    ];
}

beforeEach(function () {
    $this->balUser = User::factory()->createQuietly();
    $this->balCustomer = $this->ledgerCustomer();
});

test('setoran lebih besar dari tunggakan melunasi invoice dan sisanya tetap saldo', function () {
    $invoice = $this->ledgerInvoice($this->balCustomer);

    $this->actingAs($this->balUser)
        ->post(route('balances.deposit', $this->balCustomer), balDepositPayload(200000))
        ->assertSessionHas('status');

    expect($invoice->fresh()->status)->toBe('paid')
        ->and(app(BillingLedger::class)->balance($this->balCustomer->id))->toBe(50000);
});

test('setoran lebih kecil dari tunggakan membuat invoice dibayar sebagian', function () {
    $invoice = $this->ledgerInvoice($this->balCustomer);

    $this->actingAs($this->balUser)
        ->post(route('balances.deposit', $this->balCustomer), balDepositPayload(100000));

    expect($invoice->fresh()->status)->toBe('partial')
        ->and($invoice->fresh()->paid_amount)->toBe(100000)
        ->and(app(BillingLedger::class)->balance($this->balCustomer->id))->toBe(0);
});

test('setoran tanpa tunggakan menjadi saldo penuh', function () {
    $this->actingAs($this->balUser)
        ->post(route('balances.deposit', $this->balCustomer), balDepositPayload(75000));

    expect(app(BillingLedger::class)->balance($this->balCustomer->id))->toBe(75000);
});

test('setoran melunasi invoice tertua lebih dulu', function () {
    $older = $this->ledgerInvoice($this->balCustomer, 100000, 1);
    $newer = $this->ledgerInvoice($this->balCustomer, 100000);

    $this->actingAs($this->balUser)
        ->post(route('balances.deposit', $this->balCustomer), balDepositPayload(150000));

    expect($older->fresh()->status)->toBe('paid')
        ->and($newer->fresh()->paid_amount)->toBe(50000);
});

test('tombol pakai saldo melunasi tunggakan dari saldo yang ada', function () {
    $invoice = $this->ledgerInvoice($this->balCustomer);
    $this->ledgerPayment($this->balCustomer, $this->balUser, 100000, [], 'deposit');

    expect($invoice->fresh()->paid_amount)->toBe(0);

    $this->actingAs($this->balUser)
        ->post(route('balances.apply', $this->balCustomer))
        ->assertSessionHas('status');

    expect($invoice->fresh()->paid_amount)->toBe(100000)
        ->and($invoice->fresh()->status)->toBe('partial')
        ->and(app(BillingLedger::class)->balance($this->balCustomer->id))->toBe(0);
});

test('pakai saldo tanpa saldo atau tunggakan tidak mengubah apa pun', function () {
    $invoice = $this->ledgerInvoice($this->balCustomer);

    $this->actingAs($this->balUser)
        ->post(route('balances.apply', $this->balCustomer))
        ->assertSessionHas('status');

    expect($invoice->fresh()->paid_amount)->toBe(0);
});

test('daftar titip saldo hanya menampilkan pelanggan yang punya saldo', function () {
    $without = $this->ledgerCustomer();
    DB::table('customers')->where('id', $this->balCustomer->id)->update(['name' => 'Dengan Saldo Uji']);
    DB::table('customers')->where('id', $without->id)->update(['name' => 'Tanpa Saldo Uji']);

    $this->ledgerPayment($this->balCustomer, $this->balUser, 50000, [], 'deposit');

    $this->actingAs($this->balUser)->get(route('balances.index'))
        ->assertOk()->assertSee('Dengan Saldo Uji')->assertDontSee('Tanpa Saldo Uji');
});

test('pencarian langsung menampilkan semua pelanggan dan bisa disaring', function () {
    $other = $this->ledgerCustomer();
    DB::table('customers')->where('id', $this->balCustomer->id)->update(['name' => 'Dengan Saldo Uji']);
    DB::table('customers')->where('id', $other->id)->update(['name' => 'Tanpa Saldo Uji']);
    $this->ledgerPayment($this->balCustomer, $this->balUser, 50000, [], 'deposit');

    $this->actingAs($this->balUser)->getJson(route('balances.search'))
        ->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Dengan Saldo Uji')
        ->assertJsonPath('data.0.saldo', 50000);

    $this->actingAs($this->balUser)->getJson(route('balances.search', ['q' => 'Tanpa']))
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Tanpa Saldo Uji');
});

test('tamu diarahkan ke halaman masuk', function () {
    $this->get(route('balances.index'))->assertRedirect(route('login'));
});
