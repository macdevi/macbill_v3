<?php

use App\Models\Customer;
use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Support\Facades\DB;
use Tests\BillingLedgerTestCase;

uses(BillingLedgerTestCase::class);

function wilVisibleIds(): array
{
    return Customer::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
}

beforeEach(function () {
    config(['wilayah.enforce' => false]);

    Wilayah::forceCreate(['id' => 9001, 'name' => 'Wilayah Uji A', 'code' => 'ISO-A']);
    Wilayah::forceCreate(['id' => 9002, 'name' => 'Wilayah Uji B', 'code' => 'ISO-B']);

    $this->wilUserA = User::factory()->createQuietly(['id' => 9101, 'role' => 'admin']);
    $this->wilUserB = User::factory()->createQuietly(['id' => 9102, 'role' => 'admin']);
    $this->wilSuper = User::factory()->createQuietly(['id' => 9103, 'role' => 'super_admin']);
    $this->wilNone = User::factory()->createQuietly(['id' => 9104, 'role' => 'admin']);

    $this->wilUserA->wilayah()->attach(9001);
    $this->wilUserB->wilayah()->attach(9002);

    $this->wilCustA = $this->ledgerCustomer();
    $this->wilCustB = $this->ledgerCustomer();
    DB::table('customers')->where('id', $this->wilCustA->id)->update(['wilayah_id' => 9001]);
    DB::table('customers')->where('id', $this->wilCustB->id)->update(['wilayah_id' => 9002]);

    config(['wilayah.enforce' => true]);
});

test('admin wilayah A hanya melihat pelanggan wilayahnya', function () {
    $this->actingAs($this->wilUserA);

    expect(wilVisibleIds())->toBe([$this->wilCustA->id]);
});

test('admin wilayah B hanya melihat pelanggan wilayahnya', function () {
    $this->actingAs($this->wilUserB);

    expect(wilVisibleIds())->toBe([$this->wilCustB->id]);
});

test('super admin melihat pelanggan semua wilayah', function () {
    $this->actingAs($this->wilSuper);

    expect(wilVisibleIds())
        ->toContain($this->wilCustA->id)
        ->toContain($this->wilCustB->id);
});

test('admin tanpa wilayah tidak melihat pelanggan', function () {
    $this->actingAs($this->wilNone);

    expect(wilVisibleIds())->toBe([]);
});

test('riwayat pelanggan wilayah lain ditolak dengan 404', function () {
    $this->actingAs($this->wilUserA)
        ->get(route('customers.history', $this->wilCustA->id))
        ->assertOk();

    $this->actingAs($this->wilUserA)
        ->get(route('customers.history', $this->wilCustB->id))
        ->assertNotFound();
});

test('isolasi pelanggan wilayah lain ditolak dengan 404', function () {
    config(['manual-isolation.enabled' => true]);

    $this->actingAs($this->wilUserA)
        ->post(route('customers.isolation.store', $this->wilCustB->id))
        ->assertNotFound();
});
