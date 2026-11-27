<?php

namespace App\Http\Controllers;

use App\Models\BillingSetting;
use App\Models\Customer;
use App\Models\CustomerNetworkAction;
use App\Models\InternetPackage;
use App\Models\NetworkRouter;
use App\Models\Scopes\WilayahScope;
use App\Models\Wilayah;
use App\Services\CustomerProvisioning;
use App\Services\InvoicePeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PasangBaruController extends Controller
{
    private const SENSITIVE = ['pppoe_password', '_token', '_method'];

    public function create()
    {
        $allowed = WilayahScope::allowedIds();

        $wilayah = Wilayah::when(
            $allowed !== null,
            fn ($q) => $q->whereIn('id', $allowed)
        )->orderBy('id')->get();

        $routerWilayah = DB::table('network_router_wilayah')->get()
            ->groupBy('network_router_id')
            ->map(fn ($rows) => $rows->pluck('wilayah_id')
                ->map(fn ($v) => (int) $v)->values())
            ->all();

        $routers = NetworkRouter::orderBy('id')->get();

        $packages = InternetPackage::where('is_active', true)
            ->where('service_type', 'pppoe')
            ->with('routers')->orderBy('id')->get();

        $packageRouters = $packages->mapWithKeys(fn ($p) => [
            $p->id => $p->routers->pluck('id')->map(fn ($v) => (int) $v)->values(),
        ])->all();

        $pending = Customer::where('service_status', 'pending')
            ->with('router')->orderByDesc('id')->get();

        $open = $pending->isEmpty()
            ? collect()
            : CustomerNetworkAction::whereIn('open_customer_id', $pending->pluck('id'))
                ->pluck('status', 'open_customer_id');

        return view('pasang-baru.create', [
            'wilayah' => $wilayah,
            'routers' => $routers,
            'packages' => $packages,
            'pending' => $pending,
            'open' => $open,
            'defaultInvoiceDay' => BillingSetting::current()->default_invoice_day,
            'today' => InvoicePeriod::today()->format('Y-m-d'),
            'config' => [
                'enforced' => WilayahScope::enforced(),
                'routerWilayah' => $routerWilayah,
                'packageRouters' => $packageRouters,
            ],
        ]);
    }

    public function store(Request $request, CustomerProvisioning $provisioning)
    {
        $validator = Validator::make($request->all(), [
            'request_key' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:150'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:5000'],
            'wilayah_id' => ['required', 'integer', 'exists:wilayah,id'],
            'network_router_id' => ['required', 'integer', 'exists:network_routers,id'],
            'internet_package_id' => ['required', 'integer', 'exists:internet_packages,id'],
            'pppoe_username' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._@-]+$/'],
            'pppoe_password' => ['required', 'string', 'min:4', 'max:128'],
            'started_at' => ['required', 'date_format:Y-m-d'],
            'invoice_day' => ['nullable', 'integer', 'between:1,31'],
            'price_mode' => ['required', 'in:package,custom'],
            'custom_monthly_price' => ['nullable', 'required_if:price_mode,custom', 'integer', 'min:0', 'max:1000000000'],
            'tax_enabled' => ['required', 'boolean'],
            'first_billing_mode' => ['required', 'in:prorata,full,skip'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'pppoe_username.regex' => 'Username hanya boleh huruf, angka, titik, garis bawah, @, dan tanda hubung.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)
                ->withInput($request->except(self::SENSITIVE));
        }

        $data = $validator->validated();

        try {
            $customer = $provisioning->register($data);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())
                ->withInput($request->except(self::SENSITIVE));
        }

        return $this->run($provisioning, $customer, $request, $data['request_key']);
    }

    public function activate(
        Request $request,
        Customer $customer,
        CustomerProvisioning $provisioning
    ) {
        $data = $request->validate(['request_key' => ['required', 'uuid']]);

        return $this->run($provisioning, $customer, $request, $data['request_key']);
    }

    private function run(
        CustomerProvisioning $provisioning,
        Customer $customer,
        Request $request,
        string $key
    ) {
        try {
            $action = $provisioning->activate($customer->id, $request->user(), $key);
        } catch (ValidationException $e) {
            return redirect()->route('pasang-baru.create')->withErrors($e->errors());
        }

        $back = redirect()->route('pasang-baru.create');

        return match ($action->status) {
            'succeeded' => $back->with(
                'status',
                'Pelanggan '.$customer->name.' terdaftar dan akun PPPoE berhasil dibuat di router.'
            ),
            'rejected' => $back->with(
                'warning',
                'Pelanggan '.$customer->name.' tersimpan dengan status Menunggu aktivasi, tetapi router menolak atau tidak dapat dihubungi. Tidak ada akun yang dibuat. Periksa router, username, dan profil, lalu coba aktifkan lagi dari daftar di bawah.'
            ),
            default => $back->with(
                'warning',
                'Hasil di router untuk '.$customer->name.' belum dapat dipastikan. Jangan coba lagi. Periksa akun PPPoE di MikroTik terlebih dahulu.'
            ),
        };
    }
}
