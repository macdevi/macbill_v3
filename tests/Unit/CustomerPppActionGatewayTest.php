<?php

use App\Models\NetworkRouter;
use App\Services\CustomerPppActionGateway;

class FakeCustomerPppActionGateway extends CustomerPppActionGateway
{
    public array $calls = [];

    public array $responses = [];

    public bool $closed = false;

    protected function open(NetworkRouter $router): void {}

    protected function close(): void
    {
        $this->closed = true;
    }

    protected function query(
        string $command,
        array $equals = [],
        array $where = []
    ): array {
        $this->calls[] = [$command, $equals, $where];

        if ($this->responses === []) {
            throw new RuntimeException('Unexpected query');
        }

        $next = array_shift($this->responses);

        if ($next instanceof Throwable) {
            throw $next;
        }

        return $next;
    }
}

function pppActionAccount(
    string $profile = 'normal',
    array $extra = []
): array {
    return [array_replace([
        '.id' => '*A',
        'name' => 'pelanggan-uji',
        'profile' => $profile,
        'service' => 'pppoe',
        'disabled' => 'false',
    ], $extra)];
}

function pppActionProfiles(): array
{
    return [
        [['.id' => '*1', 'name' => 'normal']],
        [['.id' => '*2', 'name' => 'isolir']],
    ];
}

function pppActionWrites(FakeCustomerPppActionGateway $gateway): array
{
    return array_values(array_filter(
        $gateway->calls,
        fn ($call) => in_array(
            $call[0],
            ['/ppp/secret/set', '/ppp/active/remove'],
            true
        )
    ));
}

test('profil akun diubah tanpa menulis profil bersama', function () {
    $gateway = new FakeCustomerPppActionGateway;
    $gateway->responses = [
        ...pppActionProfiles(),
        pppActionAccount(),
        [],
        pppActionAccount(),
        [],
        pppActionAccount('isolir'),
        [],
        pppActionAccount('isolir'),
    ];

    $journaled = false;

    $result = $gateway->change(
        new NetworkRouter,
        'pelanggan-uji',
        'normal',
        'isolir',
        function ($before) use (&$journaled) {
            expect($before['account_id'])->toBe('*A');
            $journaled = true;
        }
    );

    expect($journaled)->toBeTrue()
        ->and($result['profile'])->toBe('isolir')
        ->and($gateway->closed)->toBeTrue()
        ->and(pppActionWrites($gateway))->toBe([
            ['/ppp/secret/set', ['.id' => '*A', 'profile' => 'isolir'], []],
        ])
        ->and($gateway->responses)->toBe([]);
});

test('profil tidak dikenal ditolak sebelum pencatatan dan perubahan', function () {
    $gateway = new FakeCustomerPppActionGateway;
    $gateway->responses = [
        ...pppActionProfiles(),
        pppActionAccount('profil-lain'),
    ];

    $journaled = false;

    expect(fn () => $gateway->change(
        new NetworkRouter,
        'pelanggan-uji',
        'normal',
        'isolir',
        function () use (&$journaled) {
            $journaled = true;
        }
    ))->toThrow(RuntimeException::class);

    expect($journaled)->toBeFalse()
        ->and(pppActionWrites($gateway))->toBe([]);
});

test('akun disabled ditolak tanpa perubahan', function () {
    $gateway = new FakeCustomerPppActionGateway;
    $gateway->responses = [
        ...pppActionProfiles(),
        pppActionAccount('normal', ['disabled' => 'true']),
    ];

    expect(fn () => $gateway->change(
        new NetworkRouter,
        'pelanggan-uji',
        'normal',
        'isolir',
        fn () => null
    ))->toThrow(RuntimeException::class);

    expect(pppActionWrites($gateway))->toBe([]);
});

test('kegagalan pencatatan operasi mencegah penulisan router', function () {
    $gateway = new FakeCustomerPppActionGateway;
    $gateway->responses = [
        ...pppActionProfiles(),
        pppActionAccount(),
        [],
    ];

    expect(fn () => $gateway->change(
        new NetworkRouter,
        'pelanggan-uji',
        'normal',
        'isolir',
        fn () => throw new RuntimeException('Journal unavailable')
    ))->toThrow(RuntimeException::class);

    expect(pppActionWrites($gateway))->toBe([])
        ->and($gateway->closed)->toBeTrue();
});

test('perubahan akun setelah pencatatan menghentikan penulisan', function () {
    $gateway = new FakeCustomerPppActionGateway;
    $gateway->responses = [
        ...pppActionProfiles(),
        pppActionAccount(),
        [],
        pppActionAccount('profil-lain'),
    ];

    expect(fn () => $gateway->change(
        new NetworkRouter,
        'pelanggan-uji',
        'normal',
        'isolir',
        fn () => null
    ))->toThrow(RuntimeException::class);

    expect(pppActionWrites($gateway))->toBe([]);
});

