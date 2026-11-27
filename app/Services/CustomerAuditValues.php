<?php

namespace App\Services;

use App\Models\InternetPackage;
use App\Models\NetworkRouter;

class CustomerAuditValues
{
    public const FIELDS = [
        'network_router_id' => 'Router',
        'internet_package_id' => 'Paket',
        'service_type' => 'Jenis layanan',
        'started_at' => 'Tanggal mulai layanan',
        'invoice_day' => 'Tanggal tagihan',
        'service_status' => 'Status layanan',
        'price_mode' => 'Sumber harga',
        'custom_monthly_price' => 'Harga khusus',
        'tax_enabled' => 'Penggunaan pajak',
    ];

    public static function normalize(string $field, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($field) {
            'network_router_id', 'internet_package_id',
            'invoice_day', 'custom_monthly_price' => (int) $value,
            'tax_enabled' => (bool) $value,
            'started_at' => substr((string) $value, 0, 10),
            default => (string) $value,
        };
    }

    public static function diff(
        array $before,
        array $after,
        array $eligible
    ): array {
        $changes = [];

        foreach (self::FIELDS as $field => $label) {
            if (!in_array($field, $eligible, true)) {
                continue;
            }

            $old = self::normalize($field, $before[$field] ?? null);
            $new = self::normalize($field, $after[$field] ?? null);

            if ($old === $new) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'label' => $label,
                'before' => self::display($field, $old),
                'after' => self::display($field, $new),
            ];
        }

        return $changes;
    }

    private static function display(string $field, mixed $value): string
    {
        if ($value === null) {
            return match ($field) {
                'invoice_day' => 'Ikuti bawaan',
                'custom_monthly_price' => 'Tidak digunakan',
                default => 'Belum diisi',
            };
        }

        return match ($field) {
            'network_router_id' => self::relatedName(NetworkRouter::class, $value),
            'internet_package_id' => self::relatedName(InternetPackage::class, $value),
            'service_type' => match ($value) {
                'pppoe' => 'PPPoE',
                'static' => 'IP statis',
                default => 'Nilai tidak dikenal',
            },
            'service_status' => match ($value) {
                'active' => 'Aktif',
                'stopped' => 'Berhenti',
                default => 'Nilai tidak dikenal',
            },
            'price_mode' => $value === 'custom' ? 'Harga khusus' : 'Ikuti paket',
            'tax_enabled' => $value ? 'Dikenakan' : 'Tidak dikenakan',
            'invoice_day' => 'Tanggal '.$value,
            'custom_monthly_price' => 'Rp'.number_format($value, 0, ',', '.'),
            default => (string) $value,
        };
    }

    private static function relatedName(string $model, int $id): string
    {
        $name = $model::whereKey($id)->value('name');
        return $name === null
            ? 'ID '.$id.' (tidak ditemukan)'
            : $name.' · ID '.$id;
    }
}
