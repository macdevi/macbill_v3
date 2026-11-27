<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\CustomerIsolation;
use Illuminate\Http\Request;

class CustomerIsolationController extends Controller
{
    public function store(
        Request $request,
        Customer $customer,
        CustomerIsolation $isolation
    ) {
        abort_unless(
            CustomerIsolation::available(),
            503,
            'Fitur isolir belum aktif atau tabel pendukung belum siap.'
        );

        $data = $request->validate([
            'kind' => ['required', 'in:isolate,restore'],
            'request_key' => ['required', 'uuid'],
            'version' => ['required', 'string', 'size:64'],
            'pause_id' => ['nullable', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'confirmed' => ['accepted'],
        ]);

        $action = $isolation->run(
            $customer->id,
            $request->user(),
            $data['kind'],
            $data['request_key'],
            $data['reason'],
            isset($data['pause_id']) ? (int) $data['pause_id'] : null,
            $data['version']
        );

        if ($request->expectsJson()) {
            $succeeded = $action->status === 'succeeded';
            $rejected = $action->status === 'rejected';

            return response()->json([
                'isolation_result' => true,
                'action_id' => $action->id,
                'status' => $succeeded
                    ? 'succeeded'
                    : ($rejected ? 'rejected' : 'uncertain'),
                'title' => $succeeded
                    ? ($action->kind === 'isolate'
                        ? 'Isolir berhasil'
                        : 'Buka isolir berhasil')
                    : ($rejected
                        ? 'Tindakan ditolak'
                        : 'Hasil perlu pemeriksaan'),
                'message' => $succeeded
                    ? ($action->kind === 'isolate'
                        ? 'Profil isolir terverifikasi. Jeda penagihan dimulai besok (WIB).'
                        : 'Profil normal terverifikasi. Penagihan kembali berjalan hari ini (WIB).')
                    : ($rejected
                        ? 'Tindakan ditolak sebelum tahap penulisan router. Periksa akun, pemetaan profil, dan koneksi.'
                        : 'Hasil tindakan belum dapat dipastikan. Tindakan berikutnya dan penerbitan invoice ditahan. Jangan mengulang atau menghapus kunci jurnal.'),
            ])->header('Cache-Control', 'private, no-store');
        }

        if ($action->status === 'succeeded') {
            return redirect()->route('customers.index')->with(
                'status',
                $action->kind === 'isolate'
                    ? 'Profil isolir terverifikasi. Jeda penagihan dimulai besok (WIB).'
                    : 'Profil normal terverifikasi. Penagihan kembali berjalan hari ini (WIB).'
            );
        }

        return redirect()->route('customers.index')->withErrors([
            'isolation' => $action->status === 'rejected'
                ? 'Tindakan #'.$action->id.' ditolak sebelum tahap penulisan router. Periksa akun, pemetaan profil, dan koneksi.'
                : 'Hasil tindakan #'.$action->id.' belum dapat dipastikan. Tindakan berikutnya dan penerbitan invoice ditahan. Jangan mengulang atau menghapus kunci jurnal.',
        ]);
    }
}
