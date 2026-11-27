<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerBillingPause;
use App\Models\CustomerNetworkAction;
use App\Models\Invoice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerIsolation
{
    public function __construct(
        private CustomerPppActionGateway $gateway
    ) {}

    public static function available(): bool
    {
        return (bool) config('manual-isolation.enabled')
            && Schema::hasTable(
                'customer_billing_pauses'
            )
            && Schema::hasTable(
                'customer_network_actions'
            );
    }

    public static function version(Customer $customer): string
    {
        $customer->load(['router', 'package']);

        $mapping = $customer->package?->routers()
            ->where('network_routers.id', $customer->network_router_id)
            ->first();

        return hash('sha256', json_encode([
            'customer' => $customer->getRawOriginal(),
            'router' => $customer->router?->getRawOriginal(),
            'normal_profile' => $mapping?->pivot?->normal_profile,
        ], JSON_THROW_ON_ERROR));
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['isolation' => $message]);
    }

    private function assignment(Customer $customer): array
    {
        $customer->load(['router', 'package']);

        if (
            $customer->service_type !== 'pppoe'
            || $customer->service_status !== 'active'
            || blank($customer->pppoe_username)
            || ! $customer->router
            || ! $customer->package
            || $customer->router->isolation_method !== 'ppp_profile'
        ) {
            $this->reject('Pelanggan atau router belum memenuhi syarat isolir PPPoE.');
        }

        $mapping = $customer->package->routers()
            ->where('network_routers.id', $customer->network_router_id)
            ->first();

        $normal = (string) ($mapping?->pivot?->normal_profile ?? '');
        $isolation = (string) ($customer->router->isolation_profile ?? '');

        if ($normal === '' || $isolation === '' || $normal === $isolation) {
            $this->reject('Profil normal dan profil isolir belum dipetakan dengan aman.');
        }

        return [
            'router_id' => (int) $customer->network_router_id,
            'package_id' => (int) $customer->internet_package_id,
            'username' => $customer->pppoe_username,
            'normal_profile' => $normal,
            'isolation_profile' => $isolation,
        ];
    }

    public function run(
        int $customerId,
        User $actor,
        string $kind,
        string $requestKey,
        string $reason,
        ?int $expectedPauseId = null,
        ?string $expectedVersion = null
    ): CustomerNetworkAction {
        $reason = trim($reason);

        if (
            ! in_array($kind, ['isolate', 'restore'], true)
            || ! Str::isUuid($requestKey)
            || mb_strlen($reason) < 5
            || mb_strlen($reason) > 1000
        ) {
            $this->reject('Permintaan tindakan tidak valid.');
        }

        try {
            $action = DB::transaction(function () use (
                $customerId, $actor, $kind,
                $requestKey, $reason, $expectedPauseId, $expectedVersion
            ) {
                $customer = Customer::whereKey($customerId)
                    ->lockForUpdate()->firstOrFail();

                if (CustomerNetworkAction::where('request_key', $requestKey)->exists()) {
                    $this->reject('Permintaan sudah tercatat. Jangan kirim ulang.');
                }

                if (CustomerNetworkAction::where('open_customer_id', $customerId)->exists()) {
                    $this->reject('Masih ada tindakan berjalan atau hasil yang belum pasti.');
                }

                $context = $this->assignment($customer);

                if (
                    $expectedVersion !== null
                    && ! hash_equals(self::version($customer), $expectedVersion)
                ) {
                    $this->reject('Data pelanggan atau router berubah. Muat ulang halaman.');
                }

                if (
                    $kind === 'restore'
                    && Invoice::where('customer_id', $customerId)
                        ->where('status', '!=', 'void')
                        ->whereColumn('paid_amount', '<', 'total')
                        ->exists()
                ) {
                    $this->reject('Masih ada sisa invoice. Selesaikan pembayaran sebelum membuka isolir.');
                }

                $pause = CustomerBillingPause::where('open_customer_id', $customerId)
                    ->lockForUpdate()->first();

                if ($kind === 'isolate' && $pause) {
                    $this->reject('Pelanggan sudah memiliki jeda isolir terbuka.');
                }

                if ($kind === 'restore' && (
                    ! $pause || (int) $pause->id !== $expectedPauseId
                )) {
                    $this->reject('Riwayat isolir berubah. Muat ulang halaman.');
                }

                if ($kind === 'isolate' && $expectedPauseId !== null) {
                    $this->reject('Kondisi pelanggan berubah. Muat ulang halaman.');
                }

                $context['pause_id'] = $pause?->id;

                return CustomerNetworkAction::create([
                    'request_key' => $requestKey,
                    'customer_id' => $customerId,
                    'open_customer_id' => $customerId,
                    'open_target_key' => hash(
                        'sha256',
                        $context['router_id'].':'.$context['username']
                    ),
                    'kind' => $kind,
                    'status' => 'reserved',
                    'actor_id' => $actor->id,
                    'actor_name' => mb_substr($actor->name, 0, 255),
                    'reason' => $reason,
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

            if ($this->assignment($customer) !== array_diff_key(
                $context,
                ['pause_id' => true]
            )) {
                throw new \RuntimeException('Assignment changed');
            }

            $from = $kind === 'isolate'
                ? $context['normal_profile']
                : $context['isolation_profile'];
            $to = $kind === 'isolate'
                ? $context['isolation_profile']
                : $context['normal_profile'];

            $result = $this->gateway->change(
                $customer->router,
                $context['username'],
                $from,
                $to,
                function (array $before) use (
                    $action, $customerId, $context, &$armed
                ) {
                    DB::transaction(function () use (
                        $action, $customerId, $context, $before
                    ) {
                        $customer = Customer::whereKey($customerId)
                            ->lockForUpdate()->firstOrFail();
                        $locked = CustomerNetworkAction::whereKey($action->id)
                            ->lockForUpdate()->firstOrFail();

                        if (
                            $locked->status !== 'reserved'
                            || $this->assignment($customer) !== array_diff_key(
                                $context,
                                ['pause_id' => true]
                            )
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
                ! $armed
                || ($result['profile'] ?? null) !== $to
                || ! is_string($result['verified_at'] ?? null)
            ) {
                throw new \RuntimeException('Invalid gateway result');
            }

            $verified = CarbonImmutable::parse($result['verified_at'])
                ->setTimezone(InvoicePeriod::TZ);

            DB::transaction(function () use (
                $action, $customerId, $context, $kind, $result, $verified
            ) {
                $customer = Customer::whereKey($customerId)
                    ->lockForUpdate()->firstOrFail();
                $locked = CustomerNetworkAction::whereKey($action->id)
                    ->lockForUpdate()->firstOrFail();

                if (
                    $locked->status !== 'sending'
                    || $this->assignment($customer) !== array_diff_key(
                        $context,
                        ['pause_id' => true]
                    )
                ) {
                    throw new \RuntimeException('Operation changed after write');
                }

                $pause = CustomerBillingPause::where('open_customer_id', $customerId)
                    ->lockForUpdate()->first();

                if ($kind === 'isolate') {
                    if ($pause) {
                        throw new \RuntimeException('Pause already exists');
                    }

                    CustomerBillingPause::create([
                        'customer_id' => $customerId,
                        'open_customer_id' => $customerId,
                        'paused_from' => $verified->startOfDay()->addDay()->format('Y-m-d'),
                        'isolated_at' => $verified->setTimezone('UTC'),
                        'isolated_by' => $locked->actor_id,
                        'isolated_by_name' => $locked->actor_name,
                        'isolation_reason' => $locked->reason,
                    ]);
                } else {
                    if (! $pause || (int) $pause->id !== (int) $context['pause_id']) {
                        throw new \RuntimeException('Pause changed');
                    }

                    // Pemulihan pada hari isolir berarti tidak ada hari jeda.
                    $resumeOn = max(
                        $pause->paused_from->format('Y-m-d'),
                        $verified->format('Y-m-d')
                    );

                    $pause->update([
                        'open_customer_id' => null,
                        'resumed_on' => $resumeOn,
                        'resumed_at' => $verified->setTimezone('UTC'),
                        'resumed_by' => $locked->actor_id,
                        'resumed_by_name' => $locked->actor_name,
                        'resume_reason' => $locked->reason,
                    ]);
                }

                $locked->update([
                    'status' => 'succeeded',
                    'open_customer_id' => null,
                    'open_target_key' => null,
                    'router_result' => $result,
                    'finished_at' => now(),
                ]);
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
