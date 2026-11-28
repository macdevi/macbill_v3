<?php

use App\Models\BillingSetting;
use App\Models\User;
use Tests\BillingLedgerTestCase;

uses(BillingLedgerTestCase::class);

function taxSettingPayload(array $extra = []): array
{
    return array_replace([
        'default_invoice_day' => 5,
        'isolation_mode' => 'manual',
        'tax_name' => 'PPN',
        'tax_rate' => '11.5',
        'tax_mode' => 'exclude',
        'default_tax_enabled' => '1',
        'due_grace_days' => 3,
    ], $extra);
}

test('tarif 11,5 persen tersimpan sebagai 1150 bps', function () {
    $super = User::factory()->createQuietly(['role' => 'super_admin']);

    $this->actingAs($super)
        ->put(route('billing-settings.update'), taxSettingPayload())
        ->assertSessionHasNoErrors();

    $settings = BillingSetting::current();

    expect((int) $settings->tax_rate_bps)->toBe(1150)
        ->and($settings->tax_mode)->toBe('exclude');
});

test('tarif di luar batas atau lebih dari dua desimal ditolak', function () {
    $super = User::factory()->createQuietly(['role' => 'super_admin']);

    foreach (['101', '11.555', '-1', 'abc'] as $rate) {
        $this->actingAs($super)
            ->put(route('billing-settings.update'), taxSettingPayload(['tax_rate' => $rate]))
            ->assertSessionHasErrors('tax_rate');
    }
});

test('admin biasa tidak boleh membuka pengaturan', function () {
    $admin = User::factory()->createQuietly(['role' => 'admin']);

    $this->actingAs($admin)->get(route('billing-settings.index'))->assertForbidden();
});
