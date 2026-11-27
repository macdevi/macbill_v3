<?php

use App\Models\CustomerNetworkAction;
use App\Models\Expense;
use App\Models\User;
use App\Services\BillingCorrections;
use App\Services\BillingLedger;
use App\Services\DashboardSummary;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\BillingLedgerTestCase;

uses(BillingLedgerTestCase::class);

beforeEach(function () {
    $this->dashboardActor = User::factory()->createQuietly();
    Gate::define('access-billing', fn ($user) => true);
    Gate::define('manage-system', fn ($user) => false);
});

function dashboardTestInvoice($test)
{
    $customer = $test->ledgerCustomer();
    $invoice = $test->ledgerInvoice($customer);

    $invoice->forceFill([
        'issued_on' => '2020-02-10',
        'due_on' => '2020-02-20',
        'price' => 100000,
        'net' => 100000,
        'tax' => 0,
        'total' => 100000,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ])->save();

    return [$customer, $invoice];
}

function dashboardTestReceipt($test, $customer, int $amount, string $kind = 'payment')
{
    return app(BillingLedger::class)->receive(
        $customer->id,
        $test->dashboardActor,
        [
            'request_key' => (string) Str::uuid(),
            'amount' => $amount,
            'paid_on' => '2020-02-15',
            'method' => 'cash',
            'priority_ids' => [],
            'confirmed' => true,
        ],
        $kind
    );
}

test('dashboard menghitung kas sekali termasuk kelebihan pembayaran dan titip saldo', function () {
    [$customer] = dashboardTestInvoice($this);

    dashboardTestReceipt($this, $customer, 140000);
    dashboardTestReceipt($this, $customer, 20000, 'deposit');

    $data = app(DashboardSummary::class)->forMonth('2020-02');

    expect($data['finance']['issued'])->toBe(100000)
        ->and($data['finance']['cash_in'])->toBe(160000)
        ->and($data['finance']['deposit_in'])->toBe(20000)
        ->and($data['finance']['balance'])->toBe(60000)
        ->and($data['finance']['receivables'])->toBe(0);
});

test('pembayaran dibatalkan tidak dihitung sebagai kas masuk', function () {
    [$customer] = dashboardTestInvoice($this);
    $receipt = dashboardTestReceipt($this, $customer, 100000);

    app(BillingCorrections::class)->voidReceipt(
        $receipt->id, $this->dashboardActor, 'Koreksi penerimaan untuk pengujian'
    );

    $data = app(DashboardSummary::class)->forMonth('2020-02');

    expect($data['finance']['cash_in'])->toBe(0)
        ->and($data['finance']['receivables'])->toBe(100000);
});

test('pengeluaran aktif dihitung dan pengeluaran batal dikecualikan', function () {
    foreach (['active' => 30000, 'void' => 90000] as $status => $amount) {
        Expense::create([
            'request_key' => (string) Str::uuid(),
            'spent_on' => '2020-02-16',
            'category' => 'listrik',
            'amount' => $amount,
            'method' => 'cash',
            'description' => 'Pengeluaran pengujian dashboard',
            'status' => $status,
            'recorded_by' => $this->dashboardActor->id,
            'recorded_by_name' => $this->dashboardActor->name,
        ]);
    }

    $data = app(DashboardSummary::class)->forMonth('2020-02');

    expect($data['finance']['expenses'])->toBe(30000)
        ->and($data['finance']['expense_count'])->toBe(1)
        ->and($data['finance']['cash_difference'])->toBe(-30000);
});

test('filter bulan tidak menghilangkan piutang saat ini', function () {
    dashboardTestInvoice($this);

    $data = app(DashboardSummary::class)->forMonth('2020-03');

    expect($data['finance']['issued'])->toBe(0)
        ->and($data['finance']['cash_in'])->toBe(0)
        ->and($data['finance']['receivables'])->toBe(100000);
});

test('pemakaian saldo tidak dihitung lagi sebagai kas masuk', function () {
    $customer = $this->ledgerCustomer();
    dashboardTestReceipt($this, $customer, 100000, 'deposit');

    $invoice = $this->ledgerInvoice($customer);
    $invoice->forceFill([
        'issued_on' => '2020-02-10',
        'total' => 100000,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ])->save();

    app(BillingLedger::class)->applyBalance(
        $customer->id, $this->dashboardActor
    );

    $data = app(DashboardSummary::class)->forMonth('2020-02');

    expect($data['finance']['cash_in'])->toBe(100000)
        ->and($data['finance']['balance'])->toBe(0)
        ->and($invoice->fresh()->paid_amount)->toBe(100000);
});

test('tindakan terbuka dipisahkan dari kelompok status pelanggan pasti', function () {
    $customer = $this->ledgerCustomer();
    $customer->forceFill(['service_status' => 'stopped'])->save();

    CustomerNetworkAction::create([
        'request_key' => (string) Str::uuid(),
        'customer_id' => $customer->id,
        'open_customer_id' => $customer->id,
        'open_target_key' => hash('sha256', 'dashboard-'.$customer->id),
        'kind' => 'isolate',
        'status' => 'uncertain',
        'actor_id' => $this->dashboardActor->id,
        'actor_name' => $this->dashboardActor->name,
        'reason' => 'Pengujian status dashboard',
        'context' => [],
    ]);

    $data = app(DashboardSummary::class)->forMonth('2020-02');

    expect($data['customers']['pending'])->toBe(1)
        ->and($data['customers']['stopped'])->toBe(0)
        ->and($data['pending_customers']->pluck('id')->all())
        ->toContain($customer->id);
});

test('dashboard tampil dengan data dan respons tidak boleh dicache bersama', function () {
    $this->actingAs($this->dashboardActor)
        ->get(route('dashboard', ['month' => '2020-02']))
        ->assertOk()
        ->assertViewHas('summary', fn ($summary) => is_array($summary))
        ->assertSee('Tagihan Belum Bayar')
        ->assertSee('Saldo Periode')
        ->assertHeader('Cache-Control', 'no-store, private');
});

test('pengguna tanpa akses billing tidak menjalankan ringkasan keuangan', function () {
    Gate::define('access-billing', fn ($user) => false);

    $mock = Mockery::mock(DashboardSummary::class);
    $mock->shouldNotReceive('forMonth');
    $this->app->instance(DashboardSummary::class, $mock);

    $this->actingAs($this->dashboardActor)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('summary', null)
        ->assertDontSee('Tagihan Belum Bayar');
});

test('bulan dashboard tidak valid ditolak', function () {
    $this->actingAs($this->dashboardActor)
        ->getJson(route('dashboard', ['month' => '2020-99']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('month');
});

test('tamu tidak dapat membuka dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});
