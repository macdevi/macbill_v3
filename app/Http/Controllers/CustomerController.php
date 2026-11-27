<?php

namespace App\Http\Controllers;

use App\Models\BillingSetting;
use App\Models\Customer;
use App\Models\InternetPackage;
use App\Models\NetworkRouter;
use App\Models\Scopes\WilayahScope;
use App\Models\Wilayah;
use App\Services\CustomerIsolation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'router' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:active,stopped'],
            'type' => ['nullable', 'in:pppoe,static'],
        ]);

        $query = Customer::with([
            'router',
            'package',
            'latestChange' => fn ($q) => $q->select([
                'customer_changes.id',
                'customer_changes.customer_id',
                'customer_changes.actor_name',
                'customer_changes.event',
                'customer_changes.created_at',
            ]),
        ])->orderByDesc('id');

        if (filled($filters['q'] ?? null)) {
            $q = trim($filters['q']);
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', '%'.$q.'%')
                    ->orWhere('whatsapp', 'like', '%'.$q.'%')
                    ->orWhere('pppoe_username', 'like', '%'.$q.'%')
                    ->orWhere('static_ip', 'like', '%'.$q.'%');

                if (preg_match('/^(?:PLG-)?0*(\d+)$/i', $q, $match)) {
                    $builder->orWhere('id', (int) $match[1]);
                }
            });
        }

        if (filled($filters['router'] ?? null)) {
            $query->where('network_router_id', $filters['router']);
        }
        if (filled($filters['status'] ?? null)) {
            $query->where('service_status', $filters['status']);
        }
        if (filled($filters['type'] ?? null)) {
            $query->where('service_type', $filters['type']);
        }

        $manualIsolationAvailable = CustomerIsolation::available();

        if (Schema::hasTable('customer_billing_pauses')) {
            $query->with('billingPause');
        }

        if (Schema::hasTable('customer_network_actions')) {
            $query->with('networkAction');
        }

        return response()->view('customers.index', [
            'manualIsolationAvailable' => $manualIsolationAvailable,
            'customers' => $query->paginate(25)->withQueryString(),
            'routers' => $this->visibleRouters(),
            'wilayahList' => $this->visibleWilayah(),
            'routerWilayah' => $this->routerWilayahMap(),
            'packages' => InternetPackage::with('routers')->orderBy('name')->get(),
            'settings' => BillingSetting::current(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request)
    {
        return $this->persist($request, new Customer);
    }

    public function update(Request $request, Customer $customer)
    {
        return $this->persist($request, $customer);
    }

    private function persist(Request $request, Customer $customer)
    {
        $routerId = $request->input('network_router_id');

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:150'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:5000'],
            'service_type' => ['required', 'in:pppoe,static'],
            'network_router_id' => ['required', 'integer', 'exists:network_routers,id'],
            'wilayah_id' => ['required', 'integer', 'exists:wilayah,id'],
            'internet_package_id' => ['required', 'integer', 'exists:internet_packages,id'],
            'pppoe_username' => [
                'nullable', 'required_if:service_type,pppoe', 'string', 'max:100',
                Rule::unique('customers', 'pppoe_username')
                    ->where(fn ($q) => $q->where('network_router_id', $routerId))
                    ->ignore($customer->id),
            ],
            'pppoe_password' => ['nullable', 'string', 'max:1024'],
            'static_ip' => [
                'nullable', 'required_if:service_type,static', 'ip',
                Rule::unique('customers', 'static_ip')
                    ->where(fn ($q) => $q->where('network_router_id', $routerId))
                    ->ignore($customer->id),
            ],
            'started_at' => ['required', 'date_format:Y-m-d'],
            'invoice_day' => ['nullable', 'integer', 'between:1,31'],
            'service_status' => ['required', 'in:active,stopped'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'price_mode' => ['required', 'in:package,custom'],
            'custom_monthly_price' => ['nullable', 'required_if:price_mode,custom', 'integer', 'min:0', 'max:1000000000'],
            'tax_enabled' => ['required', 'boolean'],
        ]);

        $back = function ($errors) use ($request) {
            return back()->withErrors($errors)
                ->withInput($request->except([
                    'pppoe_password', '_token', '_method',
                ]));
        };

        if ($validator->fails()) {
            return $back($validator);
        }

        $data = $validator->validated();

        if ($customer->exists && $customer->service_status === 'pending') {
            return $back(['service_status' => 'Pelanggan menunggu aktivasi router. Selesaikan lewat menu Pasang Baru.']);
        }

        $allowedWilayah = WilayahScope::allowedIds();
        if ($allowedWilayah !== null && ! in_array((int) $data['wilayah_id'], $allowedWilayah, true)) {
            return $back(['wilayah_id' => 'Anda tidak memiliki akses ke wilayah ini.']);
        }

        if (
            WilayahScope::enforced()
            && ! DB::table('network_router_wilayah')
                ->where('wilayah_id', $data['wilayah_id'])
                ->where('network_router_id', $data['network_router_id'])
                ->exists()
        ) {
            return $back(['network_router_id' => 'Router tidak melayani wilayah yang dipilih.']);
        }

        $package = InternetPackage::findOrFail($data['internet_package_id']);

        $sameAssignment = $customer->exists
            && (int) $customer->internet_package_id === (int) $package->id
            && (int) $customer->network_router_id === (int) $data['network_router_id']
            && $customer->service_type === $data['service_type'];

        if (! $package->is_active && ! $sameAssignment) {
            return $back(['internet_package_id' => 'Paket nonaktif tidak dapat dipilih untuk penempatan baru.']);
        }

        if ($package->service_type !== $data['service_type']) {
            return $back(['internet_package_id' => 'Jenis layanan paket tidak sesuai.']);
        }

        if (
            $data['service_type'] === 'pppoe' &&
            ! $package->routers()->where('network_routers.id', $data['network_router_id'])->exists()
        ) {
            return $back(['internet_package_id' => 'Paket belum dipetakan pada router yang dipilih.']);
        }

        if ($data['service_type'] === 'static') {
            $data['pppoe_username'] = null;
            $data['pppoe_password'] = null;
        } else {
            $data['static_ip'] = null;
            if (! $request->filled('pppoe_password')) {
                unset($data['pppoe_password']);
            }
        }

        if ($data['price_mode'] === 'package') {
            $data['custom_monthly_price'] = null;
        }

        if (
            empty($data['invoice_day']) &&
            BillingSetting::current()->default_invoice_day === null
        ) {
            return $back([
                'invoice_day' => 'Atur tanggal tagihan bawaan atau pilih tanggal khusus pelanggan.',
            ]);
        }

        $data['due_day'] = $data['invoice_day'] ?? null;
        $wilayahId = (int) $data['wilayah_id'];
        unset($data['wilayah_id']);

        $customer->fill($data);
        $customer->wilayah_id = $wilayahId;
        $customer->save();

        return redirect()->route('customers.index')->with(
            'status',
            'Data pelanggan disimpan di billing. Akun, profil, dan koneksi MikroTik tidak diubah.'
        );
    }

    private function visibleWilayah()
    {
        $allowed = WilayahScope::allowedIds();

        return Wilayah::query()
            ->when($allowed !== null, fn ($q) => $q->whereIn('id', $allowed))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function visibleRouters()
    {
        $allowed = WilayahScope::allowedIds();

        return NetworkRouter::query()
            ->when($allowed !== null, fn ($q) => $q->whereIn(
                'id',
                DB::table('network_router_wilayah')
                    ->whereIn('wilayah_id', $allowed)
                    ->select('network_router_id')
            ))
            ->orderBy('name')
            ->get();
    }

    private function routerWilayahMap(): array
    {
        return DB::table('network_router_wilayah')
            ->get(['network_router_id', 'wilayah_id'])
            ->groupBy('network_router_id')
            ->map(fn ($rows) => $rows->pluck('wilayah_id')->map(fn ($id) => (int) $id)->values()->all())
            ->all();
    }
}
