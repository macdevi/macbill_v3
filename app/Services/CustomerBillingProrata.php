<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerBillingPause;
use Carbon\CarbonImmutable;

class CustomerBillingProrata
{
    public static function forPeriod(
        Customer $customer,
        int $monthlyPrice,
        string $periodStart,
        string $periodEnd,
        ?string $startedOn = null
    ): array {
        $pauses = CustomerBillingPause::query()
            ->where('customer_id', $customer->id)
            ->whereDate('paused_from', '<=', $periodEnd)
            ->where(function ($query) use ($periodStart) {
                $query->whereNull('resumed_on')
                    ->orWhereDate('resumed_on', '>', $periodStart);
            })
            ->orderBy('paused_from')
            ->get()
            ->map(fn ($pause) => [
                'paused_from' => $pause->paused_from->format('Y-m-d'),
                'resumed_on' => $pause->resumed_on?->format('Y-m-d'),
            ])
            ->all();

        $preStart = 0;

        if (
            $startedOn !== null
            && $startedOn > $periodStart
            && $startedOn <= $periodEnd
        ) {
            $pauses[] = ['paused_from' => $periodStart, 'resumed_on' => $startedOn];
            $preStart = (int) CarbonImmutable::parse($periodStart)
                ->diffInDays(CarbonImmutable::parse($startedOn));
        }

        return [
            ...IsolationProrata::calculate(
                $monthlyPrice,
                $periodStart,
                $periodEnd,
                $pauses
            ),
            'pauses' => $pauses,
            'pre_start_days' => $preStart,
        ];
    }
}
