<?php

namespace App\Services;

use App\Models\NetworkRouter;
use Illuminate\Support\Facades\Cache;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;

class CustomerConnectionMonitor
{
    public function snapshot(NetworkRouter $router): array
    {
        $unknown = [
            'available' => false,
            'sessions' => [],
            'checked_at' => null,
        ];

        try {
            $fingerprint = hash('sha256', serialize([
                $router->id, $router->host, $router->port,
                $router->use_tls, $router->username,
                $router->getRawOriginal('password'),
            ]));

            // File cache dibagi antarproses aplikasi pada server yang sama.
            $store = Cache::store('file');
            $key = 'billing:pppoe-status:'.$fingerprint;

            $cached = $store->get($key);
            if (self::validSnapshot($cached)) {
                return $cached;
            }

            $result = $store->lock($key.':lock', 30)->get(
                function () use ($store, $key, $router) {
                    $cached = $store->get($key);
                    if (self::validSnapshot($cached)) {
                        return $cached;
                    }

                    try {
                        $result = $this->readSessions($router);
                    } catch (\Throwable $ignored) {
                        $result = [
                            'available' => false,
                            'sessions' => [],
                            'checked_at' => now()->toIso8601String(),
                        ];
                    }

                    $store->put(
                        $key,
                        $result,
                        $result['available'] ? 25 : 10
                    );

                    return $result;
                }
            );

            return self::validSnapshot($result) ? $result : $unknown;
        } catch (\Throwable $ignored) {
            // Kegagalan cache maupun API tidak boleh dianggap Offline.
            return $unknown;
        }
    }

    private static function validSnapshot(mixed $value): bool
    {
        return is_array($value)
            && isset($value['available'], $value['sessions'])
            && is_bool($value['available'])
            && is_array($value['sessions'])
            && array_key_exists('checked_at', $value);
    }

    public static function usernameKey(string $username): string
    {
        return hash_hmac('sha256', $username, (string) config('app.key'));
    }

    public static function state(
        bool $available,
        array $sessions,
        ?string $username
    ): string {
        if (!$available || $username === null || $username === '') {
            return 'unknown';
        }

        return isset($sessions[self::usernameKey($username)])
            ? 'online' : 'offline';
    }

    private function readSessions(NetworkRouter $router): array
    {
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

            $rows = $client->query(
                (new Query('/ppp/active/print'))
                    ->equal('.proplist', 'name,service')
                    ->where('service', 'pppoe')
            )->read();

            if (!is_array($rows) || count($rows) > 50000) {
                throw new \RuntimeException('Unexpected session response');
            }

            $sessions = [];

            foreach ($rows as $row) {
                if (
                    !is_array($row) ||
                    !isset($row['name'], $row['service']) ||
                    !is_string($row['name']) ||
                    $row['name'] === '' ||
                    $row['service'] !== 'pppoe'
                ) {
                    throw new \RuntimeException('Invalid session response');
                }

                // Username asli tidak disimpan dalam cache status.
                $sessions[self::usernameKey($row['name'])] = true;
            }

            return [
                'available' => true,
                'sessions' => $sessions,
                'checked_at' => now()->toIso8601String(),
            ];
        } finally {
            if ($client !== null && method_exists($client, 'disconnect')) {
                try {
                    $client->disconnect();
                } catch (\Throwable $ignored) {
                }
            }
        }
    }
}
