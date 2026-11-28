<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\User;
use App\Services\BillingLedger;
use App\Services\InvoiceGenerator;
use App\Services\InvoicePeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SeedDemoBilling extends Command
{
    protected $signature = 'billing:demo {--remove : Hapus semua data uji} {--yes : Lewati konfirmasi}';

    protected $description = 'Buat atau hapus pelanggan uji coba (router palsu, MikroTik tidak disentuh)';

    private const PREFIX = '[UJI] ';

    private const ROUTER = 'UJI Router (palsu)';

    private int $counter = 0;

    public function handle(InvoiceGenerator $generator, BillingLedger $ledger): int
    {
        if ($this->option('remove')) {
            return $this->remove();
        }

        if (DB::table('network_routers')->where('name', self::ROUTER)->exists()) {
            $this->error('Data uji sudah ada. Jalankan php artisan billing:demo --remove lebih dulu.');

            return self::FAILURE;
        }

        $actor = User::query()->where('role', 'super_admin')->orderBy('id')->first()
            ?? User::query()->orderBy('id')->first();

        if (! $actor) {
            $this->error('Belum ada pengguna. Buat akun super admin lebih dulu.');

            return self::FAILURE;
        }

        if (! $this->option('yes') && ! $this->confirm('Buat 8 pelanggan uji beserta tagihan dan pembayarannya?')) {
            $this->warn('Dibatalkan.');

            return self::FAILURE;
        }

        $rows = DB::transaction(fn () => $this->seed($actor, $generator, $ledger));

        $this->table(['Pelanggan', 'Wilayah', 'Tunggakan', 'Titip saldo'], $rows);
        $this->info('Selesai. Data uji bertanda [UJI]. Hapus dengan: php artisan billing:demo --remove');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function seed(User $actor, InvoiceGenerator $generator, BillingLedger $ledger): array
    {
        $wilA = $this->wilayah('UJI-A', 'Uji Wilayah A');
        $wilB = $this->wilayah('UJI-B', 'Uji Wilayah B');
        $now = now();

        $routerId = DB::table('network_routers')->insertGetId([
            'name' => self::ROUTER,
            'host' => '192.0.2.10',
            'port' => 8728,
            'use_tls' => false,
            'username' => 'uji-tidak-dipakai',
            'password' => Crypt::encryptString('uji-tidak-dipakai'),
            'isolation_method' => 'none',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ([$wilA, $wilB] as $wilayahId) {
            DB::table('network_router_wilayah')->insert([
                'wilayah_id' => $wilayahId,
                'network_router_id' => $routerId,
            ]);
        }

        $p100 = $this->package('UJI Paket 100rb', 100000, $routerId);
        $p150 = $this->package('UJI Paket 150rb', 150000, $routerId);

        $a1 = $this->customer('a1', 'A1 Lunas', $wilA, $routerId, $p150);
        $this->invoice($generator, $a1, $actor, 0);
        $this->receive($ledger, $a1, $actor, 150000);

        $a2 = $this->customer('a2', 'A2 Menunggak 2 bulan', $wilA, $routerId, $p150);
        $this->invoice($generator, $a2, $actor, 1);
        $this->invoice($generator, $a2, $actor, 0);

        $a3 = $this->customer('a3', 'A3 Bayar sebagian', $wilA, $routerId, $p150);
        $this->invoice($generator, $a3, $actor, 0);
        $this->receive($ledger, $a3, $actor, 60000);

        $a4 = $this->customer('a4', 'A4 Kelebihan bayar', $wilA, $routerId, $p150);
        $this->invoice($generator, $a4, $actor, 0);
        $this->receive($ledger, $a4, $actor, 200000);

        $b1 = $this->customer('b1', 'B1 Saldo murni', $wilB, $routerId, $p100);
        $this->receive($ledger, $b1, $actor, 100000, 'deposit');

        $b2 = $this->customer('b2', 'B2 Baru belum ditagih', $wilB, $routerId, $p150);

        $b3 = $this->customer('b3', 'B3 Pajak harga khusus', $wilB, $routerId, $p100, [
            'price_mode' => 'custom',
            'custom_monthly_price' => 175000,
            'tax_enabled' => true,
        ]);

        $b4 = $this->customer('b4', 'B4 Tunggakan dan saldo', $wilB, $routerId, $p150);
        $this->invoice($generator, $b4, $actor, 0);
        $this->receive($ledger, $b4, $actor, 80000, 'deposit');

        $this->expense($actor, $wilA, 'listrik', 250000, 'Token listrik kantor A');
        $this->expense($actor, $wilB, 'sewa', 500000, 'Sewa tempat B');

        $rows = [];

        foreach ([$a1, $a2, $a3, $a4, $b1, $b2, $b3, $b4] as $customer) {
            $due = (int) DB::table('invoices')
                ->where('customer_id', $customer->id)
                ->whereIn('status', ['unpaid', 'partial'])
                ->sum(DB::raw('total - paid_amount'));

            $rows[] = [
                $customer->name,
                $customer->wilayah_id === $wilA ? 'UJI-A' : 'UJI-B',
                'Rp'.number_format($due, 0, ',', '.'),
                'Rp'.number_format($ledger->balance($customer->id), 0, ',', '.'),
            ];
        }

        return $rows;
    }

    private function wilayah(string $code, string $name): int
    {
        $now = now();

        return (int) DB::table('wilayah')->insertGetId([
            'name' => $name,
            'code' => $code,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function package(string $name, int $price, int $routerId): int
    {
        $now = now();

        $id = (int) DB::table('internet_packages')->insertGetId([
            'name' => $name,
            'monthly_price' => $price,
            'service_type' => 'pppoe',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('internet_package_router')->insert([
            'internet_package_id' => $id,
            'network_router_id' => $routerId,
            'normal_profile' => 'default',
        ]);

        return $id;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function customer(
        string $slug,
        string $name,
        int $wilayahId,
        int $routerId,
        int $packageId,
        array $extra = []
    ): Customer {
        $this->counter++;

        $customer = new Customer(array_replace([
            'name' => self::PREFIX.$name,
            'whatsapp' => '08120000'.str_pad((string) $this->counter, 4, '0', STR_PAD_LEFT),
            'address' => 'Alamat uji coba',
            'service_type' => 'pppoe',
            'network_router_id' => $routerId,
            'internet_package_id' => $packageId,
            'pppoe_username' => 'uji-'.$slug,
            'pppoe_password' => 'uji-tidak-dipakai',
            'started_at' => InvoicePeriod::today()->startOfMonth()->subMonths(4)->format('Y-m-d'),
            'due_day' => 10,
            'invoice_day' => 1,
            'service_status' => 'active',
            'price_mode' => 'package',
            'tax_enabled' => false,
            'notes' => 'Data uji coba',
        ], $extra));

        $customer->forceFill(['wilayah_id' => $wilayahId])->save();

        return $customer;
    }

    private function invoice(InvoiceGenerator $generator, Customer $customer, User $actor, int $monthsAgo): void
    {
        $month = InvoicePeriod::today()->startOfMonth()->subMonthsNoOverflow($monthsAgo);

        [$invoice, $reason] = $generator->issue($customer->id, $month->year, $month->month, $actor, 'manual');

        if (! $invoice) {
            throw new \RuntimeException('Invoice '.$customer->name.' gagal: '.$reason);
        }
    }

    private function receive(BillingLedger $ledger, Customer $customer, User $actor, int $amount, string $kind = 'payment'): void
    {
        $ledger->receive($customer->id, $actor, [
            'request_key' => (string) Str::uuid(),
            'amount' => $amount,
            'paid_on' => InvoicePeriod::today()->format('Y-m-d'),
            'method' => 'cash',
            'reference' => 'Data uji',
            'confirmed' => '1',
        ], $kind);
    }

    private function expense(User $actor, int $wilayahId, string $category, int $amount, string $description): void
    {
        Expense::create([
            'request_key' => (string) Str::uuid(),
            'spent_on' => InvoicePeriod::today()->format('Y-m-d'),
            'category' => $category,
            'amount' => $amount,
            'method' => 'cash',
            'description' => self::PREFIX.$description,
            'status' => 'active',
            'wilayah_id' => $wilayahId,
            'recorded_by' => $actor->id,
            'recorded_by_name' => $actor->name,
        ]);
    }

    private function remove(): int
    {
        if (! $this->option('yes') && ! $this->confirm('Hapus SEMUA data uji bertanda [UJI]?')) {
            $this->warn('Dibatalkan.');

            return self::FAILURE;
        }

        DB::transaction(function () {
            $customerIds = DB::table('customers')
                ->where('name', 'like', self::PREFIX.'%')->pluck('id')->all();

            $invoiceIds = DB::table('invoices')->whereIn('customer_id', $customerIds)->pluck('id')->all();
            $receiptIds = DB::table('billing_receipts')->whereIn('customer_id', $customerIds)->pluck('id')->all();

            DB::table('billing_allocations')
                ->where(fn ($q) => $q->whereIn('invoice_id', $invoiceIds)->orWhereIn('receipt_id', $receiptIds))
                ->delete();

            DB::table('billing_balance_entries')->whereIn('customer_id', $customerIds)->update(['source_entry_id' => null]);
            DB::table('billing_balance_entries')->whereIn('customer_id', $customerIds)->delete();
            DB::table('billing_receipts')->whereIn('customer_id', $customerIds)->delete();

            if (Schema::hasTable('payments')) {
                if (Schema::hasColumn('payments', 'customer_id')) {
                    DB::table('payments')->whereIn('customer_id', $customerIds)->delete();
                } elseif (Schema::hasColumn('payments', 'invoice_id')) {
                    DB::table('payments')->whereIn('invoice_id', $invoiceIds)->delete();
                }
            }

            DB::table('invoices')->whereIn('customer_id', $customerIds)->delete();

            foreach (['customer_billing_pauses', 'customer_network_actions', 'customer_changes'] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'customer_id')) {
                    DB::table($table)->whereIn('customer_id', $customerIds)->delete();
                }
            }

            DB::table('customers')->whereIn('id', $customerIds)->delete();
            DB::table('expenses')->where('description', 'like', self::PREFIX.'%')->delete();

            $packageIds = DB::table('internet_packages')->where('name', 'like', 'UJI Paket%')->pluck('id')->all();
            $routerIds = DB::table('network_routers')->where('name', self::ROUTER)->pluck('id')->all();

            DB::table('internet_package_router')
                ->where(fn ($q) => $q->whereIn('internet_package_id', $packageIds)->orWhereIn('network_router_id', $routerIds))
                ->delete();
            DB::table('internet_packages')->whereIn('id', $packageIds)->delete();
            DB::table('network_router_wilayah')->whereIn('network_router_id', $routerIds)->delete();
            DB::table('network_routers')->whereIn('id', $routerIds)->delete();
            DB::table('wilayah')->whereIn('code', ['UJI-A', 'UJI-B'])->delete();
        });

        $this->info('Data uji dihapus.');

        return self::SUCCESS;
    }
}
