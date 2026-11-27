<?php

use App\Models\Expense;
use App\Models\User;
use App\Models\Wilayah;
use App\Services\InvoicePeriod;
use Illuminate\Support\Str;
use Tests\BillingLedgerTestCase;

uses(BillingLedgerTestCase::class);

function expenseTestPayload(array $extra = []): array
{
    return array_replace([
        'request_key' => (string) Str::uuid(),
        'spent_on' => InvoicePeriod::today()->format('Y-m-d'),
        'category' => 'listrik',
        'amount' => 150000,
        'method' => 'cash',
        'description' => 'Token listrik kantor',
        'wilayah_id' => Wilayah::firstOrCreate(['code' => 'TST'], ['name' => 'Wilayah Uji'])->id,
    ], $extra);
}

function expenseTestRecord(User $user, int $amount): Expense
{
    return Expense::create([
        'request_key' => (string) Str::uuid(),
        'spent_on' => InvoicePeriod::today()->format('Y-m-d'),
        'category' => 'listrik',
        'amount' => $amount,
        'method' => 'cash',
        'description' => 'Catatan pengujian',
        'status' => 'active',
        'recorded_by' => $user->id,
        'recorded_by_name' => $user->name,
    ]);
}

beforeEach(function () {
    $this->expenseUser = User::factory()->createQuietly();
});

test('admin dapat mencatat pengeluaran', function () {
    $this->actingAs($this->expenseUser)
        ->post(route('expenses.store'), expenseTestPayload())
        ->assertRedirect();

    $expense = Expense::firstOrFail();

    expect($expense->amount)->toBe(150000)
        ->and($expense->status)->toBe('active')
        ->and($expense->recorded_by_name)->toBe($this->expenseUser->name)
        ->and($expense->category)->toBe('listrik');
});

test('request key yang sama tidak membuat catatan kedua', function () {
    $payload = expenseTestPayload();

    $this->actingAs($this->expenseUser)->post(route('expenses.store'), $payload);
    $this->actingAs($this->expenseUser)
        ->post(route('expenses.store'), $payload)
        ->assertSessionHasErrors('expense');

    expect(Expense::count())->toBe(1);
});

test('tanggal masa depan dan kategori tidak valid ditolak', function () {
    $this->actingAs($this->expenseUser)
        ->post(route('expenses.store'), expenseTestPayload([
            'spent_on' => InvoicePeriod::today()->addDay()->format('Y-m-d'),
        ]))
        ->assertSessionHasErrors('spent_on');

    $this->actingAs($this->expenseUser)
        ->post(route('expenses.store'), expenseTestPayload([
            'category' => 'acak',
        ]))
        ->assertSessionHasErrors('category');

    expect(Expense::count())->toBe(0);
});

test('pembatalan wajib dikonfirmasi', function () {
    $expense = expenseTestRecord($this->expenseUser, 50000);

    $this->actingAs($this->expenseUser)
        ->post(route('expenses.void', $expense), [
            'reason' => 'Salah catat nominal',
        ])
        ->assertSessionHasErrors('confirmed');

    expect($expense->fresh()->status)->toBe('active');
});

test('pembatalan menyimpan riwayat dan keluar dari total aktif', function () {
    expenseTestRecord($this->expenseUser, 100000);
    $voided = expenseTestRecord($this->expenseUser, 50000);

    $this->actingAs($this->expenseUser)
        ->post(route('expenses.void', $voided), [
            'reason' => 'Salah catat nominal',
            'confirmed' => '1',
        ])
        ->assertSessionHasNoErrors();

    $voided->refresh();

    expect($voided->status)->toBe('void')
        ->and($voided->voided_by_name)->toBe($this->expenseUser->name)
        ->and($voided->void_reason)->toBe('Salah catat nominal')
        ->and(Expense::count())->toBe(2);

    $this->actingAs($this->expenseUser)
        ->get(route('expenses.index'))
        ->assertOk()
        ->assertSee('Rp100.000')
        ->assertDontSee('Rp150.000');
});

test('pembatalan berulang ditolak', function () {
    $expense = expenseTestRecord($this->expenseUser, 50000);
    $body = ['reason' => 'Pembatalan pertama', 'confirmed' => '1'];

    $this->actingAs($this->expenseUser)
        ->post(route('expenses.void', $expense), $body);

    $this->actingAs($this->expenseUser)
        ->post(route('expenses.void', $expense), [
            'reason' => 'Pembatalan kedua', 'confirmed' => '1',
        ])
        ->assertSessionHasErrors('expense');

    expect($expense->fresh()->void_reason)->toBe('Pembatalan pertama');
});

test('tamu diarahkan ke halaman masuk', function () {
    $this->get(route('expenses.index'))->assertRedirect(route('login'));
});
