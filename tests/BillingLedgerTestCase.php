<?php

namespace Tests;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\BillingLedger;
use App\Services\InvoicePeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

abstract class BillingLedgerTestCase extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as protected refreshVerifiedMemoryDatabase;
    }

    public function refreshDatabase(): void
    {
        $connection = config('database.default');
        $settings = config('database.connections.'.$connection, []);

        if (
            !$this->app->environment('testing')
            || ($settings['driver'] ?? null) !== 'sqlite'
            || ($settings['database'] ?? null) !== ':memory:'
            || !empty($settings['url'])
        ) {
            throw new \RuntimeException(
                'STOP: pengujian ledger hanya boleh memakai SQLite :memory: pada lingkungan testing.'
            );
        }

        foreach (DB::connection()->select('PRAGMA database_list') as $database) {
            if (!empty($database->file)) {
                throw new \RuntimeException(
                    'STOP: koneksi SQLite menunjuk file. RefreshDatabase dibatalkan.'
                );
            }
        }

        $this->refreshVerifiedMemoryDatabase();
    }

    public function ledgerCustomer(): Customer
    {
        $stamp = now();

        $routerId = DB::table('network_routers')->insertGetId([
            'name' => 'Router contoh pengujian',
            'host' => '192.0.2.1',
            'port' => 8728,
            'use_tls' => false,
            'username' => 'test-only',
            'password' => Crypt::encryptString('test-only-not-real'),
            'created_at' => $stamp,
            'updated_at' => $stamp,
        ]);

        $packageId = DB::table('internet_packages')->insertGetId([
            'name' => 'Paket contoh pengujian',
            'monthly_price' => 150000,
            'service_type' => 'pppoe',
            'is_active' => true,
            'created_at' => $stamp,
            'updated_at' => $stamp,
        ]);

        $customerId = DB::table('customers')->insertGetId([
            'name' => 'Pelanggan contoh pengujian',
            'whatsapp' => null,
            'service_type' => 'pppoe',
            'network_router_id' => $routerId,
            'internet_package_id' => $packageId,
            'pppoe_username' => 'test-'.Str::uuid(),
            'started_at' => InvoicePeriod::today()->format('Y-m-d'),
            'due_day' => 10,
            'service_status' => 'active',
            'created_at' => $stamp,
            'updated_at' => $stamp,
        ]);

        return Customer::findOrFail($customerId);
    }

    public function ledgerInvoice(
        Customer $customer,
        int $total = 150000,
        int $monthsAgo = 0
    ): Invoice {
        $period = InvoicePeriod::today()
            ->startOfMonth()
            ->subMonths($monthsAgo);
        $number = 'TEST-'.Str::uuid();

        return Invoice::create([
            'number' => $number,
            'active_period_key' => null,
            'customer_id' => $customer->id,
            'source' => 'manual',
            'customer_name' => $customer->name,
            'customer_code' => $customer->code,
            'issued_on' => $period->format('Y-m-d'),
            'due_on' => $period->addDays(9)->format('Y-m-d'),
            'period_start' => $period->format('Y-m-d'),
            'period_end' => $period->endOfMonth()->format('Y-m-d'),
            'price' => $total,
            'net' => $total,
            'tax' => 0,
            'total' => $total,
            'paid_amount' => 0,
            'status' => 'unpaid',
            'snapshot' => [
                'customer_name' => $customer->name,
                'customer_code' => $customer->code,
                'total' => $total,
            ],
        ]);
    }

    public function ledgerPayment(
        Customer $customer,
        User $user,
        int $amount,
        array $extra = [],
        string $kind = 'payment'
    ): \App\Models\BillingReceipt {
        return app(BillingLedger::class)->receive(
            $customer->id,
            $user,
            array_replace([
                'request_key' => (string) Str::uuid(),
                'amount' => $amount,
                'paid_on' => InvoicePeriod::today()->format('Y-m-d'),
                'method' => 'cash',
                'confirmed' => '1',
            ], $extra),
            $kind
        );
    }
}
