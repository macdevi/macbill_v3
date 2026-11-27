<?php

namespace App\Services;

use App\Models\BillingBalanceEntry;
use App\Models\Customer;
use App\Models\CustomerBillingPause;
use App\Models\CustomerNetworkAction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerRemoval
{
    public function __construct(
        private CustomerPppActionGateway $gateway
    ) {
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['delete' => $message]);
    }

    private function guard(Customer $customer): void
    {
        if (CustomerNetworkAction::where('open_customer_id', $customer->id)->exists()) {
            $this->reject('Masih ada tindakan jaringan yang berjalan atau hasilnya belum pasti.');
        }

        $saldo = (int) BillingBalanceEntry::where('customer_id', $customer->id)
            ->where('status', 'active')->sum('amount');

        if ($saldo !== 0) {
            $this->reject(
                'Masih ada titip saldo Rp'.number_format($saldo, 0, ',', '.')
                .'. Selesaikan saldo terlebih dahulu.'
            );
        }
    }

    private function archive(Customer $customer, User $actor, string $mode): void
    {
        $pause = CustomerBillingPause::where('open_customer_id', $customer->id)
            ->lockForUpdate()->first();

        if ($pause) {
            $now = CarbonImmutable::now(InvoicePeriod::TZ);
            $resumeOn = max(
                $pause->paused_from->format('Y-m-d'),
                $now->format('Y-m-d')
            );

            $pause->update([
                'open_customer_id' => null,
                'resumed_on' => $resumeOn,
                'resumed_at' => $now->setTimezone('UTC'),
                'resumed_by' => $actor->id,
                'resumed_by_name' => mb_substr($actor->name, 0, 255),
                'resume_reason' => 'Pelanggan dihapus dari billing.',
            ]);
        }

        $customer->deleted_by = $actor->id;
        $customer->deleted_by_name = mb_substr($actor->name, 0, 255);
        $customer->deleted_mode = $mode;
        $customer->deleted_username = $customer->pppoe_username;
        $customer->deleted_static_ip = $customer->static_ip;
        $customer->pppoe_username = null;
        $customer->pppoe_password = null;
        $customer->static_ip = null;
        $customer->save();
        $customer->delete();
    }

    public function billingOnly(int $customerId, User $actor): void
    {
        DB::transaction(function () use ($customerId, $actor) {
            $customer = Customer::whereKey($customerId)->lockForUpdate()->firstOrFail();
            $this->guard($customer);
            $this->archive($customer, $actor, 'billing');
        }, 3);
    }

    public function withRouter(
        int $customerId,
        User $actor,
        string $requestKey
    ): CustomerNetworkAction {
        if (!Str::isUuid($requestKey)) {
            $this->reject('Permintaan tindakan tidak valid.');
        }

        try {
            $action = DB::transaction(function () use ($customerId, $actor, $requestKey) {
                $customer = Customer::whereKey($customerId)->lockForUpdate()->firstOrFail();

                if (CustomerNetworkAction::where('request_key', $requestKey)->exists()) {
                    $this->reject('Permintaan sudah tercatat. Jangan kirim ulang.');
                }

                $this->guard($customer);
                $customer->load('router');

                if (
                    $customer->service_type !== 'pppoe'
                    || blank($customer->pppoe_username)
                    || !$customer->router
                ) {
                    $this->reject('Pelanggan ini tidak memiliki akun PPPoE di router.');
                }

                $context = [
                    'router_id' => (int) $customer->network_router_id,
                    'username' => $customer->pppoe_username,
                ];

                return CustomerNetworkAction::create([
                    'request_key' => $requestKey,
                    'customer_id' => $customerId,
                    'open_customer_id' => $customerId,
                    'open_target_key' => hash(
                        'sha256',
                        $context['router_id'].':'.$context['username']
                    ),
                    'kind' => 'remove_account',
                    'status' => 'reserved',
                    'actor_id' => $actor->id,
                    'actor_name' => mb_substr($actor->name, 0, 255),
                    'reason' => 'Hapus pelanggan: akun PPPoE dihapus dari router.',
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
            $customer->load('router');

            if (
                !$customer->router
                || (int) $customer->network_router_id !== $context['router_id']
                || $customer->pppoe_username !== $context['username']
            ) {
                throw new \RuntimeException('Assignment changed');
            }

            $result = $this->gateway->removeAccount(
                $customer->router,
                $context['username'],
                function (array $before) use ($action, &$armed) {
                    DB::transaction(function () use ($action, $before) {
                        $locked = CustomerNetworkAction::whereKey($action->id)
                            ->lockForUpdate()->firstOrFail();

                        if ($locked->status !== 'reserved') {
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

            if (!is_string($result['verified_at'] ?? null)) {
                throw new \RuntimeException('Invalid gateway result');
            }

            DB::transaction(function () use ($action, $customerId, $actor, $result, $context) {
                $customer = Customer::whereKey($customerId)->lockForUpdate()->firstOrFail();
                $locked = CustomerNetworkAction::whereKey($action->id)
                    ->lockForUpdate()->firstOrFail();

                if (
                    !in_array($locked->status, ['reserved', 'sending'], true)
                    || $customer->pppoe_username !== $context['username']
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

                $this->archive($customer, $actor, 'billing_router');
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
