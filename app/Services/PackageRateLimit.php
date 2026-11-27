<?php

namespace App\Services;

class PackageRateLimit
{
    private static function pair(string $token, callable $parse): array
    {
        $parts = explode('/', $token);
        if (count($parts) > 2) {
            throw new \InvalidArgumentException('Invalid pair');
        }

        return [
            $parse($parts[0]),
            $parse($parts[1] ?? $parts[0]),
        ];
    }

    private static function rate(string $value): float
    {
        if (! preg_match('/^(\d+(?:\.\d+)?)([kKmMgG]?)$/D', $value, $m)) {
            throw new \InvalidArgumentException('Invalid rate');
        }

        $factor = match (strtoupper($m[2])) {
            'K' => 1000,
            'M' => 1000000,
            'G' => 1000000000,
            default => 1,
        };

        $bps = (float) $m[1] * $factor;
        if ($bps > 100000000000 || abs($bps - round($bps)) > 0.00001) {
            throw new \InvalidArgumentException('Unsupported precision');
        }

        return $bps / 1000000;
    }

    public static function parse(string $rate): array
    {
        $tokens = preg_split('/\s+/', trim($rate));
        if (! $tokens || count($tokens) > 6 || $tokens[0] === '') {
            throw new \InvalidArgumentException('Invalid rate-limit');
        }

        $base = self::pair($tokens[0], fn ($v) => self::rate($v));

        foreach ([1, 2, 5] as $i) {
            if (isset($tokens[$i])) {
                $value = self::pair($tokens[$i], fn ($v) => self::rate($v));
                if ($i === 5) {
                    foreach ([0, 1] as $direction) {
                        if ($base[$direction] > 0 && $value[$direction] > $base[$direction]) {
                            throw new \InvalidArgumentException('Minimum exceeds maximum');
                        }
                    }
                }
            }
        }

        if (isset($tokens[3])) {
            self::pair($tokens[3], function ($value) {
                if (! preg_match('/^\d+s?$/D', $value)) {
                    throw new \InvalidArgumentException('Unsupported burst time');
                }

                return (int) $value;
            });
        }

        if (isset($tokens[4]) && ! preg_match('/^[1-8]$/D', $tokens[4])) {
            throw new \InvalidArgumentException('Invalid priority');
        }

        return ['upload' => $base[0], 'download' => $base[1]];
    }
}
