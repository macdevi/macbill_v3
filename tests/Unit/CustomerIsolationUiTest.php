<?php

use App\Models\BillingSetting;
use App\Models\CustomerBillingPause;
use App\Models\CustomerNetworkAction;
use App\Models\User;
use App\Services\CustomerIsolation;
use App\Services\CustomerPppActionGateway;
use App\Services\InvoiceGenerator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\BillingLedgerTestCase;

uses(BillingLedgerTestCase::class);

beforeEach(function () {
    config(['manual-isolation.enabled' => true]);

    $this->isoUiActor = User::factory()->createQuietly();
    $this->isoUiCustomer = $this->ledgerCustomer();

    $router = $this->isoUiCustomer->router;
    $router->update([
        'isolation_method' => 'ppp_profile',
        'isolation_profile' => 'isolir',
    ]);
    $this->isoUiCustomer->package->routers()->attach(
        $router->id,
        ['normal_profile' => 'normal']
    );
});

function isoUiPending($customer, $actor): CustomerNetworkAction
{
    return CustomerNetworkAction::create([
        'request_key' => (string) Str::uuid(),
        'customer_id' => $customer->id,
        'open_customer_id' => $customer->id,
        'open_target_key' => hash('sha256', 'ui-test-'.$customer->id),
        'kind' => 'isolate',
        'status' => 'uncertain',
        'actor_id' => $actor->id,
        'actor_name' => $actor->name,
        'reason' => 'Alasan pengujian',
        'context' => [],
    ]);
}

test('endpoint nonaktif tidak mengirim tindakan', function () {
    config(['manual-isolation.enabled' => false]);

    $this->actingAs($this->isoUiActor)
        ->post(route('customers.isolation.store', $this->isoUiCustomer))
        ->assertStatus(503);

    expect(CustomerNetworkAction::count())->toBe(0);
});

test('konfirmasi tindakan wajib dicentang', function () {
    $this->actingAs($this->isoUiActor)
        ->post(route('customers.isolation.store', $this->isoUiCustomer), [
            'kind' => 'isolate',
            'request_key' => (string) Str::uuid(),
            'version' => CustomerIsolation::version($this->isoUiCustomer),
            'reason' => 'Alasan pengujian',
        ])
        ->assertSessionHasErrors('confirmed');

    expect(CustomerNetworkAction::count())->toBe(0);
});

test('versi pelanggan lama ditolak sebelum router', function () {
    $gateway = Mockery::mock(CustomerPppActionGateway::class);
    $gateway->shouldNotReceive('change');

    expect(fn () => (new CustomerIsolation($gateway))->run(
        $this->isoUiCustomer->id,
        $this->isoUiActor,
        'isolate',
        (string) Str::uuid(),
        'Alasan pengujian',
        null,
        str_repeat('0', 64)
    ))->toThrow(ValidationException::class);

    expect(CustomerNetworkAction::count())->toBe(0);
});

test('tindakan belum pasti menahan perubahan pelanggan', function () {
    isoUiPending($this->isoUiCustomer, $this->isoUiActor);

    expect(fn () => $this->isoUiCustomer->forceFill([
        'name' => 'Nama berubah',
    ])->save())->toThrow(ValidationException::class);
});

test('tindakan belum pasti menahan persiapan invoice', function () {
    isoUiPending($this->isoUiCustomer, $this->isoUiActor);

    [$quote, $reason] = app(InvoiceGenerator::class)->prepare(
        $this->isoUiCustomer,
        BillingSetting::current(),
        2026,
        11
    );

    expect($quote)->toBeNull()
        ->and($reason)->toContain('tindakan jaringan');
});

test('buka isolir dengan sisa tagihan ditolak sebelum router', function () {
    $pause = CustomerBillingPause::create([
        'customer_id' => $this->isoUiCustomer->id,
        'open_customer_id' => $this->isoUiCustomer->id,
        'paused_from' => '2026-11-25',
        'isolated_at' => now(),
        'isolated_by_name' => 'Petugas pengujian',
        'isolation_reason' => 'Alasan pengujian',
    ]);

    $this->ledgerInvoice($this->isoUiCustomer);

    $gateway = Mockery::mock(CustomerPppActionGateway::class);
    $gateway->shouldNotReceive('change');

    expect(fn () => (new CustomerIsolation($gateway))->run(
        $this->isoUiCustomer->id,
        $this->isoUiActor,
        'restore',
        (string) Str::uuid(),
        'Permintaan pemulihan',
        $pause->id
    ))->toThrow(ValidationException::class);

    expect(CustomerNetworkAction::count())->toBe(0);
});

test('halaman pencarian menyertakan tombol dan modal isolir', function () {
    $this->actingAs($this->isoUiActor)
        ->get(route('customers.index', ['q' => $this->isoUiCustomer->name]))
        ->assertOk()
        ->assertSee('data-iso-open', false)
        ->assertSee('id="cu-isolation-modal"', false);
});

test('halaman tetap terbuka saat tabel jurnal belum tersedia', function () {
    Schema::drop('customer_network_actions');

    $this->actingAs($this->isoUiActor)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertDontSee('data-iso-open', false)
        ->assertDontSee('id="cu-isolation-modal"', false);
});

