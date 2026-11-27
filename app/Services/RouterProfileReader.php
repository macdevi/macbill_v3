<?php

namespace App\Services;

use App\Models\NetworkRouter;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;

class RouterProfileReader
{
    public function read(NetworkRouter $router): array
    {
        $client = null;

        try {
            $config = new Config([
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
            ]);

            $client = new Client($config);
            $rows = $client->query(
                (new Query('/ppp/profile/print'))->equal('.proplist', 'name')
            )->read();

            $names = [];
            foreach ($rows as $row) {
                if (! is_array($row) || ! isset($row['name']) || ! is_string($row['name'])) {
                    throw new \RuntimeException('Invalid profile response');
                }
                $names[] = $row['name'];
            }

            $names = array_values(array_unique($names));
            natcasesort($names);

            return array_values($names);
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
