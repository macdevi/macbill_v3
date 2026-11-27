<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\CustomerRemoval;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerDeletionController extends Controller
{
    public function destroy(Request $request, Customer $customer, CustomerRemoval $removal)
    {
        $data = $request->validate([
            'mode' => ['required', 'in:billing,billing_router'],
            'request_key' => ['required', 'uuid'],
            'confirm_username' => ['nullable', 'string', 'max:100'],
        ]);

        $name = $customer->name;

        try {
            if ($data['mode'] === 'billing') {
                $removal->billingOnly($customer->id, $request->user());

                return back()->with(
                    'status',
                    'Pelanggan '.$name.' dihapus dari billing. Akun di MikroTik tidak diubah. Tagihan, kuitansi, dan saldo tetap tersimpan.'
                );
            }

            if (
                blank($customer->pppoe_username)
                || ! hash_equals(
                    (string) $customer->pppoe_username,
                    (string) ($data['confirm_username'] ?? '')
                )
            ) {
                return back()->with('status', 'Tidak dihapus: username konfirmasi tidak cocok.');
            }

            $action = $removal->withRouter(
                $customer->id, $request->user(), $data['request_key']
            );
        } catch (ValidationException $e) {
            return back()->with(
                'status',
                'Tidak dihapus: '.collect($e->errors())->flatten()->first()
            );
        }

        return back()->with('status', match ($action->status) {
            'succeeded' => 'Pelanggan '.$name.' dihapus dari billing dan MikroTik. Tagihan, kuitansi, dan saldo tetap tersimpan.',
            'rejected' => 'Tidak dihapus: router menolak atau tidak dapat dihubungi. Tidak ada yang berubah.',
            default => 'Hasil di router untuk '.$name.' belum dapat dipastikan. Jangan coba lagi. Periksa akun PPPoE di MikroTik terlebih dahulu.',
        });
    }
}
