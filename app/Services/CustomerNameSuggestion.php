<?php

namespace App\Services;

use Illuminate\Support\Str;

class CustomerNameSuggestion
{
    public static function fromSecret(string $username): string
    {
        $name = trim($username);

        // Hapus kode angka di awal beserta pemisah setelahnya.
        $name = preg_replace('/^[0-9]+[\s._@-]*/u', '', $name) ?? $name;

        // Hapus awalan generik berulang, bukan bagian dari nama orang.
        do {
            $previous = $name;
            $name = preg_replace(
                '/^(?:user|client)(?:[\s._@-]+|$)/iu',
                '',
                $name
            ) ?? $name;
            $name = trim($name);
        } while ($name !== $previous);

        $name = preg_replace('/[._@-]+/u', ' ', $name) ?? $name;
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        // Jika tidak tersisa nama, tampilkan sumber untuk ditinjau manual.
        if ($name === '') {
            $name = trim($username);
        }

        return Str::limit(Str::title($name), 150, '');
    }
}
