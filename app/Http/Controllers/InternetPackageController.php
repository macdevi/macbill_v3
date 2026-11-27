<?php

namespace App\Http\Controllers;

use App\Models\InternetPackage;
use App\Models\NetworkRouter;
use App\Services\RouterProfileReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InternetPackageController extends Controller
{
    public function index()
    {
        return view('packages.index', [
            'packages' => InternetPackage::with('routers')->orderBy('name')->get(),
            'routers' => NetworkRouter::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        return $this->persist($request, new InternetPackage);
    }

    public function update(Request $request, InternetPackage $package)
    {
        return $this->persist($request, $package);
    }

    private function persist(Request $request, InternetPackage $package)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'monthly_price' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'service_type' => ['required', 'in:pppoe,static'],
            'is_active' => ['required', 'boolean'],
            'rate_limit' => ['nullable', 'string', 'max:512'],
            'download_mbps' => ['nullable', 'required_if:service_type,pppoe', 'numeric', 'min:0', 'max:100000', 'decimal:0,3'],
            'upload_mbps' => ['nullable', 'required_if:service_type,pppoe', 'numeric', 'min:0', 'max:100000', 'decimal:0,3'],
            'mappings' => ['nullable', 'array', 'max:100'],
            'mappings.*' => ['array:router_id,profile'],
            'mappings.*.router_id' => [
                'required', 'integer', 'distinct', 'exists:network_routers,id',
            ],
            'mappings.*.profile' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)
                ->withInput($request->except(['_token', '_method']));
        }

        $data = $validator->validated();
        $mappings = $data['mappings'] ?? [];

        if ($data['service_type'] === 'pppoe') {
            $rate = trim((string) ($data['rate_limit'] ?? ''));

            if ($rate === '') {
                $up = rtrim(rtrim(number_format((float) $data['upload_mbps'], 3, '.', ''), '0'), '.');
                $down = rtrim(rtrim(number_format((float) $data['download_mbps'], 3, '.', ''), '0'), '.');
                $rate = $up.'M/'.$down.'M';
            }

            try {
                $parsed = \App\Services\PackageRateLimit::parse($rate);

                if (
                    abs(round($parsed['upload'], 3) - (float) $data['upload_mbps']) > 0.00001 ||
                    abs(round($parsed['download'], 3) - (float) $data['download_mbps']) > 0.00001
                ) {
                    throw new \RuntimeException('Base rates differ');
                }
            } catch (\Throwable $ignored) {
                return back()->withErrors([
                    'rate_limit' => 'Rate-limit tidak didukung atau download/upload tidak cocok. Periksa format dan nilainya.',
                ])->withInput($request->except(['_token', '_method']));
            }

            $data['rate_limit'] = preg_replace('/\\s+/', ' ', $rate);
        } else {
            $data['rate_limit'] = null;
        }

        if ($data['service_type'] === 'pppoe' && count($mappings) === 0) {
            return back()->withErrors([
                'mappings' => 'Paket PPPoE memerlukan minimal satu router dan profil normal.',
            ])->withInput($request->except(['_token', '_method']));
        }

        if ($data['service_type'] === 'static') {
            $mappings = [];
        }

        $sync = [];
        foreach ($mappings as $mapping) {
            $router = NetworkRouter::findOrFail($mapping['router_id']);

            try {
                $profiles = app(RouterProfileReader::class)->read($router);
            } catch (\Throwable $ignored) {
                return back()->withErrors([
                    'mappings' => 'Profil pada router '.$router->name.
                        ' belum dapat diverifikasi. Periksa koneksi API.',
                ])->withInput($request->except(['_token', '_method']));
            }

            if (!in_array($mapping['profile'], $profiles, true)) {
                return back()->withErrors([
                    'mappings' => 'Profil '.$mapping['profile'].
                        ' tidak ditemukan pada router '.$router->name.'.',
                ])->withInput($request->except(['_token', '_method']));
            }

            if (
                $router->isolation_method === 'ppp_profile' &&
                $router->isolation_profile === $mapping['profile']
            ) {
                return back()->withErrors([
                    'mappings' => 'Profil normal tidak boleh sama dengan profil isolir pada router '.
                        $router->name.'.',
                ])->withInput($request->except(['_token', '_method']));
            }

            $sync[$router->id] = ['normal_profile' => $mapping['profile']];
        }

        unset($data['mappings']);

        try {
            DB::transaction(function () use ($package, $data, $sync) {
                if ($package->exists) {
                    $locked = InternetPackage::whereKey($package->id)
                        ->lockForUpdate()->firstOrFail();

                    $package->setRawAttributes($locked->getAttributes(), true);

                    $errors = app(\App\Services\PackageCustomerGuard::class)
                        ->errors($package, $data, $sync);

                    if ($errors !== []) {
                        throw \Illuminate\Validation\ValidationException::withMessages($errors);
                    }
                }

                $package->fill($data)->save();
                $package->routers()->sync($sync);
            });
        } catch (\Illuminate\Validation\ValidationException $error) {
            return back()->withErrors($error->errors())
                ->withInput($request->except(['_token', '_method']));
        }

        return redirect()->route('packages.index')
            ->with('status', 'Paket disimpan. Konfigurasi MikroTik tidak diubah.');
    }

    public function destroy(InternetPackage $package)
    {
        if (\App\Models\Customer::withTrashed()->where('internet_package_id', $package->id)->exists()) {
            return redirect()->route('packages.index')->with(
                'status',
                'Belum dapat dihapus karena masih digunakan pelanggan. Pertahankan data untuk riwayat layanan.'
            );
        }

        DB::transaction(function () use ($package) {
            $package->routers()->detach();
            $package->delete();
        });

        return redirect()->route('packages.index')
            ->with('status', 'Paket dihapus dari aplikasi.');
    }
}
