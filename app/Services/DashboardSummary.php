<?php

namespace App\Services;

use App\Models\BillingBalanceEntry;
use App\Models\BillingReceipt;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardSummary
{
    public function forMonth(string $month): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month, 'Asia/Jakarta');
        $range = [$start->format('Y-m-d'), $start->endOfMonth()->format('Y-m-d')];
        $today = InvoicePeriod::today()->format('Y-m-d');

        $issued = Invoice::where('status', '!=', 'void')
            ->whereBetween('issued_on', $range);

        $receipts = BillingReceipt::where('status', 'active')
            ->whereBetween('paid_on', $range);

        $expenses = Expense::where('status', 'active')
            ->whereBetween('spent_on', $range);

        $open = Invoice::whereIn('status', ['unpaid', 'partial'])
            ->whereColumn('total', '>', 'paid_amount');

        $overdue = (clone $open)->where('due_on', '<', $today);

        $finance = [
            'issued' => (int) (clone $issued)->sum('total'),
            'issued_count' => (clone $issued)->count(),
            'cash_in' => (int) (clone $receipts)->sum('amount'),
            'receipt_count' => (clone $receipts)->count(),
            'deposit_in' => (int) (clone $receipts)
                ->where('kind', 'deposit')->sum('amount'),
            'expenses' => (int) (clone $expenses)->sum('amount'),
            'expense_count' => (clone $expenses)->count(),
            'receivables' => (int) (clone $open)
                ->sum(DB::raw('total - paid_amount')),
            'open_count' => (clone $open)->count(),
            'balance' => (int) BillingBalanceEntry::where('status', 'active')
                ->sum('amount'),
        ];

        $finance['cash_difference'] = $finance['cash_in'] - $finance['expenses'];

        // Sisa tagihan terbit pada periode pilihan, menurut kondisi saat ini.
        // Query piutang seluruh periode tetap dipertahankan untuk bagian lain.
        $periodOpen = (clone $open)->whereBetween('issued_on', $range);

        $finance['period_receivables'] = (int) (clone $periodOpen)
            ->sum(DB::raw('total - paid_amount'));

        $finance['period_open_count'] = (clone $periodOpen)->count();

        $actionsReady = Schema::hasTable('customer_network_actions');
        $pausesReady = Schema::hasTable('customer_billing_pauses');
        $classificationReady = $actionsReady && $pausesReady;

        $customers = [
            'total' => Customer::count(),
            'subscribed' => Customer::where('service_status', 'active')->count(),
            'active' => null,
            'isolated' => null,
            'stopped' => null,
            'pending' => null,
            'classification_ready' => $classificationReady,
        ];

        $pendingCustomers = collect();

        if ($actionsReady) {
            $pendingIds = DB::table('customer_network_actions')
                ->whereNotNull('open_customer_id')->select('open_customer_id');

            $pendingQuery = Customer::whereIn('id', $pendingIds);

            $customers['pending'] = (clone $pendingQuery)->count();
            $pendingCustomers = (clone $pendingQuery)
                ->orderByRaw('LOWER(name) ASC')->orderBy('id')
                ->limit(5)->get(['id', 'name']);
        }

        if ($classificationReady) {
            $pendingIds = DB::table('customer_network_actions')
                ->whereNotNull('open_customer_id')->select('open_customer_id');

            $pauseIds = DB::table('customer_billing_pauses')
                ->whereNotNull('open_customer_id')->select('open_customer_id');

            $settled = Customer::whereNotIn('id', $pendingIds);

            $customers['isolated'] = (clone $settled)
                ->whereIn('id', clone $pauseIds)->count();

            $withoutPause = (clone $settled)->whereNotIn('id', clone $pauseIds);

            $customers['active'] = (clone $withoutPause)
                ->where('service_status', 'active')->count();

            $customers['stopped'] = (clone $withoutPause)
                ->where('service_status', 'stopped')->count();
        }

        $transactions = collect();

        foreach ((clone $receipts)->with('customer:id,name')
            ->orderByDesc('paid_on')->orderByDesc('id')->limit(8)->get() as $receipt) {
            $transactions->push([
                'id' => $receipt->id,
                'kind' => 'in',
                'label' => $receipt->kind === 'deposit' ? 'Titip saldo' : 'Pembayaran',
                'description' => $receipt->customer?->name ?? 'Pelanggan tidak tersedia',
                'date' => $receipt->paid_on->format('Y-m-d'),
                'amount' => (int) $receipt->amount,
                'method' => $receipt->method === 'cash' ? 'Tunai' : 'Transfer bank',
            ]);
        }

        foreach ((clone $expenses)->orderByDesc('spent_on')
            ->orderByDesc('id')->limit(8)->get() as $expense) {
            $transactions->push([
                'id' => $expense->id,
                'kind' => 'out',
                'label' => $expense->category_label,
                'description' => $expense->description,
                'date' => $expense->spent_on->format('Y-m-d'),
                'amount' => (int) $expense->amount,
                'method' => $expense->method_label,
            ]);
        }

        $transactions = $transactions->sort(function ($a, $b) {
            return strcmp($b['date'], $a['date'])
                ?: ($b['id'] <=> $a['id'])
                ?: strcmp($a['kind'], $b['kind']);
        })->take(8)->values();

        return [
            'finance' => $finance,
            'customers' => $customers,
            'overdue_count' => (clone $overdue)->count(),
            'overdue_total' => (int) (clone $overdue)
                ->sum(DB::raw('total - paid_amount')),
            'overdue_invoices' => (clone $overdue)
                ->orderBy('due_on')->orderBy('id')->limit(6)
                ->get([
                    'id', 'number', 'customer_name',
                    'due_on', 'total', 'paid_amount',
                ]),
            'pending_customers' => $pendingCustomers,
            'transactions' => $transactions,
        ];
    }
}
