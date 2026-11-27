<?php

namespace App\Http\Controllers;

use App\Models\BillingBalanceEntry;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\BillingCorrections;
use App\Services\BillingLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BalanceController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:150']]);
        $q = trim($data['q'] ?? '');

        $balances = BillingBalanceEntry::where('status', 'active')
            ->selectRaw('customer_id, SUM(amount) as saldo')
            ->groupBy('customer_id')->get()->keyBy('customer_id');
        $positive = $balances->filter(fn ($b) => (int) $b->saldo !== 0);

        $customers = Customer::query()->orderBy('name');
        if ($q !== '') {
            $customers->where(function ($b) use ($q) {
                $b->where('name', 'like', '%'.$q.'%');
                if (preg_match('/^(?:PLG-?)?0*(\d+)$/i', $q, $m)) {
                    $b->orWhere('id', (int) $m[1]);
                }
            });
        } else {
            $customers->orderBy('id');
        }

        return $this->page('balances.index', [
            'customers' => $customers->limit(50)->get(['id', 'name']),
            'balances' => $balances,
            'totalSaldo' => (int) $positive->sum('saldo'),
        ]);
    }

    public function show(Customer $customer, BillingLedger $ledger)
    {
        return $this->page('balances.show', [
            'customer' => $customer,
            'balance' => $ledger->balance($customer->id),
            'due' => (int) Invoice::where('customer_id', $customer->id)
                ->whereIn('status', ['unpaid', 'partial'])
                ->selectRaw('COALESCE(SUM(total - paid_amount), 0) as due')->value('due'),
            'entries' => BillingBalanceEntry::with(['receipt', 'invoice:id,number'])
                ->where('customer_id', $customer->id)
                ->orderByDesc('id')->limit(100)->get(),
            'requestKey' => (string) Str::uuid(),
        ]);
    }

    public function deposit(Request $request, Customer $customer, BillingLedger $ledger)
    {
        $receipt = $ledger->receive(
            $customer->id,
            $request->user(),
            $request->only(['request_key', 'amount', 'paid_on', 'method', 'reference', 'confirmed']),
            'deposit'
        );

        return back()->with('status', 'Titip saldo Rp'.number_format($receipt->amount, 0, ',', '.')
            .' dicatat. Saldo dipakai otomatis saat invoice baru terbit.');
    }

    public function voidUse(Request $request, BillingBalanceEntry $entry, BillingCorrections $corrections)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'confirmed' => ['accepted'],
        ]);

        $corrections->voidBalanceUse($entry->id, $request->user(), $data['reason']);

        return back()->with('status', 'Pemakaian saldo dibatalkan. Saldo kembali dan invoice dihitung ulang.');
    }

    private function page(string $view, array $data)
    {
        return response()->view($view, $data)->header('Cache-Control', 'private, no-store');
    }
}
