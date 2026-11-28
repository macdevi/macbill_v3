<?php

use App\Services\CustomerPricing;

test('pajak include: harga sudah termasuk pajak', function () {
    expect(CustomerPricing::calculate(150000, 1100, true, 'include'))->toBe([
        'price' => 150000, 'net' => 135135, 'tax' => 14865, 'total' => 150000,
    ]);
});

test('pajak exclude: pajak ditambahkan ke harga', function () {
    expect(CustomerPricing::calculate(150000, 1100, true, 'exclude'))->toBe([
        'price' => 150000, 'net' => 150000, 'tax' => 16500, 'total' => 166500,
    ]);
});

test('pelanggan tanpa pajak tidak dikenai pajak di kedua mode', function () {
    foreach (['include', 'exclude'] as $mode) {
        expect(CustomerPricing::calculate(150000, 1100, false, $mode))->toBe([
            'price' => 150000, 'net' => 150000, 'tax' => 0, 'total' => 150000,
        ]);
    }
});

test('pembulatan pajak ke rupiah terdekat', function () {
    expect(CustomerPricing::calculate(99999, 1100, true, 'include')['tax'])->toBe(9910)
        ->and(CustomerPricing::calculate(99999, 1100, true, 'exclude')['tax'])->toBe(11000)
        ->and(CustomerPricing::calculate(5, 1100, true, 'exclude'))->toMatchArray(['tax' => 1, 'total' => 6])
        ->and(CustomerPricing::calculate(1, 1100, true, 'exclude'))->toMatchArray(['tax' => 0, 'total' => 1]);
});

test('harga nol menghasilkan total nol', function () {
    expect(CustomerPricing::calculate(0, 1100, true, 'include')['total'])->toBe(0);
});

test('total selalu konsisten untuk berbagai harga dan tarif', function () {
    foreach ([1, 5, 99, 100, 99999, 150000, 777777, 1000000000] as $price) {
        foreach ([0, 50, 1100, 1150, 10000] as $rate) {
            $in = CustomerPricing::calculate($price, $rate, true, 'include');
            $ex = CustomerPricing::calculate($price, $rate, true, 'exclude');

            expect($in['net'] + $in['tax'])->toBe($price)
                ->and($in['total'])->toBe($price)
                ->and($in['tax'])->toBeGreaterThanOrEqual(0)
                ->and($ex['total'])->toBe($price + $ex['tax'])
                ->and($ex['tax'])->toBeGreaterThanOrEqual(0);
        }
    }
});

test('konfigurasi tidak valid ditolak', function () {
    expect(fn () => CustomerPricing::calculate(100, 10001, true, 'include'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => CustomerPricing::calculate(-1, 1100, true, 'include'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => CustomerPricing::calculate(100, 1100, true, 'acak'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => CustomerPricing::calculate(1000000001, 1100, true, 'include'))->toThrow(InvalidArgumentException::class);
});
