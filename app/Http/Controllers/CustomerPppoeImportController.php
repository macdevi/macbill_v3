<?php

namespace App\Http\Controllers;

use App\Models\BillingSetting;
use App\Models\Customer;
use App\Models\InternetPackage;
use App\Models\NetworkRouter;
use App\Services\RouterSecretReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CustomerPppoeImportController extends Controller
{
    private function fingerprint(NetworkRouter $router): string
    {
        return hash('sha256', json_encode([
            $router->host, $router->port, $router->use_tls, $router->username,
            $router->isolation_method, $router->isolation_profile,
        ], JSON_THROW_ON_ERROR));
    }

    private function packages(int $routerId)
    {
        return InternetPackage::where('service_type', 'pppoe')
            ->where('is_active', true)
            ->whereHas('routers', fn ($q) => $q->where('network_routers.id', $routerId))
            ->with(['routers' => fn ($q) => $q->where('network_routers.id', $routerId)])
            ->orderBy('name')->get();
    }

    private function response(array $data)
    {
        return response()->view('customers.import-pppoe', $data)
            ->header('Cache-Control', 'private, no-store');
    }

    public function index()
    {
        return $this->response([
            'routers' => \App\Services\ImportWilayah::routers()->get(),
            'batch' => null,
        ]);
    }

    public function preview(Request $request, RouterSecretReader $reader)
    {
        $data = $request->validate([
            'network_router_id' => ['required', 'integer', 'exists:network_routers,id'],
        ]);

        $router = \App\Services\ImportWilayah::routers()->findOrFail($data['network_router_id']);

        try {
            $rows = $reader->read($router);
            foreach ($rows as &$row) {
                $row['name'] = \App\Services\CustomerNameSuggestion::fromSecret(
                    $row['username']
                );
            }
            unset($row);

            $encrypted = Crypt::encryptString(json_encode(
                $rows,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            ));
        } catch (\Throwable $ignored) {
            return redirect()->route('customer-pppoe.index')->withErrors([
                'router' => 'Daftar PPP secret belum dapat dibaca. Periksa koneksi, hak baca API, dan ukuran data. Kredensial dan detail respons tidak ditampilkan.',
            ]);
        }

        $id = (string) Str::uuid();

        DB::transaction(function () use ($request, $router, $encrypted, $id) {
            DB::table('customer_import_previews')
                ->where('expires_at', '<=', now())->delete();

            DB::table('customer_import_previews')->insert([
                'id' => $id,
                'user_id' => $request->user()->id,
                'network_router_id' => $router->id,
                'router_fingerprint' => $this->fingerprint($router),
                'encrypted_payload' => $encrypted,
                'expires_at' => now()->addMinutes(20),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('customer-pppoe.show', $id);
    }

    public function show(Request $request, string $batch)
    {
        $snapshot = DB::table('customer_import_previews')
            ->where('id', $batch)
            ->where('user_id', $request->user()->id)
            ->where('expires_at', '>', now())
            ->first();

        if (!$snapshot) {
            return redirect()->route('customer-pppoe.index')
                ->withErrors(['preview' => 'Preview tidak tersedia atau sudah kedaluwarsa. Baca router kembali.']);
        }

        $router = NetworkRouter::findOrFail($snapshot->network_router_id);
        if (!hash_equals($snapshot->router_fingerprint, $this->fingerprint($router))) {
            return redirect()->route('customer-pppoe.index')
                ->withErrors(['preview' => 'Konfigurasi koneksi router berubah. Buat preview baru.']);
        }

        try {
            $rows = json_decode(
                Crypt::decryptString($snapshot->encrypted_payload),
                true, 512, JSON_THROW_ON_ERROR
            );
        } catch (\Throwable $ignored) {
            return redirect()->route('customer-pppoe.index')
                ->withErrors(['preview' => 'Preview tidak dapat dibuka. Buat preview baru.']);
        }

        $packages = $this->packages($router->id);
        $existingRows = Customer::withoutGlobalScopes()->where('network_router_id', $router->id)
            ->whereNotNull('pppoe_username')->get();
        $existing = $existingRows->pluck('pppoe_username')->all();
        $existingPackages = $existingRows
            ->keyBy('pppoe_username')->map->internet_package_id;

        $visible = [];
        foreach ($rows as $index => $row) {
            if (in_array($row['username'], $existing, true)) {
                continue;
            }

            $matches = $packages->filter(
                fn ($p) => $p->routers->first()?->pivot->normal_profile === $row['profile']
            );

            $visible[] = [
                'index' => $index,
                'username' => $row['username'],
                'name' => $row['name'],
                'profile' => $row['profile'],
                'service' => $row['service'],
                'disabled' => $row['disabled'],
                'password_available' => $row['password'] !== null,
                'duplicate' => in_array($row['username'], $existing, true),
                'valid_username' => $row['username'] !== ''
                    && mb_strlen($row['username']) <= 100
                    && trim($row['username']) === $row['username'],
                'isolation_source' => $router->isolation_method === 'ppp_profile'
                    && filled($router->isolation_profile)
                    && $router->isolation_profile === $row['profile'],
                'existing_package_id' => $existingPackages->get($row['username']),
                'suggested_package' => (
                    $router->isolation_method === 'ppp_profile'
                    && filled($router->isolation_profile)
                    && $router->isolation_profile === $row['profile']
                ) ? null : ($matches->count() === 1 ? $matches->first()->id : null),
                'ambiguous' => $matches->count() > 1,
            ];
        }
        unset($rows);

        $visible = collect($visible)->sort(function ($a, $b) {
            $nameOrder = strcmp(
                mb_strtolower(trim($a['name']), 'UTF-8'),
                mb_strtolower(trim($b['name']), 'UTF-8')
            );

            return $nameOrder
                ?: strcmp(
                    mb_strtolower($a['username'], 'UTF-8'),
                    mb_strtolower($b['username'], 'UTF-8')
                )
                ?: ($a['index'] <=> $b['index']);
        })->values();

        return $this->response([
            'batch' => $snapshot,
            'router' => $router,
            'rows' => $visible,
            'packages' => $packages,
            'settings' => BillingSetting::current(),
            'isolationImportReady' => \App\Services\CustomerIsolation::available(),
            'wilayahChoices' => count(\App\Services\ImportWilayah::candidates($router->id)) > 1
                ? \App\Models\Wilayah::whereIn('id', \App\Services\ImportWilayah::candidates($router->id))->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }

    public function commit(Request $request, string $batch)
    {
        if ($request->exists('selection_payload')) {
            $payload = $request->validate([
                'selection_payload' => ['required', 'string', 'max:2000000', 'json'],
            ]);

            $request->merge([
                'items' => json_decode(
                    $payload['selection_payload'],
                    true, 512, JSON_THROW_ON_ERROR
                ),
            ]);
        }

        $validator = Validator::make($request->all(), [
            'invoice_day' => ['nullable', 'integer', 'between:1,31'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*' => ['array:selected,name,package_id'],
            'items.*.selected' => ['required', 'in:1'],
            'items.*.name' => ['required', 'string', 'max:150'],
            'items.*.package_id' => ['required', 'integer'],
        ], [
            'items.required' => 'Pilih minimal satu baris untuk diimport.',
            'items.*.name.required' => 'Nama pelanggan pada baris terpilih wajib diisi.',
            'items.*.package_id.required' => 'Pilih paket untuk setiap baris terpilih.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        if (empty($data['invoice_day']) && BillingSetting::current()->default_invoice_day === null) {
            return back()->withErrors([
                'invoice_day' => 'Atur tanggal tagihan bawaan atau pilih tanggal khusus.',
            ])->withInput();
        }

        try {
            $result = DB::transaction(function () use ($request, $batch, $data) {
                $snapshot = DB::table('customer_import_previews')
                    ->where('id', $batch)
                    ->where('user_id', $request->user()->id)
                    ->where('expires_at', '>', now())
                    ->lockForUpdate()->first();

                if (!$snapshot) {
                    throw new \RuntimeException('Invalid preview');
                }

                $router = NetworkRouter::whereKey($snapshot->network_router_id)
                    ->lockForUpdate()->firstOrFail();

                if (!hash_equals($snapshot->router_fingerprint, $this->fingerprint($router))) {
                    throw new \RuntimeException('Router changed');
                }

                $rows = json_decode(
                    Crypt::decryptString($snapshot->encrypted_payload),
                    true, 512, JSON_THROW_ON_ERROR
                );

                $allowedPackages = $this->packages($router->id)->pluck('id')->all();
                $defaultTaxEnabled = (bool) BillingSetting::current()->default_tax_enabled;
                $created = 0;
                $skipped = 0;
                $synced = 0;

                foreach ($data['items'] as $index => $item) {
                    if (
                        !ctype_digit((string) $index) ||
                        !array_key_exists((int) $index, $rows) ||
                        !in_array((int) $item['package_id'], $allowedPackages, true)
                    ) {
                        throw new \RuntimeException('Invalid selection');
                    }

                    $source = $rows[(int) $index];
                    $username = $source['username'];

                    if (
                        $username === '' ||
                        mb_strlen($username) > 100 ||
                        trim($username) !== $username
                    ) {
                        throw new \RuntimeException('Unsupported username');
                    }

                    $isolationSource = $router->isolation_method === 'ppp_profile'
                        && filled($router->isolation_profile)
                        && $router->isolation_profile === $source['profile'];

                    $matches = Customer::withoutGlobalScopes()->where('network_router_id', $router->id)
                        ->where('pppoe_username', $username)
                        ->lockForUpdate()->get();

                    if ($matches->count() > 1) {
                        throw new \RuntimeException('Identitas pelanggan ambigu.');
                    }

                    $existingCustomer = $matches->first();

                    if (
                        $existingCustomer
                        && ($allowedArea = \App\Models\Scopes\WilayahScope::allowedIds()) !== null
                        && !in_array((int) $existingCustomer->wilayah_id, $allowedArea, true)
                    ) {
                        $skipped++;
                        continue;
                    }


                    if ($existingCustomer && !$isolationSource) {
                        $skipped++;
                        continue;
                    }

                    if ($isolationSource) {
                        if (
                            $source['disabled']
                            || !\App\Services\CustomerIsolation::available()
                        ) {
                            throw new \RuntimeException('Konfirmasi isolir belum sesuai.');
                        }

                        if ($existingCustomer && (
                            $existingCustomer->service_type !== 'pppoe'
                            || $existingCustomer->service_status !== 'active'
                            || (int) $existingCustomer->internet_package_id
                                !== (int) $item['package_id']
                        )) {
                            throw new \RuntimeException('Paket/status pelanggan lama berbeda.');
                        }
                    }

                    if ($existingCustomer) {
                        $customer = $existingCustomer;
                    } else {
                        $customer = new Customer([
                        'name' => $item['name'],
                        'service_type' => 'pppoe',
                        'network_router_id' => $router->id,
                        'internet_package_id' => (int) $item['package_id'],
                        'pppoe_username' => $username,
                        'pppoe_password' => $source['password'],
                        'started_at' => now('Asia/Jakarta')->toDateString(),
                        'invoice_day' => $data['invoice_day'] ?? null,
                        'due_day' => $data['invoice_day'] ?? null,
                        'service_status' => 'active',
                        'price_mode' => 'package',
                        'tax_enabled' => $defaultTaxEnabled,
                    ]);
                        $customer->wilayah_id = \App\Services\ImportWilayah::resolve($request->integer('wilayah_id') ?: null, (int) $router->id);
                        $customer->save();
                        $created++;
                    }

                    if ($isolationSource) {
                        $recorded = app(\App\Services\PppoeImportIsolation::class)
                            ->record($customer, $request->user());

                        if ($existingCustomer) {
                            if ($recorded) {
                                $synced++;
                            } else {
                                $skipped++;
                            }
                        }
                    }
                }

                return compact('created', 'skipped', 'synced');
            });
        } catch (\DomainException $e) {
            return back()->withErrors(['import' => $e->getMessage()])->withInput();
        } catch (\Throwable $ignored) {
            return back()->withErrors([
                'import' => 'Import dibatalkan tanpa menyimpan sebagian baris. Status pelanggan baru ditentukan otomatis. Untuk profil isolir, jeda dimulai besok WIB setelah verifikasi. Paket pelanggan lama harus tetap sama; akun harus enabled dan profilnya masih isolir. Pastikan tidak ada tindakan terbuka. Preview, koneksi, atau pemetaan juga mungkin berubah. Buat preview baru bila perlu.',
            ])->withInput();
        }

        return redirect()->route('customer-pppoe.show', [
            'batch' => $batch,
            'page' => max(1, (int) $request->query('page', 1)),
        ])->with(
            'status',
            $result['created'].' pelanggan ditambahkan, '.$result['skipped'].
            ' baris dilewati, '.$result['synced'].
            ' pelanggan lama disinkronkan isolirnya. Tidak ada perubahan pada MikroTik.'
        );
    }

    public function discard(Request $request, string $batch)
    {
        DB::table('customer_import_previews')
            ->where('id', $batch)
            ->where('user_id', $request->user()->id)->delete();

        return redirect()->route('customers.index')
            ->with('status', 'Preview import ditutup dan data sementara dihapus.');
    }
}
