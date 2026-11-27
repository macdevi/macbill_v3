<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\InternetPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CustomerBulkPriceController extends Controller
{
    private function version(Customer $customer): string
    {
        return hash('sha256', json_encode([
            'id' => $customer->id,
            'name' => $customer->name,
            'updated_at' => $customer->getRawOriginal('updated_at'),
            'router_id' => $customer->network_router_id,
            'package_id' => $customer->internet_package_id,
            'price_mode' => $customer->price_mode,
            'custom_price' => $customer->custom_monthly_price,
            'tax_enabled' => $customer->tax_enabled,
            'service_status' => $customer->service_status,
            'package_price' => $customer->package?->monthly_price,
            'package_updated_at' => $customer->package?->getRawOriginal('updated_at'),
        ], JSON_THROW_ON_ERROR));
    }

    public function index(Request $request)
    {
        $customers = Customer::with('package')
            ->orderByRaw('LOWER(name) ASC')->orderBy('id')->get();

        $pending = [];

        if (Schema::hasTable('customer_network_actions')) {
            $pending = DB::table('customer_network_actions')
                ->whereNotNull('open_customer_id')
                ->pluck('open_customer_id')->map(fn ($id) => (int) $id)->all();
        }

        return response()->json([
            'rows' => $customers->map(function ($customer) use ($pending) {
                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'code' => $customer->code,
                    'package_price' => $customer->package
                        ? (int) $customer->package->monthly_price : null,
                    'custom_price' => $customer->price_mode === 'custom'
                        ? $customer->custom_monthly_price : null,
                    'version' => $this->version($customer),
                    'locked' => in_array((int) $customer->id, $pending, true),
                ];
            })->values(),
            'total' => $customers->count(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['array:id,version,custom_monthly_price'],
            'items.*.id' => ['required', 'integer', 'distinct'],
            'items.*.version' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'items.*.custom_monthly_price' => [
                'present', 'nullable', 'integer', 'min:0', 'max:1000000000',
            ],
        ]);

        $changed = DB::transaction(function () use ($data) {
            $items = collect($data['items'])->keyBy('id');

            $customers = Customer::whereIn('id', $items->keys())
                ->orderBy('id')->lockForUpdate()->get();

            if ($customers->count() !== $items->count()) {
                throw ValidationException::withMessages([
                    'prices' => 'Daftar pelanggan berubah. Muat ulang popup.',
                ]);
            }

            $packages = InternetPackage::whereIn(
                'id', $customers->pluck('internet_package_id')->filter()->unique()
            )->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $changed = 0;

            foreach ($customers as $customer) {
                $customer->setRelation(
                    'package', $packages->get($customer->internet_package_id)
                );

                $item = $items->get($customer->id);

                if (!hash_equals($this->version($customer), $item['version'])) {
                    throw ValidationException::withMessages([
                        'prices' => 'Data '.$customer->name
                            .' berubah sejak popup dibuka. Muat ulang sebelum menyimpan.',
                    ]);
                }

                if (
                    Schema::hasTable('customer_network_actions')
                    && DB::table('customer_network_actions')
                        ->where('open_customer_id', $customer->id)->exists()
                ) {
                    throw ValidationException::withMessages([
                        'prices' => 'Data '.$customer->name
                            .' dikunci karena tindakan jaringan belum selesai.',
                    ]);
                }

                $amount = $item['custom_monthly_price'];
                $amount = $amount === null ? null : (int) $amount;

                if ($amount === null && !$customer->package) {
                    throw ValidationException::withMessages([
                        'prices' => 'Paket '.$customer->name.' belum tersedia.',
                    ]);
                }

                $customer->fill([
                    'price_mode' => $amount === null ? 'package' : 'custom',
                    'custom_monthly_price' => $amount,
                ]);

                if ($customer->isDirty(['price_mode', 'custom_monthly_price'])) {
                    $customer->save();
                    $changed++;
                }
            }

            return $changed;
        });

        return response()->json([
            'saved' => true,
            'changed' => $changed,
            'message' => $changed.' nominal pelanggan disimpan.',
        ])->header('Cache-Control', 'private, no-store');
    }
}
