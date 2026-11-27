<?php

use App\Models\User;
use App\Services\CustomerConnectionMonitor;
use App\Services\DashboardConnectionSummary;
use Illuminate\Support\Facades\Gate;

uses(\Tests\BillingLedgerTestCase::class);

beforeEach(function () {
    $this->connectionActor = User::factory()->createQuietly();
    $this->connectionCustomer = $this->ledgerCustomer();

    Gate::define('access-billing', fn ($user) => true);
    Gate::define('manage-system', fn ($user) => false);
});

test('ringkasan koneksi memakai total pelanggan billing', function () {
    $context = app(DashboardConnectionSummary::class)->context();

    expect($context['total'])->toBe(\App\Models\Customer::count())
        ->and($context['routers'])->toContain([
            'id' => (int) $this->connectionCustomer->network_router_id,
            'total' => 1,
        ]);
});

test('sesi pelanggan terdaftar menghasilkan online', function () {
    $router = $this->connectionCustomer->router;
    $monitor = Mockery::mock(CustomerConnectionMonitor::class);

    $monitor->shouldReceive('snapshot')->once()->with($router)->andReturn([
        'available' => true,
        'sessions' => [
            CustomerConnectionMonitor::usernameKey(
                $this->connectionCustomer->pppoe_username
            ) => true,
        ],
        'checked_at' => '2026-11-25T08:00:00+07:00',
    ]);

    $data = app(DashboardConnectionSummary::class)->forRouter($router, $monitor);

    expect($data['online'])->toBe(1)
        ->and($data['offline'])->toBe(0)
        ->and($data['unknown'])->toBe(0);
});

test('sesi kosong hanya menghasilkan offline ketika pemeriksaan tersedia', function () {
    $router = $this->connectionCustomer->router;
    $monitor = Mockery::mock(CustomerConnectionMonitor::class);

    $monitor->shouldReceive('snapshot')->once()->with($router)->andReturn([
        'available' => true,
        'sessions' => [],
        'checked_at' => '2026-11-25T08:00:00+07:00',
    ]);

    $data = app(DashboardConnectionSummary::class)->forRouter($router, $monitor);

    expect($data['online'])->toBe(0)
        ->and($data['offline'])->toBe(1)
        ->and($data['unknown'])->toBe(0);
});

test('pemeriksaan gagal tidak dianggap offline', function () {
    $router = $this->connectionCustomer->router;
    $monitor = Mockery::mock(CustomerConnectionMonitor::class);

    $monitor->shouldReceive('snapshot')->once()->with($router)->andReturn([
        'available' => false,
        'sessions' => [],
        'checked_at' => null,
    ]);

    $data = app(DashboardConnectionSummary::class)->forRouter($router, $monitor);

    expect($data['online'])->toBe(0)
        ->and($data['offline'])->toBe(0)
        ->and($data['unknown'])->toBe(1);
});

test('endpoint ringkasan dilindungi hak akses billing', function () {
    Gate::define('access-billing', fn ($user) => false);

    $monitor = Mockery::mock(CustomerConnectionMonitor::class);
    $monitor->shouldNotReceive('snapshot');
    $this->app->instance(CustomerConnectionMonitor::class, $monitor);

    $this->actingAs($this->connectionActor)
        ->getJson(route('dashboard.customer-status', [
            'router' => $this->connectionCustomer->network_router_id,
        ]))
        ->assertForbidden();
});

test('dashboard menampilkan card koneksi baru tanpa bagian pelanggan lama', function () {
    $this->actingAs($this->connectionActor)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('id="dashboard-customer-connections"', false)
        ->assertDontSee('Kondisi pelanggan saat ini');
});
