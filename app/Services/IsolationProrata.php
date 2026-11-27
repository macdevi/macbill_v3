<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

class IsolationProrata
{
    /**
     * pauses:
     * [
     *   ['paused_from' => '2026-11-01', 'resumed_on' => '2026-11-10'],
     *   ['paused_from' => '2026-11-20', 'resumed_on' => null],
     * ]
     *
     * paused_from termasuk hari jeda.
     * resumed_on sudah kembali menjadi hari layanan.
     */
    public static function calculate(
        int $monthlyPrice,
        string $periodStart,
        string $periodEnd,
        array $pauses
    ): array {
        if ($monthlyPrice < 0 || $monthlyPrice > 1000000000) {
            throw new InvalidArgumentException('Harga bulanan tidak valid.');
        }

        $start = self::date($periodStart);
        $end = self::date($periodEnd);

        if ($end->lt($start)) {
            throw new InvalidArgumentException('Urutan periode tidak valid.');
        }

        $periodDays = (int) $start->diffInDays($end) + 1;

        if ($periodDays > 366) {
            throw new InvalidArgumentException('Periode terlalu panjang.');
        }

        $intervals = [];

        foreach ($pauses as $pause) {
            if (
                !is_array($pause)
                || !isset($pause['paused_from'])
                || !is_string($pause['paused_from'])
                || !array_key_exists('resumed_on', $pause)
                || (
                    $pause['resumed_on'] !== null
                    && !is_string($pause['resumed_on'])
                )
            ) {
                throw new InvalidArgumentException('Data jeda tidak valid.');
            }

            $from = self::date($pause['paused_from']);
            $resume = $pause['resumed_on'] === null
                ? null
                : self::date($pause['resumed_on']);

            if ($resume !== null && $resume->lt($from)) {
                throw new InvalidArgumentException(
                    'Tanggal pemulihan mendahului awal jeda.'
                );
            }

            $intervals[] = [$from, $resume];
        }

        $pausedDays = 0;

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            foreach ($intervals as [$from, $resume]) {
                if (
                    $day->gte($from)
                    && ($resume === null || $day->lt($resume))
                ) {
                    $pausedDays++;
                    break;
                }
            }
        }

        $activeDays = $periodDays - $pausedDays;
        $isProrated = $pausedDays > 0;
        $chargedDays = min(30, $activeDays);

        $price = $isProrated
            ? intdiv($monthlyPrice * $chargedDays + 15, 30)
            : $monthlyPrice;

        return [
            'monthly_price' => $monthlyPrice,
            'period_days' => $periodDays,
            'paused_days' => $pausedDays,
            'active_days' => $activeDays,
            'prorata_basis_days' => 30,
            'is_prorated' => $isProrated,
            'price' => $price,
        ];
    }

    private static function date(string $value): CarbonImmutable
    {
        try {
            $date = CarbonImmutable::createFromFormat(
                '!Y-m-d',
                $value,
                InvoicePeriod::TZ
            );
        } catch (\Throwable $e) {
            throw new InvalidArgumentException(
                'Format tanggal tidak valid.',
                0,
                $e
            );
        }

        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Tanggal tidak valid.');
        }

        return $date;
    }
}
