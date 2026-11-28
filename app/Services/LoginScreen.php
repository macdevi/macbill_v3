<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LoginScreen
{
    public const PLACEHOLDERS = ['{nama}', '{slug}'];

    public const ACCENTS = [
        'green' => ['label' => 'Hijau', 'fg' => '#33ff66', 'dim' => '#1f9e40'],
        'cyan' => ['label' => 'Sian', 'fg' => '#5eead4', 'dim' => '#2a9d8f'],
        'amber' => ['label' => 'Ambar', 'fg' => '#ffb000', 'dim' => '#a87400'],
        'white' => ['label' => 'Putih', 'fg' => '#e6edf3', 'dim' => '#8b98a5'],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'command' => '$ ./{slug} --masuk',
            'title' => '{nama} · Billing & Manajemen Jaringan',
            'tagline' => 'Billing PPPoE & Tagihan Pelanggan',
            'modules' => ['Memuat modul pelanggan', 'Memuat modul tagihan'],
            'spinner' => 'Menyiapkan layar masuk',
            'message' => 'Silakan login untuk melanjutkan.',
            'splash' => true,
            'rain' => true,
            'accent' => 'green',
        ];
    }

    /**
     * Nilai tersimpan digabung dengan bawaan, penanda belum diganti.
     *
     * @return array<string, mixed>
     */
    public static function raw(mixed $stored): array
    {
        $defaults = self::defaults();

        if (! is_array($stored)) {
            return $defaults;
        }

        $merged = array_replace($defaults, array_intersect_key($stored, $defaults));

        if (! is_array($merged['modules']) || $merged['modules'] === []) {
            $merged['modules'] = $defaults['modules'];
        }

        if (! is_string($merged['accent']) || ! isset(self::ACCENTS[$merged['accent']])) {
            $merged['accent'] = 'green';
        }

        return $merged;
    }

    public static function render(string $text, string $name, string $slug): string
    {
        return str_replace(self::PLACEHOLDERS, [$name, $slug], $text);
    }

    /**
     * Data siap pakai untuk halaman login (penanda sudah diganti).
     *
     * @return array<string, mixed>
     */
    public static function forLogin(): array
    {
        $name = '';
        $stored = null;

        try {
            $row = DB::table('billing_settings')->where('id', 1)->first();
            $name = trim((string) ($row->business_name ?? ''));
            $json = $row->login_screen ?? null;
            $stored = is_string($json) ? json_decode($json, true) : null;
        } catch (\Throwable $e) {
            $stored = null;
        }

        $name = $name !== '' ? $name : 'MacBilling';
        $slug = Str::slug($name) ?: 'macbilling';
        $raw = self::raw($stored);
        $accent = self::ACCENTS[(string) $raw['accent']];

        return [
            'name' => $name,
            'slug' => $slug,
            'command' => self::render((string) $raw['command'], $name, $slug),
            'title' => self::render((string) $raw['title'], $name, $slug),
            'tagline' => self::render((string) $raw['tagline'], $name, $slug),
            'modules' => array_values(array_map(
                fn ($line) => self::render((string) $line, $name, $slug),
                (array) $raw['modules']
            )),
            'spinner' => self::render((string) $raw['spinner'], $name, $slug),
            'message' => self::render((string) $raw['message'], $name, $slug),
            'splash' => (bool) $raw['splash'],
            'rain' => (bool) $raw['rain'],
            'fg' => $accent['fg'],
            'dim' => $accent['dim'],
        ];
    }
}
