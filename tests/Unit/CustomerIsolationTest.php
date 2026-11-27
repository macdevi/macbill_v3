<?php

use App\Models\CustomerBillingPause;
use App\Models\CustomerNetworkAction;
use App\Models\User;
use App\Services\CustomerIsolation;
use App\Services\CustomerPppActionGateway;
use App\Services\InvoicePeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\BillingLedgerTestCase;

uses(BillingLedgerTestCase::class);

beforeEach(function () {
    CarbonImmutable::setTestNow(
        CarbonImmutable::parse(
            '2026-11-24 12:00:00', InvoicePeriod::TZ
        )
    );

    $this->isolationActor = User::factory()->createQuietly();
    $this->isolationCustomer = $this->ledgerCustomer();

    $router = $this->isolationCustomer->router;
    $router->update([
        'isolation_method' => 'ppp_profile',
        'isolation_profile' => 'isolir',
    ]);

    $this->isolationCustomer->package->routers()->attach(
        $router->id, ['normal_profile' => 'normal']
    );
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function isolationGatewayResult(): array
{
    return [
        'profile' => 'isolir',
        'reset_sessions' => 0,
        'verified_at' => '2026-11-24T12:00:00+07:00',
    ];
}

function isolationOpenPause($customer): CustomerBillingPause
{
    return CustomerBillingPause::create([
        'customer_id' => $customer->id,
        'open_customer_id' => $customer->id,
        'paused_from' => '2026-11-25',
        'isolated_at' => '2026-11-24 05:00:00',
        'isolated_by_name' => 'Petugas pengujian',
        'isolation_reason' => 'Alasan pengujian',
    ]);
}

test('isolir terverifikasi membuat jeda mulai besok', function () {
    $gateway = Mockery::mock(CustomerPppActionGateway::class);
    $gateway->shouldReceive('change')->once()->andReturnUsing(
        function ($router, $username, $from, $to, $beforeWrite) {
            expect($from)->toBe('normal')->and($to)->toBe('isolir');
            $beforeWrite(['account_id' => '*A']);

            return isolationGatewayResult();
        }
    );

    $action = (new CustomerIsolation($gateway))->run(
        $this->isolationCustomer->id,
        $this->isolationActor,
        'isolate',
        (string) Str::uuid(),
        'Belum membayar tagihan'
    );

    $pause = CustomerBillingPause::firstOrFail();

    expect($action->status)->toBe('succeeded')
        ->and($action->open_customer_id)->toBeNull()
        ->and($pause->paused_from->format('Y-m-d'))->toBe('2026-11-25')
        ->and($this->isolationCustomer->fresh()->service_status)->toBe('active');
});

test('gagal sebelum penulisan tidak membuat jeda dan melepas kunci', function () {
    $gateway = Mockery::mock(CustomerPppActionGateway::class);
    $gateway->shouldReceive('change')->once()
        ->andThrow(new RuntimeException('Read failed'));

    $action = (new CustomerIsolation($gateway))->run(
        $this->isolationCustomer->id,
        $this->isolationActor,
        'isolate',
        (string) Str::uuid(),
        'Belum membayar tagihan'
    );

    expect($action->status)->toBe('rejected')
        ->and($action->open_customer_id)->toBeNull()
        ->and(CustomerBillingPause::count())->toBe(0);
});

test('gagal setelah siap menulis menahan kunci sebagai belum pasti', function () {
    $gateway = Mockery::mock(CustomerPppActionGateway::class);
    $gateway->shouldReceive('change')->once()->andReturnUsing(
        function ($router, $username, $from, $to, $beforeWrite) {
            $beforeWrite(['account_id' => '*A']);
            throw new RuntimeException('Write timeout');
        }
    );

    $service = new CustomerIsolation($gateway);

    $action = $service->run(
        $this->isolationCustomer->id,
        $this->isolationActor,
        'isolate',
        (string) Str::uuid(),
        'Belum membayar tagihan'
    );

    expect($action->status)->toBe('uncertain')
        ->and((int) $action->open_customer_id)->toBe($this->isolationCustomer->id)
        ->and(CustomerBillingPause::count())->toBe(0);

    expect(fn () => $service->run(
        $this->isolationCustomer->id,
        $this->isolationActor,
        'isolate',
        (string) Str::uuid(),
        'Percobaan tindakan kedua'
    ))->toThrow(ValidationException::class);

    expect(CustomerNetworkAction::count())->toBe(1);
});

test('request key yang sama tidak memanggil router dua kali', function () {
    $gateway = Mockery::mock(CustomerPppActionGateway::class);
    $gateway->shouldReceive('change')->once()->andReturnUsing(
        function ($router, $username, $from, $to, $beforeWrite) {
            $beforeWrite(['account_id' => '*A']);

            return isolationGatewayResult();
        }
    );

    $service = new CustomerIsolation($gateway);
    $key = (string) Str::uuid();

    $service->run(
        $this->isolationCustomer->id,
        $this->isolationActor,
        'isolate',
        $key,
        'Belum membayar tagihan'
    );

    expect(fn () => $service->run(
        $this->isolationCustomer->id,
        $this->isolationActor,
        'isolate',
        $key,
        'Belum membayar tagihan'
    ))->toThrow(ValidationException::class);

    expect(CustomerNetworkAction::count())->toBe(1);
});

test('pemulihan hari berikutnya mengakhiri jeda pada hari pemulihan', function () {
    $pause = isolationOpenPause($this->isolationCustomer);

    $gateway = Mockery::mock(CustomerPppActionGateway::class);
    $gateway->shouldReceive('change')->once()->andReturnUsing(
        function ($router, $username, $from, $to, $beforeWrite) {
            expect($from)->toBe('isolir')->and($to)->toBe('normal');
            $beforeWrite(['account_id' => '*A']);

            return [
                'profile' => 'normal',
                'reset_sessions' => 0,
                'verified_at' => '2026-12-01T10:00:00+07:00',
            ];
        }
    );

    $action = (new CustomerIsolation($gateway))->run(
        $this->isolationCustomer->id,
        $this->isolationActor,
        'restore',
        (string) Str::uuid(),
        'Pembayaran sudah diperiksa',
        $pause->id
    );

    expect($action->status)->toBe('succeeded')
        ->and($pause->fresh()->resumed_on->format('Y-m-d'))->toBe('2026-12-01')
        ->and($pause->fresh()->open_customer_id)->toBeNull();
});

test('isolir dan buka pada hari sama menghasilkan jeda kosong', function () {
    $pause = isolationOpenPause($this->isolationCustomer);

    $gateway = Mockery::mock(CustomerPppActionGateway::class);
    $gateway->shouldReceive('change')->once()->andReturnUsing(
        function ($router, $username, $from, $to, $beforeWrite) {
            $beforeWrite(['account_id' => '*A']);

            return [
                'profile' => 'normal',
                'reset_sessions' => 0,
                'verified_at' => '2026-11-24T15:00:00+07:00',
            ];
        }
    );

    (new CustomerIsolation($gateway))->run(
        $this->isolationCustomer->id,
        $this->isolationActor,
        'restore',
        (string) Str::uuid(),
        'Pemulihan pada hari sama',
        $pause->id
    );

    $pause->refresh();

    expect($pause->paused_from->format('Y-m-d'))
        ->toBe($pause->resumed_on->format('Y-m-d'));
});

test('riwayat pemulihan yang tidak cocok ditolak sebelum router', function () {
    isolationOpenPause($this->isolationCustomer);

    $gateway = Mockery::mock(CustomerPppActionGateway::class);
    $gateway->shouldNotReceive('change');

    expect(fn () => (new CustomerIsolation($gateway))->run(
        $this->isolationCustomer->id,
        $this->isolationActor,
        'restore',
        (string) Str::uuid(),
        'Pemulihan riwayat salah',
        999999
    ))->toThrow(ValidationException::class);

    expect(CustomerNetworkAction::count())->toBe(0);
});

test('timestamp audit memakai UTC dan tanggal jeda tetap WIB', function (
    string $kind,
    string $verifiedAt,
    string $expectedUtc,
    string $expectedDate
) {
    config(['app.timezone' => 'UTC']);

    $pause = $kind === 'restore'
        ? isolationOpenPause($this->isolationCustomer)
        : null;

    $profile = $kind === 'isolate' ? 'isolir' : 'normal';

    $gateway = Mockery::mock(CustomerPppActionGateway::class);
    $gateway->shouldReceive('change')->once()->andReturnUsing(
        function ($router, $username, $from, $to, $beforeWrite) use ($verifiedAt, $profile) {
            expect($to)->toBe($profile);
            $beforeWrite(['account_id' => '*A']);

            return [
                'profile' => $profile,
                'reset_sessions' => 0,
                'verified_at' => $verifiedAt,
            ];
        }
    );

    $action = (new CustomerIsolation($gateway))->run(
        $this->isolationCustomer->id,
        $this->isolationActor,
        $kind,
        (string) Str::uuid(),
        'Pengujian zona waktu audit',
        $pause?->id
    );

    $stored = CustomerBillingPause::firstOrFail();
    $timestampField = $kind === 'isolate' ? 'isolated_at' : 'resumed_at';
    $dateField = $kind === 'isolate' ? 'paused_from' : 'resumed_on';

    expect($action->status)->toBe('succeeded')
        ->and($stored->getRawOriginal($timestampField))->toBe($expectedUtc)
        ->and($stored->getAttribute($timestampField)->getTimestamp())
        ->toBe(CarbonImmutable::parse($verifiedAt)->getTimestamp())
        ->and(
            $stored->getAttribute($timestampField)
                ->setTimezone(InvoicePeriod::TZ)
                ->format('Y-m-d\TH:i:sP')
        )->toBe($verifiedAt)
        ->and($stored->getAttribute($dateField)->format('Y-m-d'))
        ->toBe($expectedDate);
})->with([
    'isolir sesudah tengah malam WIB' => [
        'isolate',
        '2026-11-25T00:05:00+07:00',
        '2026-11-24 17:05:00',
        '2026-11-26',
    ],
    'isolir sebelum tengah malam WIB' => [
        'isolate',
        '2026-11-24T23:55:00+07:00',
        '2026-11-24 16:55:00',
        '2026-11-25',
    ],
    'pemulihan sesudah tengah malam WIB' => [
        'restore',
        '2026-11-25T00:05:00+07:00',
        '2026-11-24 17:05:00',
        '2026-11-25',
    ],
    'pemulihan sebelum tengah malam WIB' => [
        'restore',
        '2026-11-25T23:55:00+07:00',
        '2026-11-25 16:55:00',
        '2026-11-25',
    ],
]);
