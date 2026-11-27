<?php

namespace App\Http\Controllers;

use App\Services\DashboardSummary;
use App\Services\InvoicePeriod;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        DashboardSummary $summary,
        \App\Services\DashboardConnectionSummary $connections
    )
    {
        $data = $request->validate([
            'month' => [
                'nullable',
                'date_format:Y-m',
                'regex:/^[2-9]\d{3}-(0[1-9]|1[0-2])$/',
            ],
        ]);

        $month = $data['month'] ?? InvoicePeriod::today()->format('Y-m');
        $canBilling = $request->user()->can('access-billing');
        $canManage = $request->user()->can('manage-system');

        return response()->view('dashboard', [
            'month' => $month,
            'monthLabel' => CarbonImmutable::createFromFormat(
                '!Y-m', $month, 'Asia/Jakarta'
            )->locale('id')->translatedFormat('F Y'),
            'canBilling' => $canBilling,
            'canManage' => $canManage,
            'summary' => $canBilling ? $summary->forMonth($month) : null,
            'connectionContext' => $canBilling ? $connections->context() : null,
            'asOf' => now('Asia/Jakarta')->format('d/m/Y H:i'),
        ])->header('Cache-Control', 'private, no-store');
    }
}
