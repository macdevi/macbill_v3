<?php

use App\Models\Customer;
use App\Models\CustomerBillingPause;
use App\Models\CustomerNetworkAction;
use App\Models\User;
use App\Services\CustomerPppActionGateway;
use App\Services\InvoicePeriod;
use App\Services\RouterSecretReader;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

uses(\Tests\BillingLedgerTestCase::class);

beforeEach(function () {
    config(['manual-isolation.enabled' => true]);

    CarbonImmutable::setTestNow(
        CarbonImmutable::parse('2026-11-24 18:00:00', InvoicePeriod::TZ)
    );

    $this->importActor = User::factory()->createQuietly();
    $this->importCustomer = $this->ledgerCustomer();
    $this->importCustomer->forceFill([
        'service_type' => 'pppoe',
        'service_status' => 'active',
        'pppoe_username' => 'legacy-offline',
    ])->saveQuietly();

    $this->importRouter = $this->importCustomer->router;
    $this->importRouter->update([
        'isolation_method' => 'ppp_profile',
        'isolation_profile' => 'isolir',
    ]);

    $this->importPackage = $this->importCustomer->package;
    $this->importPackage->forceFill([
        'service_type' => 'pppoe',
        'is_active' => true,
    ])->saveQuietly();

    $this->importPackage->routers()->syncWithoutDetaching([
        $this->importRouter->id => ['normal_profile' => 'normal'],
    ]);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function isolationImportBatch($test, array $rows): string
{
    $reader = Mockery::mock(RouterSecretReader::class);
    $reader->shouldReceive('read')->once()->andReturn($rows);
    app()->instance(RouterSecretReader::class, $reader);

    $response = $test->actingAs($test->importActor)->post(
        route('customer-pppoe.preview'),
        ['network_router_id' => $test->importRouter->id]
    );

    $response->assertSessionHasNoErrors()->assertRedirect();

    return basename(parse_url(
        $response->headers->get('Location'), PHP_URL_PATH
    ));
}

function isolationImportRow(string $username, string $profile = 'isolir'): array
{
    return [
        'username' => $username,
        'password' => null,
        'profile' => $profile,
        'service' => 'pppoe',
        'disabled' => false,
    ];
}

function isolationImportPayload($test): array
{
    $data = [
        'started_at' => '2026-11-24',
        'invoice_day' => 15,
        'items' => [
            0 => [
                'selected' => '1',
                'name' => 'Nama dari formulir',
                'package_id' => $test->importPackage->id,
            ],
        ],
    ];

    return $data;
}

function isolationImportGateway($test, bool $fail = false): void
{
    $gateway = Mockery::mock(CustomerPppActionGateway::class);
    $gateway->shouldNotReceive('change');

    $expectation = $gateway->shouldReceive('inspectIsolation')->once();

    if ($fail) {
        $expectation->andThrow(new RuntimeException('Verification failed'));
    } else {
        $expectation->andReturn([
            'account_id' => '*A',
            'profile' => 'isolir',
            'session_count' => 0,
            'verification_scope' => 'account_profile_only',
            'verified_at' => '2026-11-24T18:00:00+07:00',
        ]);
    }

    app()->instance(CustomerPppActionGateway::class, $gateway);
}

test('impor akun isolir baru membuat pelanggan jurnal dan jeda', function () {
    $batch = isolationImportBatch($this, [
        isolationImportRow('pelanggan-baru'),
    ]);

    isolationImportGateway($this);

    $this->actingAs($this->importActor)
        ->post(route('customer-pppoe.commit', $batch), isolationImportPayload($this))
        ->assertSessionHasNoErrors()->assertRedirect();

    $customer = Customer::where('pppoe_username', 'pelanggan-baru')->firstOrFail();
    $pause = CustomerBillingPause::where('customer_id', $customer->id)->firstOrFail();

    expect($customer->service_status)->toBe('active')
        ->and($pause->paused_from->format('Y-m-d'))->toBe('2026-11-25')
        ->and($pause->isolated_at->copy()->setTimezone('UTC')->format('H:i:s'))
            ->toBe('11:00:00')
        ->and(CustomerNetworkAction::where('kind', 'adopt_isolation')
            ->where('status', 'succeeded')->count())->toBe(1);
});

test('sinkronisasi pelanggan lama tidak menimpa data dan tidak menggandakan jeda', function () {
    $before = $this->importCustomer->fresh()->getRawOriginal();
    $invoice = $this->ledgerInvoice($this->importCustomer);
    $invoiceBefore = $invoice->fresh()->getRawOriginal();
    $count = Customer::count();

    $batch = isolationImportBatch($this, [
        isolationImportRow('legacy-offline'),
    ]);

    isolationImportGateway($this);

    for ($i = 0; $i < 2; $i++) {
        $this->actingAs($this->importActor)
            ->post(route('customer-pppoe.commit', $batch), isolationImportPayload($this))
            ->assertSessionHasNoErrors()->assertRedirect();
    }

    expect(Customer::count())->toBe($count)
        ->and($this->importCustomer->fresh()->getRawOriginal())->toBe($before)
        ->and($invoice->fresh()->getRawOriginal())->toBe($invoiceBefore)
        ->and(CustomerBillingPause::count())->toBe(1)
        ->and(CustomerNetworkAction::count())->toBe(1);
});

test('impor isolir tanpa checkbox tambahan membuat jeda mulai besok WIB', function () {
    $count = Customer::count();

    $batch = isolationImportBatch($this, [
        isolationImportRow('tanpa-checkbox'),
    ]);

    isolationImportGateway($this);

    $this->actingAs($this->importActor)
        ->post(
            route('customer-pppoe.commit', $batch),
            isolationImportPayload($this)
        )
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Customer::count())->toBe($count + 1)
        ->and(CustomerBillingPause::firstOrFail()
            ->paused_from->format('Y-m-d'))->toBe('2026-11-25')
        ->and(CustomerNetworkAction::count())->toBe(1);
});

test('profil berubah saat verifikasi membatalkan seluruh impor', function () {
    $count = Customer::count();

    $batch = isolationImportBatch($this, [
        isolationImportRow('profil-berubah'),
    ]);

    isolationImportGateway($this, true);

    $this->actingAs($this->importActor)
        ->post(route('customer-pppoe.commit', $batch), isolationImportPayload($this))
        ->assertSessionHasErrors('import');

    expect(Customer::count())->toBe($count)
        ->and(CustomerBillingPause::count())->toBe(0)
        ->and(CustomerNetworkAction::count())->toBe(0);
});

test('tindakan terbuka menolak sinkronisasi tanpa membaca ulang router', function () {
    CustomerNetworkAction::create([
        'request_key' => (string) Str::uuid(),
        'customer_id' => $this->importCustomer->id,
        'open_customer_id' => $this->importCustomer->id,
        'open_target_key' => hash('sha256', 'pending-import-test'),
        'kind' => 'isolate',
        'status' => 'uncertain',
        'actor_id' => $this->importActor->id,
        'actor_name' => $this->importActor->name,
        'reason' => 'Tindakan pengujian belum pasti',
        'context' => [],
    ]);

    $batch = isolationImportBatch($this, [
        isolationImportRow('legacy-offline'),
    ]);

    $gateway = Mockery::mock(CustomerPppActionGateway::class);
    $gateway->shouldNotReceive('inspectIsolation');
    $gateway->shouldNotReceive('change');
    $this->app->instance(CustomerPppActionGateway::class, $gateway);

    $this->actingAs($this->importActor)
        ->post(route('customer-pppoe.commit', $batch), isolationImportPayload($this))
        ->assertSessionHasErrors('import');

    expect(CustomerBillingPause::count())->toBe(0)
        ->and(CustomerNetworkAction::count())->toBe(1);
});

test('impor profil normal tidak membutuhkan konfirmasi atau jeda isolir', function () {
    $batch = isolationImportBatch($this, [
        isolationImportRow('pelanggan-normal', 'normal'),
    ]);

    $gateway = Mockery::mock(CustomerPppActionGateway::class);
    $gateway->shouldNotReceive('inspectIsolation');
    $gateway->shouldNotReceive('change');
    $this->app->instance(CustomerPppActionGateway::class, $gateway);

    $this->actingAs($this->importActor)
        ->post(
            route('customer-pppoe.commit', $batch),
            isolationImportPayload($this)
        )
        ->assertSessionHasNoErrors()->assertRedirect();

    expect(Customer::where('pppoe_username', 'pelanggan-normal')
        ->firstOrFail()->service_status)
        ->toBe('active')
        ->and(CustomerBillingPause::count())->toBe(0)
        ->and(CustomerNetworkAction::count())->toBe(0);
});

test('preview menyembunyikan pelanggan yang sudah terdaftar pada router', function () {
    $batch = isolationImportBatch($this, [
        isolationImportRow('legacy-offline'),
    ]);

    $this->actingAs($this->importActor)
        ->get(route('customer-pppoe.show', $batch))
        ->assertOk()
        ->assertViewHas('rows', function ($rows) {
            return $rows->every(function ($row) {
                return !\App\Models\Customer::where(
                    'network_router_id', $this->importRouter->id
                )->where('pppoe_username', $row['username'])->exists();
            });
        })
        ->assertDontSee('name="confirm_isolation"', false)
        ->assertDontSee('name="service_status"', false);
});

test('status kiriman klien tidak mengganti status otomatis pelanggan baru', function () {
    $batch = isolationImportBatch($this, [
        isolationImportRow('status-otomatis', 'normal'),
    ]);

    $gateway = Mockery::mock(CustomerPppActionGateway::class);
    $gateway->shouldNotReceive('inspectIsolation');
    $gateway->shouldNotReceive('change');
    $this->app->instance(CustomerPppActionGateway::class, $gateway);

    $data = isolationImportPayload($this);
    $data['service_status'] = 'stopped';

    $this->actingAs($this->importActor)
        ->post(route('customer-pppoe.commit', $batch), $data)
        ->assertSessionHasNoErrors();

    expect(Customer::where('pppoe_username', 'status-otomatis')
        ->firstOrFail()->service_status)->toBe('active');
});

test('sinkronisasi isolir tidak mengaktifkan kembali pelanggan lama yang berhenti', function () {
    $this->importCustomer->forceFill([
        'service_status' => 'stopped',
    ])->saveQuietly();

    $batch = isolationImportBatch($this, [
        isolationImportRow('legacy-offline'),
    ]);

    $gateway = Mockery::mock(CustomerPppActionGateway::class);
    $gateway->shouldNotReceive('inspectIsolation');
    $gateway->shouldNotReceive('change');
    $this->app->instance(CustomerPppActionGateway::class, $gateway);

    $this->actingAs($this->importActor)
        ->post(
            route('customer-pppoe.commit', $batch),
            isolationImportPayload($this)
        )
        ->assertSessionHasErrors('import');

    expect($this->importCustomer->fresh()->service_status)->toBe('stopped')
        ->and(CustomerBillingPause::count())->toBe(0);
});

test('payload JSON 500 pelanggan melewati validasi jumlah', function () {
    $items = array_fill(0, 500, [
        'selected' => '1',
        'name' => 'Pelanggan uji',
        'package_id' => 1,
    ]);

    $this->actingAs($this->importActor)
        ->post(route('customer-pppoe.commit', (string) \Illuminate\Support\Str::uuid()), [
            'invoice_day' => 15,
            'selection_payload' => json_encode($items, JSON_THROW_ON_ERROR),
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('import')
        ->assertSessionDoesntHaveErrors(['items', 'selection_payload']);
});

test('payload JSON lebih dari 500 pelanggan ditolak', function () {
    $items = array_fill(0, 501, [
        'selected' => '1',
        'name' => 'Pelanggan uji',
        'package_id' => 1,
    ]);

    $this->actingAs($this->importActor)
        ->post(route('customer-pppoe.commit', (string) \Illuminate\Support\Str::uuid()), [
            'invoice_day' => 15,
            'selection_payload' => json_encode($items, JSON_THROW_ON_ERROR),
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('items');
});

test('payload JSON rusak ditolak sebelum proses impor', function () {
    $this->actingAs($this->importActor)
        ->postJson(route('customer-pppoe.commit', (string) \Illuminate\Support\Str::uuid()), [
            'invoice_day' => 15,
            'selection_payload' => '{"rusak":',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('selection_payload');
});
