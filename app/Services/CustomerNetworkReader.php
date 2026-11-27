<?php

namespace App\Services;

use App\Models\Customer;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;

class CustomerNetworkReader
{
    public function read(Customer $customer): array
    {
        if ($customer->service_type !== 'pppoe' || blank($customer->pppoe_username)) {
            throw new \RuntimeException('Unsupported customer');
        }

        $router = $customer->router;
        $package = $customer->package;

        if (! $router || ! $package) {
            throw new \RuntimeException('Missing assignment');
        }

        $mapping = $package->routers()
            ->where('network_routers.id', $router->id)->first();

        $normal = (string) ($mapping?->pivot?->normal_profile ?? '');
        $isolation = (string) ($router->isolation_profile ?? '');
        $username = $customer->pppoe_username;
        $client = null;

        try {
            $client = new Client(new Config([
                'host' => $router->host,
                'port' => (int) $router->port,
                'user' => $router->username,
                'pass' => $router->password,
                'ssl' => (bool) $router->use_tls,
                'ssl_options' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                    'ciphers' => 'DEFAULT',
                ],
                'timeout' => 5,
                'socket_timeout' => 10,
                'socket_blocking' => true,
                'throw_timeout_exception' => true,
                'attempts' => 1,
                'delay' => 1,
            ]));

            $secrets = $client->query(
                (new Query('/ppp/secret/print'))
                    ->equal('.proplist', 'name,profile,service,disabled')
                    ->where('name', $username)
            )->read();

            if (! is_array($secrets) || count($secrets) > 1) {
                throw new \RuntimeException('Unexpected account response');
            }

            $secret = null;

            foreach ($secrets as $row) {
                if (
                    ! is_array($row) ||
                    ! isset($row['name'], $row['profile'], $row['service']) ||
                    $row['name'] !== $username ||
                    ! is_string($row['profile']) ||
                    ! is_string($row['service']) ||
                    ! array_key_exists('disabled', $row) ||
                    ! in_array(
                        $row['disabled'],
                        [true, false, 'true', 'false', 'yes', 'no', '1', '0', 1, 0],
                        true
                    )
                ) {
                    throw new \RuntimeException('Invalid account response');
                }

                $secret = [
                    'profile' => $row['profile'],
                    'service' => $row['service'],
                    'disabled' => in_array(
                        $row['disabled'], [true, 'true', 'yes', '1', 1], true
                    ),
                ];
            }

            $activeRows = $client->query(
                (new Query('/ppp/active/print'))
                    ->equal('.proplist', 'name,service')
                    ->where('name', $username)
                    ->where('service', 'pppoe')
            )->read();

            if (! is_array($activeRows) || count($activeRows) > 100) {
                throw new \RuntimeException('Unexpected session response');
            }

            foreach ($activeRows as $row) {
                if (
                    ! is_array($row) ||
                    ($row['name'] ?? null) !== $username ||
                    ($row['service'] ?? null) !== 'pppoe'
                ) {
                    throw new \RuntimeException('Invalid session response');
                }
            }

            return self::summarize(
                $secret, count($activeRows), $normal, $isolation
            );
        } finally {
            if ($client !== null && method_exists($client, 'disconnect')) {
                try {
                    $client->disconnect();
                } catch (\Throwable $ignored) {
                }
            }
        }
    }

    public static function summarize(
        ?array $secret,
        int $sessionCount,
        string $normal,
        string $isolation
    ): array {
        $notes = [];
        $profile = $secret['profile'] ?? null;

        if ($secret === null) {
            $access = 'Akun lokal tidak ditemukan';
            $notes[] = 'Tidak ditemukan pada PPP secret router yang dipilih. Akun berbasis RADIUS tidak dapat dipastikan dari pemeriksaan akun lokal ini.';
        } elseif (! in_array($secret['service'], ['pppoe', 'any'], true)) {
            $access = 'Jenis akun bukan PPPoE';
            $notes[] = 'Ada akun dengan nama yang sama, tetapi jenis layanannya bukan PPPoE/any.';
        } elseif ($secret['disabled']) {
            $access = 'Akun dinonaktifkan';
            $notes[] = 'Akun disabled tidak disamakan dengan isolir berbasis profil.';
        } elseif ($normal !== '' && $normal === $isolation) {
            $access = 'Pemetaan profil ambigu';
            $notes[] = 'Profil normal dan profil isolir dikonfigurasi sama. Periksa pemetaan sebelum tindakan manual.';
        } elseif ($isolation !== '' && $profile === $isolation) {
            $access = 'Profil isolir';
        } elseif ($normal !== '' && $profile === $normal) {
            $access = 'Profil normal';
        } else {
            $access = 'Profil berbeda / belum dikenali';
            $notes[] = 'Profil akun tidak cocok dengan profil normal paket maupun profil isolir yang dikonfigurasi.';
        }

        if ($normal === '') {
            $notes[] = 'Profil normal paket pada router ini belum dipetakan.';
        }

        if ($isolation === '') {
            $notes[] = 'Profil isolir router belum diatur.';
        }

        if ($sessionCount > 1) {
            $notes[] = 'Ditemukan lebih dari satu sesi PPPoE dengan nama akun yang sama.';
        }

        $notes[] = 'Profil yang ditampilkan berasal dari akun, bukan verifikasi konfigurasi sesi aktif atau keberhasilan pembatasan internet.';
        $notes[] = 'Terputus berarti tidak ditemukan sesi PPPoE aktif saat pemeriksaan, bukan kepastian penyebab gangguan.';

        return [
            'access' => $access,
            'connection' => $sessionCount > 0 ? 'Terhubung' : 'Terputus',
            'profile' => $profile ?? 'Tidak ditemukan',
            'normal_profile' => $normal !== '' ? $normal : 'Belum dipetakan',
            'isolation_profile' => $isolation !== '' ? $isolation : 'Belum diatur',
            'session_count' => $sessionCount,
            'note' => implode(' ', $notes),
        ];
    }
}
