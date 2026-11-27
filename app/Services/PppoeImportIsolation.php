<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerBillingPause;
use App\Models\CustomerNetworkAction;
use App\Models\NetworkRouter;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PppoeImportIsolation
{
    public function __construct(private CustomerPppActionGateway $gateway) {}

    public function record(Customer $source, User $actor): bool
    {
        return DB::transaction(function () use ($source, $actor) {
            if (! CustomerIsolation::available()) {
                throw new \RuntimeException('Pencatatan isolir belum tersedia.');
            }

            $router = NetworkRouter::whereKey($source->network_router_id)
                ->lockForUpdate()->firstOrFail();

            $customer = Customer::whereKey($source->id)
                ->lockForUpdate()->firstOrFail();

            if (
                (int) $customer->network_router_id !== (int) $router->id
                || $customer->service_type !== 'pppoe'
                || $customer->service_status !== 'active'
                || blank($customer->pppoe_username)
                || $router->isolation_method !== 'ppp_profile'
                || blank($router->isolation_profile)
            ) {
                throw new \RuntimeException('Pelanggan belum memenuhi syarat.');
            }

            $package = $customer->package;

            if (! $package || ! $package->is_active || $package->service_type !== 'pppoe') {
                throw new \RuntimeException('Paket normal tidak tersedia.');
            }

            $mapping = $package->routers()
                ->where('network_routers.id', $router->id)->first();

            $normal = (string) ($mapping?->pivot?->normal_profile ?? '');
            $isolation = (string) $router->isolation_profile;

            if ($normal === '' || $normal === $isolation) {
                throw new \RuntimeException('Pemetaan profil normal tidak aman.');
            }

            $targetKey = hash(
                'sha256',
                $router->id.':'.$customer->pppoe_username
            );

            if (CustomerNetworkAction::where(function ($query) use (
                $customer, $targetKey
            ) {
                $query->where('open_customer_id', $customer->id)
                    ->orWhere('open_target_key', $targetKey);
            })->exists()) {
                throw new \RuntimeException('Masih ada tindakan jaringan terbuka.');
            }

            if (CustomerBillingPause::where(
                'open_customer_id', $customer->id
            )->exists()) {
                return false;
            }

            $reason = 'Pencatatan isolir yang sudah ada di MikroTik melalui impor PPPoE. '
                .'Tanggal isolir manual sebelumnya tidak diketahui.';

            $action = CustomerNetworkAction::create([
                'request_key' => (string) Str::uuid(),
                'customer_id' => $customer->id,
                'open_customer_id' => $customer->id,
                'open_target_key' => $targetKey,
                'kind' => 'adopt_isolation',
                'status' => 'reserved',
                'actor_id' => $actor->id,
                'actor_name' => mb_substr($actor->name, 0, 255),
                'reason' => $reason,
                'context' => [
                    'source' => 'pppoe_import',
                    'router_id' => $router->id,
                    'package_id' => $package->id,
                    'username' => $customer->pppoe_username,
                    'normal_profile' => $normal,
                    'isolation_profile' => $isolation,
                    'historical_isolation_time_unknown' => true,
                    'timestamp_semantics' => 'adoption_verified_at',
                ],
            ]);

            $result = $this->gateway->inspectIsolation(
                $router, $customer->pppoe_username, $normal, $isolation
            );

            if (
                ($result['profile'] ?? null) !== $isolation
                || empty($result['account_id'])
                || ! is_string($result['verified_at'] ?? null)
                || ! is_int($result['session_count'] ?? null)
                || $result['session_count'] < 0
            ) {
                throw new \RuntimeException('Hasil pemeriksaan tidak valid.');
            }

            $verified = CarbonImmutable::parse($result['verified_at'])
                ->setTimezone(InvoicePeriod::TZ);

            CustomerBillingPause::create([
                'customer_id' => $customer->id,
                'open_customer_id' => $customer->id,
                'paused_from' => $verified->startOfDay()->addDay()->format('Y-m-d'),
                'isolated_at' => $verified->setTimezone('UTC'),
                'isolated_by' => $actor->id,
                'isolated_by_name' => mb_substr($actor->name, 0, 255),
                'isolation_reason' => $reason,
            ]);

            $action->update([
                'status' => 'succeeded',
                'open_customer_id' => null,
                'open_target_key' => null,
                'router_result' => $result,
                'finished_at' => now(),
            ]);

            return true;
        });
    }
}
