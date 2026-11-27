<?php

namespace App\Services;

use App\Models\BillingSetting;
use App\Models\Customer;
use App\Models\CustomerNetworkAction;
use App\Models\InternetPackage;
use App\Models\Scopes\WilayahScope;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerProvisioning
{
    public function __construct(
        private CustomerPppActionGateway $gateway
    ) {
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['provision' => $message]);
    }

    public function register(array $data): Customer
    {
        $routerId = (int) ($data['network_router_id'] ?? 0);
        $wilayahId = (int) ($data['wilayah_id'] ?? 0);
        $username = trim((string) ($data['pppoe_username'] ?? ''));
        $password = (string) ($data['pppoe_password'] ?? '');

        if ($username === '' || $password === '') {
            $this->reject('Username dan password PPPoE wajib diisi.');
        }

        $allowed = WilayahScope::allowedIds();
        if ($allowed !== null && !in_array($wilayahId, $allowed, true)) {
            $this->reject('Anda tidak memiliki akses ke wilayah ini.');
        }

        if (
            WilayahScope::enforced()
            && !DB::table('network_router_wilayah')
                ->where('wilayah_id', $wilayahId)
                ->where('network_router_id', $routerId)
                ->exists()
        ) {
            $this->reject('Router tidak melayani wilayah yang dipilih.');
        }

        $package = InternetPackage::find((int) ($data['internet_package_id'] ?? 0));
        if (!$package || !$package->is_active || $package->service_type !== 'pppoe') {
            $this->reject('Paket tidak tersedia untuk pemasangan PPPoE baru.');
        }

        $mapping = $package->routers()
            ->where('network_routers.id', $routerId)->first();
        if (blank($mapping?->pivot?->normal_profile)) {
            $this->reject('Paket belum dipetakan pada router yang dipilih.');
        }

        if (
            DB::table('customers')
                ->where('network_router_id', $routerId)
                ->where('pppoe_username', $username)->exists()
        ) {
            $this->reject('Username PPPoE sudah dipakai pelanggan lain di router ini.');
        }

        $invoiceDay = $data['invoice_day'] ?? null;
        if (blank($invoiceDay) && BillingSetting::current()->default_invoice_day === null) {
            $this->reject('Atur tanggal tagihan bawaan atau pilih tanggal khusus pelanggan.');
        }
        $invoiceDay = blank($invoiceDay) ? null : (int) $invoiceDay;

        $custom = ($data['price_mode'] ?? 'package') === 'custom';

        try {
            return DB::transaction(function () use (
                $data, $routerId, $wilayahId, $package, $username,
                $password, $invoiceDay, $custom
            ) {
                $customer = new Customer;
                $customer->fill([
                    'name' => $data['name'],
                    'whatsapp' => $data['whatsapp'] ?? null,
                    'address' => $data['address'] ?? null,
                    'service_type' => 'pppoe',
                    'network_router_id' => $routerId,
                    'internet_package_id' => $package->id,
                    'pppoe_username' => $username,
                    'pppoe_password' => $password,
                    'static_ip' => null,
                    'started_at' => $data['started_at'],
                    'invoice_day' => $invoiceDay,
                    'due_day' => $invoiceDay,
                    'service_status' => 'pending',
                    'notes' => $data['notes'] ?? null,
                    'price_mode' => $custom ? 'custom' : 'package',
                    'custom_monthly_price' => $custom
                        ? (int) ($data['custom_monthly_price'] ?? 0) : null,
                    'tax_enabled' => (bool) ($data['tax_enabled'] ?? false),
                    'first_billing_mode' => in_array(
                        $data['first_billing_mode'] ?? null,
                        ['prorata', 'full', 'skip'],
                        true
                    ) ? $data['first_billing_mode'] : 'prorata',
                ]);
                $customer->wilayah_id = $wilayahId;
                $customer->save();

                return $customer;
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            $this->reject('Username PPPoE sudah dipakai pelanggan lain di router ini.');
        }
    }

    private function assignment(Customer $customer): array
    {
        $customer->load(['router', 'package']);

        if (
            $customer->service_type !== 'pppoe'
            || $customer->service_status !== 'pending'
            || blank($customer->pppoe_username)
            || blank($customer->pppoe_password)
            || !$customer->router
            || !$customer->package
        ) {
            $this->reject('Pelanggan tidak memenuhi syarat aktivasi PPPoE.');
        }

        $mapping = $customer->package->routers()
            ->where('network_routers.id', $customer->network_router_id)
            ->first();

        $normal = (string) ($mapping?->pivot?->normal_profile ?? '');

        if ($normal === '') {
            $this->reject('Profil paket pada router belum dipetakan.');
        }

        return [
            'router_id' => (int) $customer->network_router_id,
            'package_id' => (int) $customer->internet_package_id,
            'username' => $customer->pppoe_username,
            'normal_profile' => $normal,
        ];
    }

    public function activate(
        int $customerId,
        User $actor,
        string $requestKey
    ): CustomerNetworkAction {
        if (!Str::isUuid($requestKey)) {
            $this->reject('Permintaan tindakan tidak valid.');
        }

        try {
            $action = DB::transaction(function () use ($customerId, $actor, $requestKey) {
                $customer = Customer::whereKey($customerId)
                    ->lockForUpdate()->firstOrFail();

                if (CustomerNetworkAction::where('request_key', $requestKey)->exists()) {
                    $this->reject('Permintaan sudah tercatat. Jangan kirim ulang.');
                }

                if (CustomerNetworkAction::where('open_customer_id', $customerId)->exists()) {
                    $this->reject('Masih ada tindakan berjalan atau hasil yang belum pasti.');
                }

                $context = $this->assignment($customer);

                return CustomerNetworkAction::create([
                    'request_key' => $requestKey,
                    'customer_id' => $customerId,
                    'open_customer_id' => $customerId,
                    'open_target_key' => hash(
                        'sha256',
                        $context['router_id'].':'.$context['username']
                    ),
                    'kind' => 'provision',
                    'status' => 'reserved',
                    'actor_id' => $actor->id,
                    'actor_name' => mb_substr($actor->name, 0, 255),
                    'reason' => 'Pasang baru: pembuatan akun PPPoE pelanggan baru.',
                    'context' => $context,
                ]);
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            $this->reject('Permintaan atau akun PPPoE sedang digunakan tindakan lain.');
        }

        $armed = false;
        $context = $action->context;

        try {
            $customer = Customer::findOrFail($customerId);

            if ($this->assignment($customer) !== $context) {
                throw new \RuntimeException('Assignment changed');
            }

            $result = $this->gateway->provision(
                $customer->router,
                $context['username'],
                (string) $customer->pppoe_password,
                $context['normal_profile'],
                function (array $before) use ($action, $customerId, $context, &$armed) {
                    DB::transaction(function () use ($action, $customerId, $context, $before) {
                        $customer = Customer::whereKey($customerId)
                            ->lockForUpdate()->firstOrFail();
                        $locked = CustomerNetworkAction::whereKey($action->id)
                            ->lockForUpdate()->firstOrFail();

                        if (
                            $locked->status !== 'reserved'
                            || $this->assignment($customer) !== $context
                        ) {
                            throw new \RuntimeException('Operation changed');
                        }

                        $locked->update([
                            'status' => 'sending',
                            'router_before' => $before,
                        ]);
                    }, 3);

                    $armed = true;
                }
            );

            if (
                !$armed
                || ($result['profile'] ?? null) !== $context['normal_profile']
                || !is_string($result['verified_at'] ?? null)
            ) {
                throw new \RuntimeException('Invalid gateway result');
            }

            DB::transaction(function () use ($action, $customerId, $context, $result) {
                $customer = Customer::whereKey($customerId)
                    ->lockForUpdate()->firstOrFail();
                $locked = CustomerNetworkAction::whereKey($action->id)
                    ->lockForUpdate()->firstOrFail();

                if (
                    $locked->status !== 'sending'
                    || $this->assignment($customer) !== $context
                ) {
                    throw new \RuntimeException('Operation changed after write');
                }

                $locked->update([
                    'status' => 'succeeded',
                    'open_customer_id' => null,
                    'open_target_key' => null,
                    'router_result' => $result,
                    'finished_at' => now(),
                ]);

                $customer->service_status = 'active';
                $customer->save();
            }, 3);
        } catch (\Throwable $e) {
            CustomerNetworkAction::whereKey($action->id)
                ->whereIn('status', ['reserved', 'sending'])
                ->update($armed ? [
                    'status' => 'uncertain',
                ] : [
                    'status' => 'rejected',
                    'open_customer_id' => null,
                    'open_target_key' => null,
                    'finished_at' => now(),
                ]);
        }

        return $action->fresh();
    }
}
