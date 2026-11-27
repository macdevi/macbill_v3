<?php

use App\Services\IsolationProrata;

test('periode tanpa jeda tetap harga bulanan penuh', function () {
    $result = IsolationProrata::calculate(
        150000, '2026-02-15', '2026-03-14', []
    );

    expect($result['period_days'])->toBe(28)
        ->and($result['paused_days'])->toBe(0)
        ->and($result['is_prorated'])->toBeFalse()
        ->and($result['price'])->toBe(150000);
});

test('isolir tanggal 24 mulai menjeda biaya tanggal 25', function () {
    $result = IsolationProrata::calculate(
        150000,
        '2026-11-15',
        '2026-12-14',
        [['paused_from' => '2026-11-25', 'resumed_on' => null]]
    );

    expect($result['active_days'])->toBe(10)
        ->and($result['paused_days'])->toBe(20)
        ->and($result['price'])->toBe(50000);
});

test('buka isolir tanggal 1 menghasilkan 14 hari sampai tanggal 14', function () {
    $result = IsolationProrata::calculate(
        150000,
        '2026-11-15',
        '2026-12-14',
        [[
            'paused_from' => '2026-11-01',
            'resumed_on' => '2026-12-01',
        ]]
    );

    expect($result['active_days'])->toBe(14)
        ->and($result['paused_days'])->toBe(16)
        ->and($result['price'])->toBe(70000);
});

test('jeda sepanjang periode menghasilkan biaya nol', function () {
    $result = IsolationProrata::calculate(
        150000,
        '2026-11-15',
        '2026-12-14',
        [['paused_from' => '2026-10-01', 'resumed_on' => null]]
    );

    expect($result['active_days'])->toBe(0)
        ->and($result['price'])->toBe(0);
});

test('beberapa jeda digabung tanpa menghitung hari dua kali', function () {
    $result = IsolationProrata::calculate(
        150000,
        '2026-11-01',
        '2026-11-30',
        [
            ['paused_from' => '2026-11-05', 'resumed_on' => '2026-11-10'],
            ['paused_from' => '2026-11-08', 'resumed_on' => '2026-11-12'],
            ['paused_from' => '2026-11-20', 'resumed_on' => '2026-11-23'],
        ]
    );

    expect($result['paused_days'])->toBe(10)
        ->and($result['active_days'])->toBe(20)
        ->and($result['price'])->toBe(100000);
});

test('jeda di luar periode tidak mengurangi harga', function () {
    $result = IsolationProrata::calculate(
        150000,
        '2026-11-01',
        '2026-11-30',
        [
            ['paused_from' => '2026-10-01', 'resumed_on' => '2026-11-01'],
            ['paused_from' => '2026-12-01', 'resumed_on' => null],
        ]
    );

    expect($result['paused_days'])->toBe(0)
        ->and($result['price'])->toBe(150000);
});

test('periode 31 hari tidak ditagih melebihi harga bulanan', function () {
    $result = IsolationProrata::calculate(
        150000,
        '2026-10-15',
        '2026-11-14',
        [[
            'paused_from' => '2026-10-20',
            'resumed_on' => '2026-10-21',
        ]]
    );

    expect($result['period_days'])->toBe(31)
        ->and($result['active_days'])->toBe(30)
        ->and($result['price'])->toBe(150000);
});

test('buka isolir sebelum jeda mulai tidak membuat jeda negatif', function () {
    $result = IsolationProrata::calculate(
        150000,
        '2026-11-01',
        '2026-11-30',
        [[
            'paused_from' => '2026-11-25',
            'resumed_on' => '2026-11-25',
        ]]
    );

    expect($result['paused_days'])->toBe(0)
        ->and($result['price'])->toBe(150000);
});

test('nominal prorata dibulatkan ke rupiah terdekat', function () {
    $result = IsolationProrata::calculate(
        100000,
        '2026-11-01',
        '2026-11-30',
        [['paused_from' => '2026-11-02', 'resumed_on' => null]]
    );

    expect($result['active_days'])->toBe(1)
        ->and($result['price'])->toBe(3333);
});

test('tanggal tidak valid ditolak', function () {
    IsolationProrata::calculate(
        150000, '2026-02-30', '2026-03-14', []
    );
})->throws(InvalidArgumentException::class);

test('pemulihan sebelum awal jeda ditolak', function () {
    IsolationProrata::calculate(
        150000,
        '2026-11-01',
        '2026-11-30',
        [[
            'paused_from' => '2026-11-20',
            'resumed_on' => '2026-11-19',
        ]]
    );
})->throws(InvalidArgumentException::class);
