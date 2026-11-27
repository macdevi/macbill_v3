<?php

namespace App\Services;

use App\Models\NetworkRouter;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;

class RouterSecretReader
{
    public function read(NetworkRouter $router): array
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

            $response = $client->query(
                (new Query('/ppp/secret/print'))->equal(
                    '.proplist',
                    'name,password,profile,service,disabled'
                )
            )->read();

            if (! is_array($response) || count($response) > 5000) {
                throw new \RuntimeException('Unsupported secret response');
            }

            $result = [];
            foreach ($response as $row) {
                if (! is_array($row) || ! isset($row['name']) || ! is_string($row['name'])) {
                    throw new \RuntimeException('Invalid secret response');
                }

                $service = (string) ($row['service'] ?? 'any');
                if (! in_array($service, ['pppoe', 'any'], true)) {
                    continue;
                }

                $password = $row['password'] ?? null;
                if (
                    ! is_string($password) ||
                    $password === '' ||
                    preg_match('/^\*+$/D', $password) ||
                    in_array(strtolower($password), ['<hidden>', '[hidden]'], true)
                ) {
                    $password = null;
                }

                $result[] = [
                    'username' => $row['name'],
                    'password' => $password,
                    'profile' => (string) ($row['profile'] ?? 'default'),
                    'service' => $service,
                    'disabled' => in_array(
                        $row['disabled'] ?? false,
                        [true, 'true', 'yes', '1', 1],
                        true
                    ),
                ];
            }

            return $result;
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
