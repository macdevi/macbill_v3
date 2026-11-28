<?php

use App\Models\Expense;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\DashboardSummary;
use App\Services\InvoicePeriod;
use Illuminate\Support\Str;
use Tests\BillingLedgerTestCase;

uses(BillingLedgerTestCase::class);

function expWilPayload(int $wilayahId, int $amount = 50000): array
{
    return [
        'request_key' => (string) Str::uuid(),
        'spent_on' => InvoicePeriod::today()->format('Y-m-d'),
        'category' => 'listrik',
        'amount' => $amount,
        'method' => 'cash',
        'description' => 'Pengeluaran uji wilayah',
        'wilayah_id' => $wilayahId,
    ];
}

function expWilRecord(User $user, int $wilayahId, int $amount): Expense
{
    return Expense::create([
        'request_key' => (string) Str::uuid(),
        'spent_on' => InvoicePeriod::today()->format('Y-m-d'),
        'category' => 'listrik',
        'amount' => $amount,
        'method' => 'cash',
        'description' => 'Catatan uji wilayah',
        'status' => 'active',
        'wilayah_id' => $wilayahId,
        'recorded_by' => $user->id,
        'recorded_by_name' => $user->name,
    ]);
}

beforeEach(function () {
    config(['wilayah.enforce' => false]);

    Wilayah::forceCreate(['id' => 9001, 'name' => 'Wilayah Uji A', 'code' => 'EXP-A']);
    Wilayah::forceCreate(['id' => 9002, 'name' => 'Wilayah Uji B', 'code' => 'EXP-B']);

    $this->expA = User::factory()->createQuietly(['id' => 9201, 'role' => 'admin']);
    $this->expB = User::factory()->createQuietly(['id' => 9202, 'role' => 'admin']);
    $this->expSuper = User::factory()->createQuietly(['id' => 9203, 'role' => 'super_admin']);
    $this->expNone = User::factory()->createQuietly(['id' => 9204, 'role' => 'admin']);

    $this->expA->wilayah()->attach(9001);
    $this->expB->wilayah()->attach(9002);

    config(['wilayah.enforce' => true]);
});

test('admin dapat mencatat pengeluaran di wilayahnya sendiri', function () {
    $this->actingAs($this->expA)
        ->post(route('expenses.store'), expWilPayload(9001))
        ->assertSessionHasNoErrors();

    expect(Expense::withoutGlobalScopes()->where('wilayah_id', 9001)->count())->toBe(1);
});

test('admin tidak dapat mencatat pengeluaran di wilayah lain', function () {
    $this->actingAs($this->expA)
        ->post(route('expenses.store'), expWilPayload(9002))
        ->assertSessionHasErrors('wilayah_id');

    expect(Expense::withoutGlobalScopes()->count())->toBe(0);
});

test('admin tanpa wilayah tidak dapat mencatat pengeluaran', function () {
    $this->actingAs($this->expNone)
        ->post(route('expenses.store'), expWilPayload(9001))
        ->assertSessionHasErrors('wilayah_id');

    expect(Expense::withoutGlobalScopes()->count())->toBe(0);
});

test('admin hanya melihat pengeluaran wilayahnya', function () {
    expWilRecord($this->expSuper, 9001, 71234);
    expWilRecord($this->expSuper, 9002, 82345);

    $this->actingAs($this->expA)->get(route('expenses.index'))
        ->assertOk()->assertSee('Rp71.234')->assertDontSee('Rp82.345');

    $this->actingAs($this->expB)->get(route('expenses.index'))
        ->assertOk()->assertSee('Rp82.345')->assertDontSee('Rp71.234');
});

test('super admin melihat pengeluaran semua wilayah', function () {
    expWilRecord($this->expSuper, 9001, 71234);
    expWilRecord($this->expSuper, 9002, 82345);

    $this->actingAs($this->expSuper)->get(route('expenses.index'))
        ->assertOk()->assertSee('Rp71.234')->assertSee('Rp82.345');
});

test('admin tidak dapat membatalkan pengeluaran wilayah lain', function () {
    $other = expWilRecord($this->expSuper, 9002, 82345);

    $this->actingAs($this->expA)
        ->post(route('expenses.void', $other), [
            'reason' => 'Uji pembatalan lintas wilayah',
            'confirmed' => '1',
        ])
        ->assertNotFound();

    expect(Expense::withoutGlobalScopes()->find($other->id)->status)->toBe('active');
});

test('total dashboard hanya menghitung wilayah admin', function () {
    expWilRecord($this->expSuper, 9001, 50000);
    expWilRecord($this->expSuper, 9002, 70000);

    $month = InvoicePeriod::today()->format('Y-m');

    $this->actingAs($this->expA);
    expect(app(DashboardSummary::class)->forMonth($month)['finance']['expenses'])->toBe(50000);

    $this->actingAs($this->expSuper);
    expect(app(DashboardSummary::class)->forMonth($month)['finance']['expenses'])->toBe(120000);
});
