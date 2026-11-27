<?php

namespace App\Http\Controllers;

use App\Services\BillingReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DataResetController extends Controller
{
    public function index(): Response
    {
        return response()->view('data-reset.index', [
            'enabled' => (bool) config('billing_reset.enabled'),
            'counts' => BillingReset::counts(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function destroy(Request $request): RedirectResponse
    {
        abort_unless(
            config('billing_reset.enabled'),
            403,
            'Fitur ini dimatikan. Set BILLING_RESET_ENABLED=true di .env.'
        );

        $request->validate([
            'confirmation' => ['required', 'in:KOSONGKAN'],
            'password' => ['required', 'current_password'],
        ], [
            'confirmation.in' => 'Ketik KOSONGKAN dengan huruf besar untuk melanjutkan.',
            'password.current_password' => 'Kata sandi tidak cocok.',
        ]);

        BillingReset::run('user #'.$request->user()->getKey());

        return redirect()->route('data-reset.index')
            ->with('status', 'Semua data billing dikosongkan. Pengguna, wilayah, dan pengaturan billing tidak diubah. MikroTik tidak disentuh.');
    }
}
