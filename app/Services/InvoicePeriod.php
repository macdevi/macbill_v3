<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class InvoicePeriod
{
    public const TZ = 'Asia/Jakarta';

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TZ)->startOfDay();
    }

    public static function anchor(int $year, int $month, int $day): CarbonImmutable
    {
        $first = CarbonImmutable::create($year, $month, 1, 0, 0, 0, self::TZ);

        return $first->day(min($day, $first->daysInMonth));
    }

    public static function forIssueMonth(int $year, int $month, int $day): array
    {
        if ($day < 1 || $day > 31 || $month < 1 || $month > 12 || $year < 2000) {
            throw new \InvalidArgumentException('Invalid billing period input');
        }

        $issued = self::anchor($year, $month, $day);
        $previous = $issued->startOfMonth()->subMonthNoOverflow();
        $start = self::anchor($previous->year, $previous->month, $day);

        return [
            'issued_on' => $issued,
            'period_start' => $start,
            'period_end' => $issued->subDay(),
        ];
    }
}
