<?php

namespace App\Services;

use App\Models\BillingSetting;
use App\Models\Customer;

class CustomerPricing
{
    public static function calculate(
        int $price,
        int $rateBps,
        bool $taxEnabled,
        string $mode
    ): array {
        if (
            $price < 0 || $price > 1000000000 ||
            $rateBps < 0 || $rateBps > 10000 ||
            !in_array($mode, ['include', 'exclude'], true)
        ) {
            throw new \InvalidArgumentException('Invalid pricing configuration');
        }

        $rate = $taxEnabled ? $rateBps : 0;
        $denominator = $mode === 'include' ? 10000 + $rate : 10000;

        $tax = intdiv(
            $price * $rate + intdiv($denominator, 2),
            $denominator
        );

        return [
            'price' => $price,
            'net' => $mode === 'include' ? $price - $tax : $price,
            'tax' => $tax,
            'total' => $mode === 'include' ? $price : $price + $tax,
        ];
    }

    public static function forCustomer(
        Customer $customer,
        BillingSetting $settings
    ): array {
        $price = $customer->price_mode === 'custom'
            ? (int) $customer->custom_monthly_price
            : (int) $customer->package->monthly_price;

        return self::calculate(
            $price,
            (int) $settings->tax_rate_bps,
            (bool) $customer->tax_enabled,
            $settings->tax_mode
        );
    }
}
