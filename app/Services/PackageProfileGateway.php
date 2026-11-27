<?php

namespace App\Services;

use App\Models\NetworkRouter;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;

class PackageProfileGateway
{
    private function connect(NetworkRouter $router): Client
    {
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

        return new Client($config);
    }

    private function close($client): void
    {
        if ($client && method_exists($client, 'disconnect')) {
            try {
                $client->disconnect();
            } catch (\Throwable $ignored) {
            }
        }
    }

    private function row(Client $client, string $name): array
    {
        $rows = $client->query(
            (new Query('/ppp/profile/print'))
                ->where('name', $name)
                ->equal('.proplist', '.id,name,rate-limit')
        )->read();

        if (count($rows) !== 1 || ! isset($rows[0]['.id'], $rows[0]['name'])
            || $rows[0]['name'] !== $name) {
            throw new \RuntimeException('Profile unavailable');
        }

        return [
            'id' => (string) $rows[0]['.id'],
            'name' => (string) $rows[0]['name'],
            'rate' => (string) ($rows[0]['rate-limit'] ?? ''),
        ];
    }

    public function read(NetworkRouter $router, string $name): array
    {
        $client = null;
        try {
            $client = $this->connect($router);

            return $this->row($client, $name);
        } finally {
            $this->close($client);
        }
    }

    public function write(NetworkRouter $router, array $before, string $rate): array
    {
        $client = null;
        try {
            $client = $this->connect($router);

            if ($this->row($client, $before['name']) !== $before) {
                throw new \RuntimeException('Profile changed');
            }

            $client->query(
                (new Query('/ppp/profile/set'))
                    ->equal('.id', $before['id'])
                    ->equal('rate-limit', $rate)
            )->read();

            return $this->row($client, $before['name']);
        } finally {
            $this->close($client);
        }
    }
}