test('timeout penulisan tidak dicoba ulang otomatis', function () {
    $gateway = new FakeCustomerPppActionGateway;
    $gateway->responses = [
        ...pppActionProfiles(),
        pppActionAccount(),
        [],
        pppActionAccount(),
        new RuntimeException('Timeout'),
    ];

    expect(fn () => $gateway->change(
        new NetworkRouter,
        'pelanggan-uji',
        'normal',
        'isolir',
        fn () => null
    ))->toThrow(RuntimeException::class);

    expect(count(pppActionWrites($gateway)))->toBe(1)
        ->and($gateway->closed)->toBeTrue();
});

test('hanya sesi akun yang sesuai diputus', function () {
    $session = [[
        '.id' => '*S',
        'name' => 'pelanggan-uji',
        'service' => 'pppoe',
    ]];

    $gateway = new FakeCustomerPppActionGateway;
    $gateway->responses = [
        ...pppActionProfiles(),
        pppActionAccount(),
        $session,
        pppActionAccount(),
        [],
        pppActionAccount('isolir'),
        $session,
        [],
        [],
        pppActionAccount('isolir'),
    ];

    $result = $gateway->change(
        new NetworkRouter,
        'pelanggan-uji',
        'normal',
        'isolir',
        fn () => null
    );

    expect($result['reset_sessions'])->toBe(1)
        ->and(pppActionWrites($gateway))->toBe([
            ['/ppp/secret/set', ['.id' => '*A', 'profile' => 'isolir'], []],
            ['/ppp/active/remove', ['.id' => '*S'], []],
        ])
        ->and($gateway->responses)->toBe([]);
});

test('identitas sesi berubah tidak boleh memutus akun lain', function () {
    $gateway = new FakeCustomerPppActionGateway;
    $gateway->responses = [
        ...pppActionProfiles(),
        pppActionAccount(),
        [['.id' => '*S', 'name' => 'pelanggan-uji', 'service' => 'pppoe']],
        pppActionAccount(),
        [],
        pppActionAccount('isolir'),
        [['.id' => '*S', 'name' => 'pelanggan-lain', 'service' => 'pppoe']],
    ];

    expect(fn () => $gateway->change(
        new NetworkRouter,
        'pelanggan-uji',
        'normal',
        'isolir',
        fn () => null
    ))->toThrow(RuntimeException::class);

    expect(count(pppActionWrites($gateway)))->toBe(1);
});

test('hasil profil yang tidak sesuai tidak dianggap berhasil', function () {
    $gateway = new FakeCustomerPppActionGateway;
    $gateway->responses = [
        ...pppActionProfiles(),
        pppActionAccount(),
        [],
        pppActionAccount(),
        [],
        pppActionAccount('normal'),
    ];

    expect(fn () => $gateway->change(
        new NetworkRouter,
        'pelanggan-uji',
        'normal',
        'isolir',
        fn () => null
    ))->toThrow(RuntimeException::class);
});

test('profil asal dan tujuan sama ditolak sebelum koneksi', function () {
    $gateway = new FakeCustomerPppActionGateway;

    expect(fn () => $gateway->change(
        new NetworkRouter,
        'pelanggan-uji',
        'normal',
        'normal',
        fn () => null
    ))->toThrow(InvalidArgumentException::class);

    expect($gateway->calls)->toBe([]);
});

test('pemeriksaan isolir impor menerima akun offline tanpa penulisan router', function () {
    $gateway = new FakeCustomerPppActionGateway;
    $gateway->responses = [
        ...pppActionProfiles(),
        pppActionAccount('isolir'),
        [],
        pppActionAccount('isolir'),
    ];

    $result = $gateway->inspectIsolation(
        new NetworkRouter,
        'pelanggan-uji',
        'normal',
        'isolir'
    );

    expect($result['profile'])->toBe('isolir')
        ->and($result['session_count'])->toBe(0)
        ->and($result['verification_scope'])->toBe('account_profile_only')
        ->and(pppActionWrites($gateway))->toBe([])
        ->and($gateway->responses)->toBe([])
        ->and($gateway->closed)->toBeTrue();
});

test('pemeriksaan isolir impor menolak profil normal tanpa penulisan router', function () {
    $gateway = new FakeCustomerPppActionGateway;
    $gateway->responses = [
        ...pppActionProfiles(),
        pppActionAccount('normal'),
    ];

    expect(fn () => $gateway->inspectIsolation(
        new NetworkRouter,
        'pelanggan-uji',
        'normal',
        'isolir'
    ))->toThrow(RuntimeException::class);

    expect(pppActionWrites($gateway))->toBe([])
        ->and($gateway->closed)->toBeTrue();
});
