<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BillingReset
{
    public const TABLES = [
        'billing_balance_entries',
        'billing_allocations',
        'billing_receipts',
        'payments',
        'invoices',
        'customer_billing_pauses',
        'customer_network_actions',
        'customer_changes',
        'customer_import_previews',
        'customer_excel_previews',
        'customers',
        'expenses',
        'internet_package_router',
        'internet_packages',
        'network_router_wilayah',
        'network_routers',
    ];

    /**
     * @return array<string, int>
     */
    public static function counts(): array
    {
        $counts = [];

        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table)) {
                $counts[$table] = DB::table($table)->count();
            }
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    public static function run(string $actor = 'console'): array
    {
        $counts = self::counts();

        DB::transaction(function () use ($counts) {
            if (isset($counts['billing_balance_entries'])) {
                DB::table('billing_balance_entries')->update(['source_entry_id' => null]);
            }

            foreach (array_keys($counts) as $table) {
                DB::table($table)->delete();
            }
        });

        Log::warning('Data billing dikosongkan', [
            'oleh' => $actor,
            'jumlah' => $counts,
        ]);

        return $counts;
    }
}
