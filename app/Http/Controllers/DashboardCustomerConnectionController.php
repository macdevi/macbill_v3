<?php

namespace App\Http\Controllers;

use App\Models\NetworkRouter;
use App\Services\CustomerConnectionMonitor;
use App\Services\DashboardConnectionSummary;

class DashboardCustomerConnectionController extends Controller
{
    public function __invoke(
        NetworkRouter $router,
        DashboardConnectionSummary $summary,
        CustomerConnectionMonitor $monitor
    ) {
        return response()->json(
            $summary->forRouter($router, $monitor)
        )->header('Cache-Control', 'private, no-store');
    }
}
