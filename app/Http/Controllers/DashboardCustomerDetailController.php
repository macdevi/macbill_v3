<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class DashboardCustomerDetailController extends Controller
{
    public function __invoke(Request $request)
    {
        $input = $request->validate([
            'category' => ['required', Rule::in(['subscribed', 'isolated'])],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);
        $query = Customer::query();
        if ($input['category'] === 'isolated') {
            if (! Schema::hasTable('customer_network_actions')
                || ! Schema::hasTable('customer_billing_pauses')) {
                return response()->json([
                    'message' => 'Klasifikasi isolir billing belum tersedia.',
                ], 503)->header('Cache-Control', 'private, no-store');
            }
            $pendingIds = DB::table('customer_network_actions')
                ->whereNotNull('open_customer_id')->select('open_customer_id');
            $pauseIds = DB::table('customer_billing_pauses')
                ->whereNotNull('open_customer_id')->select('open_customer_id');
            $query->whereNotIn('id', $pendingIds)->whereIn('id', $pauseIds);
        }
        $search = trim((string) ($input['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($filter) use ($search) {
                $filter->where('name', 'like', '%'.$search.'%')
                    ->orWhere('pppoe_username', 'like', '%'.$search.'%');
                if (preg_match('/^(?:PLG-)?0*([1-9][0-9]*)$/i', $search, $matches)) {
                    $filter->orWhere('id', $matches[1]);
                }
            });
        }
        $page = $query->orderByRaw('LOWER(name) ASC')->orderBy('id')
            ->paginate(25, ['id', 'name', 'service_type', 'pppoe_username'],
                'page', (int) ($input['page'] ?? 1));
        $rows = $page->getCollection()->map(fn (Customer $customer) => [
            'id' => (int) $customer->id,
            'code' => $customer->code,
            'name' => (string) $customer->name,
            'service_type' => (string) $customer->service_type,
            'pppoe_username' => (string) ($customer->pppoe_username ?? ''),
        ])->values();

        return response()->json([
            'rows' => $rows, 'total' => $page->total(),
            'page' => $page->currentPage(), 'last_page' => $page->lastPage(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
