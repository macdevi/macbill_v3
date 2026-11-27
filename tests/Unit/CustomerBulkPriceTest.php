<?php

use App\Models\Customer;
use App\Models\CustomerNetworkAction;
use App\Models\User;
use App\Services\CustomerPppActionGateway;
use Illuminate\Support\Str;

uses(\Tests\BillingLedgerTestCase::class);

beforeEach(function () {
    $this->bulkActor = User::factory()->createQuietly();
    $this->bulkCustomer = $this->ledgerCustomer();
    $this->bulkCustomer->forceFill([
        'price_mode' => 'package',
        'custom_monthly_price' => null,
    ])->saveQuietly();

    $gateway = Mockery::mock(CustomerPppActionGateway::class);
    $gateway->shouldNotReceive('change');
    $gateway->shouldNotReceive('inspectIsolation');
    $this->app->instance(CustomerPppActionGateway::class, $gateway);
});

function bulkPriceItem($test, Customer $customer, ?int $amount): array
{
    $rows = $test->actingAs($test->bulkActor)
        ->getJson(route('customers.bulk-prices.index'))
        ->assertOk()->json('rows');

    $row = collect($rows)->firstWhere('id', $customer->id);

    if (!$row) {
        throw new RuntimeException('Pelanggan fixture tidak ditemukan.');
    }

    return [
        'id' => $customer->id,
        'version' => $row['version'],
        'custom_monthly_price' => $amount,
    ];
}

test('popup nominal tersedia di halaman pelanggan', function () {
    $this->actingAs($this->bulkActor)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertSee('id="cu-bulk-prices-open"', false)
        ->assertSee('Harga khusus / bulan');
});

test('edit nominal menyimpan harga khusus tanpa mengubah invoice atau pengaturan lain', function () {
    $invoice = $this->ledgerInvoice($this->bulkCustomer);
    $invoiceBefore = $invoice->fresh()->getRawOriginal();
    $before = $this->bulkCustomer->fresh()->getRawOriginal();
    $item = bulkPriceItem($this, $this->bulkCustomer, 160000);

    $this->actingAs($this->bulkActor)
        ->patchJson(route('customers.bulk-prices.update'), ['items' => [$item]])
        ->assertOk()->assertJsonPath('changed', 1);

    $after = $this->bulkCustomer->fresh()->getRawOriginal();

    foreach (['price_mode', 'custom_monthly_price', 'updated_at'] as $key) {
        unset($before[$key], $after[$key]);
    }

    expect($after)->toBe($before)
        ->and($this->bulkCustomer->fresh()->price_mode)->toBe('custom')
        ->and($this->bulkCustomer->fresh()->custom_monthly_price)->toBe(160000)
        ->and($invoice->fresh()->getRawOriginal())->toBe($invoiceBefore);
});

test('mengosongkan harga khusus kembali mengikuti paket', function () {
    $this->bulkCustomer->forceFill([
        'price_mode' => 'custom',
        'custom_monthly_price' => 160000,
    ])->save();

    $item = bulkPriceItem($this, $this->bulkCustomer, null);

    $this->actingAs($this->bulkActor)
        ->patchJson(route('customers.bulk-prices.update'), ['items' => [$item]])
        ->assertOk();

    expect($this->bulkCustomer->fresh()->price_mode)->toBe('package')
        ->and($this->bulkCustomer->fresh()->custom_monthly_price)->toBeNull();
});

test('angka nol disimpan sebagai harga khusus bukan mengikuti paket', function () {
    $item = bulkPriceItem($this, $this->bulkCustomer, 0);

    $this->actingAs($this->bulkActor)
        ->patchJson(route('customers.bulk-prices.update'), ['items' => [$item]])
        ->assertOk();

    expect($this->bulkCustomer->fresh()->price_mode)->toBe('custom')
        ->and($this->bulkCustomer->fresh()->custom_monthly_price)->toBe(0);
});

test('nominal negatif ditolak', function () {
    $item = bulkPriceItem($this, $this->bulkCustomer, -1);

    $this->actingAs($this->bulkActor)
        ->patchJson(route('customers.bulk-prices.update'), ['items' => [$item]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items.0.custom_monthly_price');

    expect($this->bulkCustomer->fresh()->price_mode)->toBe('package');
});

test('perubahan data setelah popup dibuka menolak penyimpanan lama', function () {
    $item = bulkPriceItem($this, $this->bulkCustomer, 160000);

    $this->bulkCustomer->forceFill([
        'price_mode' => 'custom',
        'custom_monthly_price' => 170000,
    ])->save();

    $this->actingAs($this->bulkActor)
        ->patchJson(route('customers.bulk-prices.update'), ['items' => [$item]])
        ->assertUnprocessable()->assertJsonValidationErrors('prices');

    expect($this->bulkCustomer->fresh()->custom_monthly_price)->toBe(170000);
});

test('satu pelanggan terkunci membatalkan seluruh perubahan massal', function () {
    $other = $this->ledgerCustomer();
    $before = $this->bulkCustomer->fresh()->getRawOriginal();
    $first = bulkPriceItem($this, $this->bulkCustomer, 160000);
    $second = bulkPriceItem($this, $other, 180000);

    CustomerNetworkAction::create([
        'request_key' => (string) Str::uuid(),
        'customer_id' => $other->id,
        'open_customer_id' => $other->id,
        'open_target_key' => hash('sha256', 'bulk-test-'.$other->id),
        'kind' => 'isolate',
        'status' => 'uncertain',
        'actor_id' => $this->bulkActor->id,
        'actor_name' => $this->bulkActor->name,
        'reason' => 'Pengujian kunci edit massal',
        'context' => [],
    ]);

    $this->actingAs($this->bulkActor)
        ->patchJson(route('customers.bulk-prices.update'), [
            'items' => [$first, $second],
        ])
        ->assertUnprocessable()->assertJsonValidationErrors('prices');

    expect($this->bulkCustomer->fresh()->getRawOriginal())->toBe($before);
});

test('popup menampilkan seluruh pelanggan A sampai Z tanpa membedakan huruf besar kecil', function () {
    $this->bulkCustomer->forceFill([
        'name' => 'Yati',
    ])->save();

    $other = $this->ledgerCustomer();
    $other->forceFill([
        'name' => 'hallo',
    ])->save();

    $response = $this->actingAs($this->bulkActor)
        ->getJson(route('customers.bulk-prices.index', [
            'q' => $this->bulkCustomer->code,
            'page' => 99,
        ]))
        ->assertOk()
        ->assertJsonCount(Customer::count(), 'rows');

    $ids = collect($response->json('rows'))->pluck('id')->all();

    expect(array_search($other->id, $ids, true))
        ->toBeLessThan(array_search($this->bulkCustomer->id, $ids, true));
});

test('tamu tidak dapat membaca atau menyimpan nominal massal', function () {
    $this->getJson(route('customers.bulk-prices.index'))->assertUnauthorized();

    $this->patchJson(route('customers.bulk-prices.update'), [
        'items' => [],
    ])->assertUnauthorized();
});
