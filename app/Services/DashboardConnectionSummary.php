<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\NetworkRouter;

class DashboardConnectionSummary
{
    public function context(): array
    {
        $groups = Customer::query()
            ->select('service_type', 'network_router_id')
            ->selectRaw('COUNT(*) as aggregate_count')
            ->groupBy('service_type', 'network_router_id')
            ->get();

        $total = 0;
        $unsupported = 0;
        $unassigned = 0;
        $routers = [];

        foreach ($groups as $group) {
            $count = (int) $group->aggregate_count;
            $total += $count;

            if ($group->service_type !== 'pppoe') {
                $unsupported += $count;

                continue;
            }

            if (! $group->network_router_id) {
                $unassigned += $count;

                continue;
            }

            $routers[] = [
                'id' => (int) $group->network_router_id,
                'total' => $count,
            ];
        }

        usort($routers, fn ($a, $b) => $a['id'] <=> $b['id']);

        return compact('total', 'unsupported', 'unassigned', 'routers');
    }

    public function forRouter(
        NetworkRouter $router,
        CustomerConnectionMonitor $monitor
    ): array {
        $customers = Customer::where('network_router_id', $router->id)
            ->where('service_type', 'pppoe')
            ->get(['id', 'name', 'pppoe_username']);

        $snapshot = [
            'available' => false,
            'sessions' => [],
            'checked_at' => null,
        ];

        if ($customers->isNotEmpty()) {
            try {
                $snapshot = $monitor->snapshot($router);
            } catch (\Throwable $ignored) {
                // Kegagalan pemeriksaan tetap Unknown, bukan Offline.
            }
        }

        $counts = ['online' => 0, 'offline' => 0, 'unknown' => 0];
        $detailRows = [];

        foreach ($customers as $customer) {
            $state = CustomerConnectionMonitor::state(
                $snapshot['available'],
                $snapshot['sessions'],
                $customer->pppoe_username
            );

            $counts[$state]++;
            if ($state !== 'unknown') {
                $detailRows[] = [
                    'id' => (int) $customer->id,
                    'code' => $customer->code,
                    'name' => (string) $customer->name,
                    'service_type' => 'pppoe',
                    'pppoe_username' => (string) $customer->pppoe_username,
                    'state' => $state,
                ];
            }
        }

        return [
            'router_id' => (int) $router->id,
            'total' => $customers->count(),
            'customers' => $detailRows,
            ...$counts,
            'available' => (bool) $snapshot['available'],
            'checked_at' => $snapshot['checked_at'],
        ];
    }
}
