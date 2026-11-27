<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\NetworkRouter;
use App\Services\CustomerConnectionMonitor;
use App\Services\CustomerNetworkReader;
use Illuminate\Http\Request;

class CustomerNetworkController extends Controller
{
    public function status(
        Request $request,
        CustomerConnectionMonitor $monitor
    ) {
        $data = $request->validate([
            'customer_ids' => ['required', 'array', 'min:1', 'max:25'],
            'customer_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);

        $customers = Customer::whereIn('id', $data['customer_ids'])
            ->get(['id', 'service_type', 'network_router_id', 'pppoe_username']);

        if ($customers->count() !== count($data['customer_ids'])) {
            return response()->json(['message' => 'Daftar pelanggan berubah.'], 422)
                ->header('Cache-Control', 'private, no-store');
        }

        $pppoe = $customers->where('service_type', 'pppoe');
        $routerIds = $pppoe->pluck('network_router_id')->unique()->values();

        if ($routerIds->count() > 1) {
            return response()->json(['message' => 'Satu permintaan hanya untuk satu router.'], 422)
                ->header('Cache-Control', 'private, no-store');
        }

        $snapshot = [
            'available' => false,
            'sessions' => [],
            'checked_at' => null,
        ];

        if ($routerIds->count() === 1) {
            $router = NetworkRouter::find($routerIds->first());
            if ($router) {
                $snapshot = $monitor->snapshot($router);
            }
        }

        $result = [];
        foreach ($customers as $customer) {
            $state = $customer->service_type !== 'pppoe'
                ? 'unsupported'
                : $monitor::state(
                    $snapshot['available'],
                    $snapshot['sessions'],
                    $customer->pppoe_username
                );

            $result[] = [
                'id' => $customer->id,
                'state' => $state,
                'checked_at' => $customer->service_type === 'pppoe'
                    ? $snapshot['checked_at'] : null,
            ];
        }

        return response()->json(['customers' => $result])
            ->header('Cache-Control', 'private, no-store');
    }

    public function check(Customer $customer, CustomerNetworkReader $reader)
    {
        if ($customer->service_type !== 'pppoe') {
            return response()->json([
                'message' => 'Pemeriksaan ini hanya tersedia untuk pelanggan PPPoE.',
            ], 422)->header('Cache-Control', 'private, no-store');
        }

        try {
            $result = $reader->read($customer);
        } catch (\Throwable $ignored) {
            return response()->json([
                'message' => 'Pemeriksaan belum berhasil. Periksa koneksi router, layanan API, dan hak baca akun API. Kredensial serta detail respons tidak ditampilkan.',
            ], 502)->header('Cache-Control', 'private, no-store');
        }

        return response()->json([
            ...$result,
            'checked_at' => now()->format('d/m/Y H:i:s T'),
        ])->header('Cache-Control', 'private, no-store');
    }
}