test('endpoint tertutup saat tabel jurnal belum tersedia', function () {
    Schema::drop('customer_network_actions');

    $this->actingAs($this->isoUiActor)
        ->post(route('customers.isolation.store', $this->isoUiCustomer))
        ->assertStatus(503);
});

test('respons JSON isolir berhasil tidak membuat notifikasi atas', function () {
    $gateway = Mockery::mock(CustomerPppActionGateway::class);
    $gateway->shouldReceive('change')->once()->andReturnUsing(
        function ($router, $username, $from, $to, $beforeWrite) {
            $beforeWrite(['account_id' => '*A']);

            return [
                'profile' => 'isolir',
                'reset_sessions' => 0,
                'verified_at' => '2026-11-24T12:00:00+07:00',
            ];
        }
    );

    $this->app->instance(CustomerPppActionGateway::class, $gateway);

    $this->actingAs($this->isoUiActor)
        ->postJson(
            route('customers.isolation.store', $this->isoUiCustomer),
            [
                'kind' => 'isolate',
                'request_key' => (string) Str::uuid(),
                'version' => CustomerIsolation::version($this->isoUiCustomer),
                'reason' => 'Pengujian popup proses isolir',
                'confirmed' => '1',
            ]
        )
        ->assertOk()
        ->assertJsonPath('isolation_result', true)
        ->assertJsonPath('status', 'succeeded')
        ->assertJsonPath('title', 'Isolir berhasil')
        ->assertSessionMissing('status')
        ->assertSessionHasNoErrors();

    expect(CustomerNetworkAction::count())->toBe(1)
        ->and(CustomerBillingPause::count())->toBe(1);
});

test('validasi JSON isolir tidak membuat tindakan atau notifikasi atas', function () {
    $gateway = Mockery::mock(CustomerPppActionGateway::class);
    $gateway->shouldNotReceive('change');
    $this->app->instance(CustomerPppActionGateway::class, $gateway);

    $this->actingAs($this->isoUiActor)
        ->postJson(
            route('customers.isolation.store', $this->isoUiCustomer),
            [
                'kind' => 'isolate',
                'request_key' => (string) Str::uuid(),
                'version' => CustomerIsolation::version($this->isoUiCustomer),
                'reason' => 'Pengujian tanpa konfirmasi',
            ]
        )
        ->assertStatus(422)
        ->assertJsonValidationErrors('confirmed')
        ->assertSessionMissing('status');

    expect(CustomerNetworkAction::count())->toBe(0);
});

test('halaman pelanggan menyertakan panel popup hasil isolir', function () {
    $this->actingAs($this->isoUiActor)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertSee('id="cu-iso-feedback"', false)
        ->assertSee('id="cu-iso-result-close"', false);
});

test('card hanya menampilkan rincian dan tindakan berada di template rincian', function () {
    $response = $this->actingAs($this->isoUiActor)
        ->get(route('customers.index'));

    $response->assertOk()
        ->assertSee('Rincian pelanggan')
        ->assertSee('cu-detail-actions', false);

    $html = $response->getContent();

    $cardMatched = preg_match(
        '/<article\b[^>]*class="cu-card"[^>]*>.*?<\/article>/s',
        $html,
        $card
    );

    expect($cardMatched)->toBe(1)
        ->and($card[0])->toContain('Rincian')
        ->and($card[0])->not->toContain('cu-edit')
        ->and($card[0])->not->toContain('data-iso-open');

    $templateMatched = preg_match(
        '/<template id="cu-info-'
        .preg_quote((string) $this->isoUiCustomer->id, '/')
        .'">(.*?)<\/template>/s',
        $html,
        $template
    );

    expect($templateMatched)->toBe(1)
        ->and($template[1])->toContain('cu-edit')
        ->and($template[1])->toContain('data-iso-open');
});

test('jeda terbuka menampilkan terisolir tanpa menghentikan langganan', function () {
    CustomerBillingPause::create([
        'customer_id' => $this->isoUiCustomer->id,
        'open_customer_id' => $this->isoUiCustomer->id,
        'paused_from' => '2026-11-25',
        'isolated_at' => now(),
        'isolated_by_name' => 'Petugas pengujian',
        'isolation_reason' => 'Pengujian label isolir',
    ]);

    $this->actingAs($this->isoUiActor)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertSee('Layanan: Terisolir');

    expect($this->isoUiCustomer->fresh()->service_status)->toBe('active');
});

test('tindakan belum pasti menampilkan status perlu pemeriksaan', function () {
    isoUiPending($this->isoUiCustomer, $this->isoUiActor);

    $this->actingAs($this->isoUiActor)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertSee('Layanan: Perlu pemeriksaan');
});

test('status terisolir tetap terlihat ketika tombol tindakan dinonaktifkan', function () {
    CustomerBillingPause::create([
        'customer_id' => $this->isoUiCustomer->id,
        'open_customer_id' => $this->isoUiCustomer->id,
        'paused_from' => '2026-11-25',
        'isolated_at' => now(),
        'isolated_by_name' => 'Petugas pengujian',
        'isolation_reason' => 'Pengujian status saat fitur nonaktif',
    ]);

    config(['manual-isolation.enabled' => false]);

    $this->actingAs($this->isoUiActor)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertSee('Layanan: Terisolir')
        ->assertDontSee('data-iso-open', false);
});
