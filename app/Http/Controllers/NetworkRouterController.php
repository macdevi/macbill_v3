<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\NetworkRouter;
use App\Models\Scopes\WilayahScope;
use App\Models\Wilayah;
use App\Services\ImportWilayah;
use App\Services\RouterProfileReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class NetworkRouterController extends Controller
{
    public function index(Request $request)
    {
        $editId = $request->session()->getOldInput('_router_id')
            ?: $request->integer('edit');

        $editing = $editId
            ? ImportWilayah::routers()->findOrFail((int) $editId)
            : null;

        return view('routers.index', [
            'routers' => ImportWilayah::routers()->get(),
            'editing' => $editing,
        ]);
    }

    public function store(Request $request)
    {
        return $this->persist($request, new NetworkRouter);
    }

    public function update(Request $request, NetworkRouter $router)
    {
        abort_unless(ImportWilayah::routers()->whereKey($router->id)->exists(), 404);

        return $this->persist($request, $router);
    }

    public function destroy(NetworkRouter $router)
    {
        abort_unless(ImportWilayah::routers()->whereKey($router->id)->exists(), 404);

        if (Customer::withoutGlobalScopes()->where('network_router_id', $router->id)->exists()) {
            return redirect()->route('routers.index')->with(
                'status',
                'Belum dapat dihapus karena masih digunakan pelanggan. Pertahankan data untuk riwayat layanan.'
            );
        }

        if (DB::table('internet_package_router')
            ->where('network_router_id', $router->id)->exists()) {
            return redirect()->route('routers.index')->with(
                'status',
                'Router belum dapat dihapus karena masih dipakai paket internet. Hapus pemetaannya terlebih dahulu.'
            );
        }

        $name = $router->name;
        DB::transaction(function () use ($router) {
            DB::table('network_router_wilayah')
                ->where('network_router_id', $router->id)->delete();
            $router->delete();
        });

        return redirect()->route('routers.index')
            ->with('status', 'Konfigurasi router '.$name.' dihapus dari aplikasi.');
    }

    private function persist(Request $request, NetworkRouter $router)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'host' => [
                'required', 'string', 'max:253',
                function ($attribute, $value, $fail) {
                    $valid = filter_var($value, FILTER_VALIDATE_IP)
                        || filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
                    if (! $valid) {
                        $fail('Isi alamat IP atau hostname saja, tanpa protokol dan port.');
                    }
                },
            ],
            'port' => ['required', 'integer', 'between:1,65535'],
            'use_tls' => ['required', 'boolean'],
            'username' => ['required', 'string', 'max:100'],
            'password' => [
                $router->exists ? 'nullable' : 'required',
                'string', 'max:1024',
            ],
            'wan_interface' => ['nullable', 'string', 'max:100'],
            'isolation_method' => ['required', 'in:none,ppp_profile'],
            'isolation_profile' => [
                'nullable', 'required_if:isolation_method,ppp_profile',
                'string', 'max:255',
            ],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput($request->except(['password', '_token', '_method']));
        }

        $data = $validator->validated();

        if (($data['isolation_method'] ?? 'none') === 'none') {
            $data['isolation_profile'] = null;
        } else {
            // Verifikasi terhadap konfigurasi yang akan disimpan.
            $candidate = clone $router;
            $candidateData = $data;

            if ($router->exists && ! $request->filled('password')) {
                unset($candidateData['password']);
            }

            $candidate->fill($candidateData);

            try {
                $profiles = app(RouterProfileReader::class)->read($candidate);
            } catch (\Throwable $ignored) {
                return back()->withErrors([
                    'isolation_profile' => 'Profil belum dapat diverifikasi. Periksa koneksi dan hak baca API, lalu coba lagi.',
                ])->withInput($request->except(['password', '_token', '_method']));
            }

            if (! in_array($data['isolation_profile'] ?? '', $profiles, true)) {
                return back()->withErrors([
                    'isolation_profile' => 'Pilih profil isolir yang tersedia pada router ini.',
                ])->withInput($request->except(['password', '_token', '_method']));
            }
        }

        if ($router->exists && ! $request->filled('password')) {
            unset($data['password']);
        }

        $isNew = ! $router->exists;
        $router->fill($data)->save();

        if ($isNew && ($ownAreas = WilayahScope::allowedIds()) !== null) {
            Wilayah::whereIn('id', $ownAreas)->get()
                ->each(fn ($area) => $area->routers()->syncWithoutDetaching([$router->id]));
        }

        return redirect()->route('routers.index')
            ->with('status', 'Konfigurasi disimpan. Koneksi ke router belum diuji.');
    }
}
