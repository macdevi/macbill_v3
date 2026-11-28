<?php

namespace App\Http\Controllers;

use App\Models\BillingBalanceEntry;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\BillingCorrections;
use App\Services\BillingLedger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BalanceController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:150']]);
        $q = trim($data['q'] ?? '');

        $balances = BillingBalanceEntry::where('status', 'active')
            ->selectRaw('customer_id, SUM(amount) as saldo')
            ->groupBy('customer_id')
            ->havingRaw('SUM(amount) <> 0')
            ->get()->keyBy('customer_id');

        $customers = Customer::query()
            ->whereIn('id', $balances->keys())
            ->when($q !== '', fn ($query) => $this->matching($query, $q))
            ->orderBy('name')->orderBy('id')
            ->paginate(25)->withQueryString();

        return $this->page('balances.index', [
            'customers' => $customers,
            'balances' => $balances,
            'totalSaldo' => (int) $balances->sum('saldo'),
            'q' => $q,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:150']]);
        $q = trim($data['q'] ?? '');

        $customers = Customer::query()
            ->when($q !== '', fn ($query) => $this->matching($query, $q))
            ->orderBy('name')->orderBy('id')
            ->limit(30)->get();

        $saldo = BillingBalanceEntry::where('status', 'active')
            ->whereIn('customer_id', $customers->modelKeys())
            ->selectRaw('customer_id, SUM(amount) as saldo')
            ->groupBy('customer_id')
            ->pluck('saldo', 'customer_id');

        return response()->json([
            'data' => $customers->map(fn ($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'saldo' => (int) $saldo->get($c->id, 0),
                'url' => route('balances.show', $c),
            ])->values(),
        ])->header('Cache-Control', 'private, no-store');
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
        [$receipt, $applied] = DB::transaction(function () use ($request, $customer, $ledger) {
            $receipt = $ledger->receive(
                $customer->id,
                $request->user(),
                $request->only(['request_key', 'amount', 'paid_on', 'method', 'reference', 'confirmed']),
                'deposit'
            );

            return [$receipt, $ledger->applyBalance($customer->id, $request->user())];
        });

        $message = 'Titip saldo '.self::rupiah((int) $receipt->amount).' dicatat.';

        if ($applied > 0) {
            $message .= ' '.self::rupiah($applied).' langsung dipakai melunasi tunggakan.';
        }

        $left = $ledger->balance($customer->id);

        if ($left > 0) {
            $message .= ' Sisa titip saldo '.self::rupiah($left).'.';
        }

        return back()->with('status', $message);
    }

    public function apply(Request $request, Customer $customer, BillingLedger $ledger): RedirectResponse
    {
        $applied = $ledger->applyBalance($customer->id, $request->user());

        return back()->with('status', $applied > 0
            ? 'Titip saldo '.self::rupiah($applied).' dipakai melunasi tunggakan.'
            : 'Tidak ada titip saldo atau tunggakan yang bisa dipakai.');
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

    /**
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    private function matching(Builder $query, string $q): Builder
    {
        return $query->where(function (Builder $b) use ($q) {
            $b->where('name', 'like', '%'.$q.'%');

            if (preg_match('/^(?:PLG-?)?0*(\d+)$/i', $q, $m)) {
                $b->orWhere('id', (int) $m[1]);
            }
        });
    }

    private static function rupiah(int $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }

    private function page(string $view, array $data)
    {
        return response()->view($view, $data)->header('Cache-Control', 'private, no-store');
    }
}
