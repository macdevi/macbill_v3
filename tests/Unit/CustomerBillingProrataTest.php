<?php

use App\Models\BillingSetting;
use App\Models\Customer;
use App\Models\CustomerBillingPause;
use App\Models\Invoice;
use App\Services\CustomerBillingProrata;
use App\Services\InvoiceGenerator;
use App\Services\InvoicePeriod;
use Carbon\CarbonImmutable;
use Tests\BillingLedgerTestCase;

uses(BillingLedgerTestCase::class);

function billingPauseFixture(
    Customer $customer,
    string $from,
    ?string $resume = null
): CustomerBillingPause {
    return CustomerBillingPause::create([
        'customer_id' => $customer->id,
        'open_customer_id' => $resume === null ? $customer->id : null,
        'paused_from' => $from,
        'resumed_on' => $resume,
        'isolated_at' => $from.' 00:00:00',
        'isolated_by_name' => 'Petugas pengujian',
        'isolation_reason' => 'Jeda pengujian',
        'resumed_at' => $resume ? $resume.' 00:00:00' : null,
        'resumed_by_name' => $resume ? 'Petugas pengujian' : null,
        'resume_reason' => $resume ? 'Pemulihan pengujian' : null,
    ]);
}

beforeEach(function () {
    CarbonImmutable::setTestNow(
        CarbonImmutable::parse(
            '2026-12-15 12:00:00',
            InvoicePeriod::TZ
        )
    );

    $this->billingPauseCustomer = $this->ledgerCustomer();
    $this->billingPauseCustomer->forceFill([
        'invoice_day' => 15,
        'price_mode' => 'custom',
        'custom_monthly_price' => 150000,
        'tax_enabled' => false,
    ])->saveQuietly();

    $settings = BillingSetting::current();
    $settings->forceFill([
        'tax_rate_bps' => 0,
        'tax_mode' => 'exclude',
        'due_grace_days' => 0,
    ])->save();
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('tanpa riwayat jeda harga invoice tetap penuh', function () {
    [$quote, $reason] = app(InvoiceGenerator::class)->prepare(
        $this->billingPauseCustomer,
        BillingSetting::current(),
        2026,
        12
    );

    expect($reason)->toBeNull()
        ->and($quote['total'])->toBe(150000)
        ->and($quote['billing_prorata']['paused_days'])->toBe(0);
});

test('riwayat pelanggan lain tidak mempengaruhi prorata', function () {
    $other = $this->ledgerCustomer();
    billingPauseFixture($other, '2026-11-01');

    $result = CustomerBillingProrata::forPeriod(
        $this->billingPauseCustomer,
        150000,
        '2026-11-15',
        '2026-12-14'
    );

    expect($result['price'])->toBe(150000)
        ->and($result['paused_days'])->toBe(0);
});

test('invoice prorata menyimpan snapshot tanpa mengubah invoice lama', function () {
    $old = $this->ledgerInvoice($this->billingPauseCustomer);
    $oldAttributes = $old->fresh()->getRawOriginal();

    billingPauseFixture(
        $this->billingPauseCustomer,
        '2026-11-01',
        '2026-12-01'
    );

    [$invoice, $reason] = app(InvoiceGenerator::class)->issue(
        $this->billingPauseCustomer->id,
        2026,
        12,
        null,
        'manual'
    );

    expect($reason)->toBeNull()
        ->and($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->price)->toBe(70000)
        ->and($invoice->total)->toBe(70000)
        ->and($invoice->snapshot['monthly_price'])->toBe(150000)
        ->and($invoice->snapshot['billing_prorata']['active_days'])->toBe(14)
        ->and($old->fresh()->getRawOriginal())->toBe($oldAttributes);
});

test('pajak dihitung dari nominal setelah prorata', function () {
    $this->billingPauseCustomer->forceFill([
        'tax_enabled' => true,
    ])->saveQuietly();

    $settings = BillingSetting::current();
    $settings->forceFill([
        'tax_rate_bps' => 1100,
        'tax_mode' => 'exclude',
    ])->save();

    billingPauseFixture(
        $this->billingPauseCustomer,
        '2026-11-01',
        '2026-12-01'
    );

    [$quote, $reason] = app(InvoiceGenerator::class)->prepare(
        $this->billingPauseCustomer,
        $settings,
        2026,
        12
    );

    expect($reason)->toBeNull()
        ->and($quote['price'])->toBe(70000)
        ->and($quote['tax'])->toBe(7700)
        ->and($quote['total'])->toBe(77700);
});

test('jeda penuh tidak menerbitkan invoice nol', function () {
    billingPauseFixture(
        $this->billingPauseCustomer,
        '2026-11-01'
    );

    [$invoice, $reason] = app(InvoiceGenerator::class)->issue(
        $this->billingPauseCustomer->id,
        2026,
        12,
        null,
        'manual'
    );

    expect($invoice)->toBeNull()
        ->and($reason)->toContain('Rp0')
        ->and(Invoice::count())->toBe(0);
});

test('jeda terbuka tetap menagihkan hari sebelum jeda', function () {
    billingPauseFixture(
        $this->billingPauseCustomer,
        '2026-11-25'
    );

    [$quote, $reason] = app(InvoiceGenerator::class)->prepare(
        $this->billingPauseCustomer,
        BillingSetting::current(),
        2026,
        12
    );

    expect($reason)->toBeNull()
        ->and($quote['billing_prorata']['active_days'])->toBe(10)
        ->and($quote['total'])->toBe(50000);
});

test('snapshot invoice tetap setelah riwayat jeda berubah', function () {
    $pause = billingPauseFixture(
        $this->billingPauseCustomer,
        '2026-11-25'
    );

    [$invoice, $reason] = app(InvoiceGenerator::class)->issue(
        $this->billingPauseCustomer->id,
        2026,
        12,
        null,
        'manual'
    );

    expect($reason)->toBeNull();

    $snapshot = $invoice->snapshot;

    $pause->update([
        'open_customer_id' => null,
        'resumed_on' => '2026-12-01',
        'resumed_at' => '2026-12-01 00:00:00',
        'resumed_by_name' => 'Petugas pengujian',
        'resume_reason' => 'Pemulihan pengujian',
    ]);

    expect($invoice->fresh()->snapshot)->toBe($snapshot)
        ->and($invoice->fresh()->total)->toBe(50000);
});

test('batas tanggal jeda mengikuti hari kalender periode', function (
    string $from,
    ?string $resume,
    int $pausedDays,
    int $activeDays,
    int $price
) {
    billingPauseFixture(
        $this->billingPauseCustomer,
        $from,
        $resume
    );

    $result = CustomerBillingProrata::forPeriod(
        $this->billingPauseCustomer,
        150000,
        '2026-11-15',
        '2026-12-14'
    );

    expect($result['period_days'])->toBe(30)
        ->and($result['paused_days'])->toBe($pausedDays)
        ->and($result['active_days'])->toBe($activeDays)
        ->and($result['price'])->toBe($price);
})->with([
    'jeda terbuka mulai hari terakhir' => [
        '2026-12-14', null, 1, 29, 145000,
    ],
    'jeda hari terakhir dipulihkan setelah periode' => [
        '2026-12-14', '2026-12-15', 1, 29, 145000,
    ],
    'jeda baru mulai setelah periode' => [
        '2026-12-15', null, 0, 30, 150000,
    ],
    'pemulihan tepat hari pertama' => [
        '2026-11-01', '2026-11-15', 0, 30, 150000,
    ],
    'jeda mulai hari pertama dipulihkan besoknya' => [
        '2026-11-15', '2026-11-16', 1, 29, 145000,
    ],
    'isolir dan pemulihan pada tanggal batas yang sama' => [
        '2026-12-14', '2026-12-14', 0, 30, 150000,
    ],
]);

test('jeda yang berakhir pada awal periode tidak masuk snapshot jeda', function () {
    billingPauseFixture(
        $this->billingPauseCustomer,
        '2026-11-01',
        '2026-11-15'
    );

    $result = CustomerBillingProrata::forPeriod(
        $this->billingPauseCustomer,
        150000,
        '2026-11-15',
        '2026-12-14'
    );

    expect($result['pauses'])->toBe([])
        ->and($result['price'])->toBe(150000);
});

test('invoice menyimpan pengurangan jeda pada hari terakhir periode', function () {
    billingPauseFixture(
        $this->billingPauseCustomer,
        '2026-12-14'
    );

    [$invoice, $reason] = app(InvoiceGenerator::class)->issue(
        $this->billingPauseCustomer->id,
        2026,
        12,
        null,
        'manual'
    );

    expect($reason)->toBeNull()
        ->and($invoice)->toBeInstanceOf(Invoice::class);

    expect($invoice->price)->toBe(145000)
        ->and($invoice->total)->toBe(145000)
        ->and($invoice->snapshot['billing_prorata']['paused_days'])->toBe(1)
        ->and($invoice->snapshot['billing_prorata']['active_days'])->toBe(29);
});
